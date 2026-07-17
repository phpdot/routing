<?php

declare(strict_types=1);

/**
 * Router
 *
 * Segment-trie compiled router with PSR-15 dispatch.
 * Implements RequestHandlerInterface — call handle() to dispatch a request.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing;

use Closure;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Routing\Compiler\PatternRegistry;
use PHPdot\Routing\Compiler\RouteCompiler;
use PHPdot\Routing\Contract\ControllerInterface;
use PHPdot\Routing\Contract\MatcherInterface;
use PHPdot\Routing\Generator\UrlGenerator;
use PHPdot\Routing\Matcher\MethodNotAllowed;
use PHPdot\Routing\Matcher\RouteMatch;
use PHPdot\Routing\Matcher\TrieMatcher;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;
use PHPdot\Routing\Route\RouteGroup;
use PHPdot\Routing\Route\RouteScope;
use PHPdot\Routing\Traits\HttpMethodsTrait;
use PHPdot\Routing\Utils\Path;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[Singleton]
class Router implements RequestHandlerInterface
{
    use HttpMethodsTrait;

    private RouteCollection $routes;
    private PatternRegistry $patterns;
    private MatcherInterface|null $matcher = null;

    /**
     * @var array<string>
     */
    private array $prefixes = [];

    /**
     * @var array<string>
     */
    protected array $hosts = [];

    /**
     * @var array<class-string<MiddlewareInterface>|Closure>
     */
    private array $globalMiddlewares = [];

    /**
     * @var array<string, RouteScope>
     */
    private array $scopes = [];

    /**
     * @var Closure(ServerRequestInterface): ResponseInterface|null
     */
    private Closure|null $fallback = null;

    private RouteMatch|null $lastMatch = null;

    private string $basePath = '';

    /**
     * Wire the router to its container and PSR-17 response factory.
     *
     * @param ContainerInterface $container PSR-11 container for resolving controllers and middleware
     * @param ResponseFactoryInterface $responseFactory PSR-17 factory for creating 404/405 responses
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
        $this->routes = new RouteCollection();
        $this->patterns = new PatternRegistry();
    }

    /**
     * Configure the deployment base path. Stripped from incoming request URIs
     * before route matching so routes stay deployment-agnostic.
     *
     * Pass `'/site/admin'` for an app served from `http://host/site/admin/`.
     * Pass `''` (or omit) for root deployments. Multi-segment base paths are
     * supported. Trailing/leading slashes are normalised.
     *
     * Runtime-agnostic — works under FPM, Swoole, or anything else, since the
     * value is configured explicitly rather than auto-detected from `$_SERVER`.
     *
     * @param string $basePath
     *
     * @return self
     */
    public function setBasePath(string $basePath): self
    {
        $trimmed = trim($basePath, '/');
        $this->basePath = $trimmed === '' ? '' : '/' . $trimmed;

        return $this;
    }

    /**
     * Get the configured base path. Returns empty string for root deployments.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Register a route.
     *
     * @param string|array<string> $method
     * @param Closure|string|array<int, string> $handler
     * @param string $pattern
     *
     * @return Route
     */
    public function addRoute(string|array $method, string $pattern, Closure|string|array $handler): Route
    {
        $methods = is_array($method) ? array_map('strtoupper', $method) : [strtoupper($method)];
        $fullPattern = $this->buildPattern($pattern);
        $segments = Path::segments($fullPattern);

        $route = new Route($methods, $fullPattern, $segments, $handler);
        $route->hosts($this->hosts);
        $this->routes->add($route);

        return $route;
    }

    /**
     * Register a group of routes with a shared prefix.
     *
     * @param string $prefix
     * @param Closure $callback
     *
     * @return RouteGroup
     */
    public function group(string $prefix, Closure $callback): RouteGroup
    {
        $group = new RouteGroup($this);
        $group->handle($prefix, $callback);

        return $group;
    }

    /**
     * Register global middleware.
     *
     * @param class-string<MiddlewareInterface>|Closure $middleware
     *
     * @return Router
     */
    public function middleware(string|Closure $middleware): self
    {
        $this->globalMiddlewares[] = $middleware;

        return $this;
    }

    /**
     * Register a fallback handler for unmatched routes.
     *
     * @param Closure(ServerRequestInterface): ResponseInterface $handler
     *
     * @return Router
     */
    public function fallback(Closure $handler): self
    {
        $this->fallback = $handler;

        return $this;
    }

    /**
     * Set host constraint for subsequent routes.
     *
     * @param string $host
     *
     * @return Router
     */
    public function host(string $host): self
    {
        $this->hosts[] = $host;

        return $this;
    }

    /**
     * Set host constraints for subsequent routes.
     *
     * @param array<string> $hosts
     *
     * @return Router
     */
    public function hosts(array $hosts): self
    {
        $this->hosts = array_merge($this->hosts, $hosts);

        return $this;
    }

    /**
     * Register a named scope.
     *
     * @param RouteScope $scope
     *
     * @return Router
     */
    public function addScope(RouteScope $scope): self
    {
        $name = $scope->getName();
        if (isset($this->scopes[$name])) {
            throw new RuntimeException("Scope '{$name}' already exists.");
        }
        $this->scopes[$name] = $scope;

        return $this;
    }

    /**
     * Register multiple scopes at once.
     *
     * @param array<RouteScope> $scopes
     *
     * @return Router
     */
    public function addScopes(array $scopes): self
    {
        foreach ($scopes as $scope) {
            $this->addScope($scope);
        }

        return $this;
    }

    /**
     * Retrieve a registered scope by name.
     *
     * @param string $name
     *
     * @return RouteScope
     */
    public function getScope(string $name): RouteScope
    {
        if (!isset($this->scopes[$name])) {
            throw new RuntimeException("Scope '{$name}' not found.");
        }

        return $this->scopes[$name];
    }

    /**
     * Register a custom pattern type.
     *
     * @param string $regex
     * @param string $name
     *
     * @return Router
     */
    public function addPattern(string $name, string $regex): self
    {
        $this->patterns->add($name, $regex);

        return $this;
    }

    /**
     * Get the current prefix stack.
     *
     * @return array<string>
     */
    public function getPrefixes(): array
    {
        return $this->prefixes;
    }

    /**
     * Set the prefix stack. Used internally by RouteGroup.
     *
     * @param array<string> $prefixes
     *
     * @return void
     */
    public function setPrefixes(array $prefixes): void
    {
        $this->prefixes = $prefixes;
    }

    /**
     * Compile routes into a trie for fast matching.
     * Called automatically on first dispatch if not called explicitly.
     *
     * @return void
     */
    public function compile(): void
    {
        $compiler = new RouteCompiler($this->patterns);
        $root = $compiler->compile($this->routes);
        $this->matcher = new TrieMatcher($root);
    }

    /**
     * Match a method and path against compiled routes.
     *
     * @param array<string> $segments
     * @param string $method
     * @param string $host
     *
     * @return RouteMatch|MethodNotAllowed|null
     */
    public function match(string $method, array $segments, string $host = ''): RouteMatch|MethodNotAllowed|null
    {
        $matcher = $this->matcher ?? $this->compiledMatcher();

        $result = $matcher->match($method, $segments, $host);

        if ($result instanceof RouteMatch) {
            $this->lastMatch = $result;
        }

        return $result;
    }

    /**
     * Dispatch a request through middleware and route handler.
     * Implements PSR-15 RequestHandlerInterface.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->basePath !== '') {
            $stripped = $this->stripBasePath($request);

            if ($stripped === null) {
                return $this->responseFactory->createResponse(404);
            }

            $request = $stripped;
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $host = $request->getHeaderLine('host');

        $segments = Path::segments($path);
        $result = $this->match($method, $segments, $host);

        if ($result instanceof RouteMatch) {
            $route = $result->getRoute();
            $params = $result->getParameters();

            $routedRequest = $request;
            foreach ($params as $key => $value) {
                $routedRequest = $routedRequest->withAttribute($key, $value);
            }
            $routedRequest = $routedRequest->withAttribute('_route', $route);
            $routedRequest = $routedRequest->withAttribute('_route_params', $params);

            $middlewares = array_merge($this->globalMiddlewares, $route->getMiddlewares());

            $response = $this->runMiddlewareChain($routedRequest, $middlewares, $route, $params);

            if ($method === 'HEAD') {
                return $response->withBody($this->createEmptyBody());
            }

            return $response;
        }

        if ($result instanceof MethodNotAllowed) {
            return $this->responseFactory->createResponse(405)
                ->withHeader('Allow', implode(', ', $result->allowedMethods));
        }

        if ($this->fallback !== null) {
            return ($this->fallback)($request);
        }

        return $this->responseFactory->createResponse(404);
    }

    /**
     * Generate a URL from a named route.
     *
     * @param array<string, string|int> $parameters
     * @param array<string, string|int> $query
     * @param string $name
     *
     * @return string
     */
    public function url(string $name, array $parameters = [], array $query = []): string
    {
        return $this->getUrlGenerator()->generate($name, $parameters, $query);
    }

    /**
     * Get the URL generator instance.
     *
     * @return UrlGenerator
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return new UrlGenerator($this->routes);
    }

    /**
     * Get the last matched route from the most recent dispatch.
     *
     * @return ?RouteMatch
     */
    public function getLastMatch(): RouteMatch|null
    {
        return $this->lastMatch;
    }

    /**
     * Get matched routes from the most recent dispatch.
     *
     * @return array<RouteMatch>
     */
    public function getMatchedRoutes(): array
    {
        if ($this->lastMatch === null) {
            return [];
        }

        return [$this->lastMatch];
    }

    /**
     * Get the route collection.
     *
     * @return RouteCollection
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Get the pattern registry.
     *
     * @return PatternRegistry
     */
    public function getPatterns(): PatternRegistry
    {
        return $this->patterns;
    }

    /**
     * Get named routes that have been marked as exposed.
     *
     * @return array<string, string>
     */
    public function exposed(): array
    {
        $map = [];
        foreach ($this->routes->getExposed() as $route) {
            $name = $route->getName();
            if ($name !== null) {
                $map[$name] = '/' . ltrim($route->getPattern(), '/');
            }
        }

        return $map;
    }

    /**
     * List all registered routes with their metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $list = [];
        foreach ($this->routes->all() as $route) {
            $handler = $route->getHandler();
            if ($handler instanceof Closure) {
                $handlerString = 'Closure';
            } elseif (is_array($handler)) {
                $handlerString = $handler[0] . '@' . $handler[1];
            } else {
                $handlerString = $handler;
            }

            $list[] = [
                'methods'     => $route->getMethods(),
                'pattern'     => '/' . ltrim($route->getPattern(), '/'),
                'name'        => $route->getName(),
                'handler'     => $handlerString,
                'middlewares'  => $route->getMiddlewares(),
                'hosts'       => $route->getHosts(),
                'where'       => $route->getWhere(),
                'scope'       => $route->getScope()?->getName(),
            ];
        }

        return $list;
    }

    /**
     * Split a path string into non-empty segments.
     *
     * @deprecated Use Path::segments() instead
     *
     * @param string $path
     *
     * @return array<string>
     */
    public static function splitSegments(string $path): array
    {
        return Path::segments($path);
    }

    /**
     * Compile and return the matcher for lazy compilation on first match.
     *
     * @return MatcherInterface
     */
    private function compiledMatcher(): MatcherInterface
    {
        $this->compile();

        return $this->matcher ?? throw new RuntimeException('Compilation failed.');
    }

    /**
     * Build the full pattern by prepending the current prefix stack.
     *
     * @param string $pattern
     *
     * @return string
     */
    protected function buildPattern(string $pattern): string
    {
        $pattern = trim($pattern, '/');

        if ($this->prefixes !== []) {
            return implode('/', $this->prefixes) . '/' . $pattern;
        }

        return $pattern;
    }

    /**
     * Execute middleware chain then call the route handler.
     *
     * @param array<string|Closure> $middlewares
     * @param array<string, mixed> $params
     * @param ServerRequestInterface $request
     * @param Route $route
     *
     * @return ResponseInterface
     */
    private function runMiddlewareChain(
        ServerRequestInterface $request,
        array $middlewares,
        Route $route,
        array $params,
    ): ResponseInterface {
        $handler = new class ($this->container, $this->responseFactory, $route, $params) implements RequestHandlerInterface {
            /**
             * @param array<string, mixed> $params
             */
            public function __construct(
                private readonly ContainerInterface $container,
                private readonly ResponseFactoryInterface $responseFactory,
                private readonly Route $route,
                private readonly array $params,
            ) {}

            /**
             * Resolve and execute the route handler.
             *
             * @param ServerRequestInterface $request Incoming request with route attributes
             *
             * @return ResponseInterface Handler response
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $routeHandler = $this->route->getHandler();

                if ($routeHandler instanceof Closure) {
                    $result = $routeHandler($request, ...array_values($this->params));
                    if ($result instanceof ResponseInterface) {
                        return $result;
                    }

                    return $this->responseFactory->createResponse(200);
                }

                if (is_string($routeHandler)) {
                    if (!str_contains($routeHandler, '@')) {
                        throw new RuntimeException("Handler string must be 'Class@method' format.");
                    }
                    [$class, $method] = explode('@', $routeHandler, 2);
                } elseif (is_array($routeHandler)) {
                    [$class, $method] = $routeHandler;
                } else {
                    throw new RuntimeException('Invalid handler format.');
                }

                $instance = $this->container->get($class);
                if (!is_object($instance)) {
                    throw new RuntimeException("Container returned non-object for '{$class}'.");
                }

                if (!$instance instanceof ControllerInterface) {
                    throw new RuntimeException("'{$class}' must implement ControllerInterface.");
                }

                if (!method_exists($instance, $method)) {
                    throw new RuntimeException("Method '{$class}::{$method}' does not exist.");
                }

                /**
                 * @var callable $callable
                 */
                $callable = [$instance, $method];
                $result = $callable($request, ...array_values($this->params));

                if ($result instanceof ResponseInterface) {
                    return $result;
                }

                return $this->responseFactory->createResponse(200);
            }
        };

        $pipeline = $handler;
        foreach (array_reverse($middlewares) as $middleware) {
            if ($middleware instanceof Closure) {
                $pipeline = new class ($middleware, $pipeline) implements RequestHandlerInterface {
                    /**
                     * @param Closure $middleware Closure middleware
                     * @param RequestHandlerInterface $next Next handler in the chain
                     */
                    public function __construct(
                        private readonly Closure $middleware,
                        private readonly RequestHandlerInterface $next,
                    ) {}

                    /**
                     * Execute the closure middleware.
                     *
                     * @param ServerRequestInterface $request Incoming request
                     *
                     * @return ResponseInterface Middleware response
                     */
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        /**
                         * @var ResponseInterface
                         */
                        return ($this->middleware)($request, $this->next);
                    }
                };
            } else {
                /**
                 * @var class-string $middleware
                 */
                $resolved = $this->container->get($middleware);
                if (!$resolved instanceof MiddlewareInterface) {
                    throw new RuntimeException("'{$middleware}' must implement MiddlewareInterface.");
                }
                $pipeline = new class ($resolved, $pipeline) implements RequestHandlerInterface {
                    /**
                     * @param MiddlewareInterface $middleware PSR-15 middleware instance
                     * @param RequestHandlerInterface $next Next handler in the chain
                     */
                    public function __construct(
                        private readonly MiddlewareInterface $middleware,
                        private readonly RequestHandlerInterface $next,
                    ) {}

                    /**
                     * Process the PSR-15 middleware.
                     *
                     * @param ServerRequestInterface $request Incoming request
                     *
                     * @return ResponseInterface Middleware response
                     */
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return $this->middleware->process($request, $this->next);
                    }
                };
            }
        }

        return $pipeline->handle($request);
    }

    /**
     * Create an empty stream for HEAD responses.
     *
     * @return StreamInterface
     */
    private function createEmptyBody(): StreamInterface
    {
        $response = $this->responseFactory->createResponse();

        return $response->getBody();
    }

    /**
     * Strip the configured base path from the incoming request URI so routes
     * defined as `/users` match `/{basePath}/users` from the wire.
     *
     * Returns null when the request URI does not begin with the configured base
     * path — the caller treats this as a 404 (the request belongs to a different
     * application served from the same host).
     *
     * @param ServerRequestInterface $request
     *
     * @return ?ServerRequestInterface
     */
    private function stripBasePath(ServerRequestInterface $request): ServerRequestInterface|null
    {
        $path = $request->getUri()->getPath();

        if (!str_starts_with($path, $this->basePath)) {
            return null;
        }

        $remainder = substr($path, strlen($this->basePath));

        if ($remainder !== '' && $remainder[0] !== '/') {
            return null;
        }

        $newPath = $remainder === '' ? '/' : $remainder;

        return $request->withUri($request->getUri()->withPath($newPath));
    }
}
