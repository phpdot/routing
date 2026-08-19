<?php

declare(strict_types=1);

/**
 * Route
 *
 * Route definition. Created during registration, compiled into the trie,
 * matched at dispatch time. Its methods and handler are fixed at
 * construction; its pattern is settled by registration time too, with one
 * exception — applying a scope prepends that scope's path, which is why the
 * pattern and its parsed segments are not readonly. Both are final before
 * compile(), so the trie is built from a pattern nothing can still change.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Route;

use Closure;
use PHPdot\Routing\Exception\RoutingException;
use PHPdot\Routing\Utils\Path;
use Psr\Http\Server\MiddlewareInterface;

final class Route
{
    private string|null $name = null;

    /**
     * @var array<string, string>
     */
    private array $where = [];

    /**
     * @var array<string|Closure>
     */
    private array $middlewares = [];

    /**
     * @var array<string>
     */
    private array $hosts = [];

    private RouteScope|null $scope = null;

    private bool $exposed = false;

    /**
     * One compiled route: its methods, pattern, parsed segments, and handler.
     *
     * @param array<string> $methods HTTP methods this route responds to
     * @param string $pattern Full URI pattern with prefixes applied
     * @param array<string> $segments Parsed pattern segments
     * @param Closure|string|array<int, string> $handler Route handler
     */
    public function __construct(
        private readonly array $methods,
        private string $pattern,
        private array $segments,
        private readonly Closure|string|array $handler,
    ) {}

    /**
     * Get the HTTP methods this route responds to.
     *
     * @return array<string> HTTP method names
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get the full URI pattern.
     *
     * @return string URI pattern
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the parsed pattern segments.
     *
     * @return array<string> URL segments
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * Get the route handler.
     *
     * @return Closure|string|array<int, string> Handler callable or controller reference
     */
    public function getHandler(): Closure|string|array
    {
        return $this->handler;
    }

    /**
     * Get the route name.
     *
     * @return string|null Route name, or null if unnamed
     */
    public function getName(): string|null
    {
        return $this->name;
    }

    /**
     * Get parameter constraint overrides.
     *
     * @return array<string, string> Parameter name to constraint type mapping
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * Get all registered middleware.
     *
     * @return array<string|Closure> Middleware class names or closures
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Get host constraints.
     *
     * @return array<string> Allowed hostnames
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * Get the scope applied to this route.
     *
     * @return RouteScope|null Applied scope, or null if none
     */
    public function getScope(): RouteScope|null
    {
        return $this->scope;
    }

    /**
     * Check if this route is exposed to the client.
     *
     * @return bool True if the route is exposed
     */
    public function isExposed(): bool
    {
        return $this->exposed;
    }

    /**
     * Set the route name.
     *
     * @param string $name Route name for URL generation
     *
     * @return self Fluent return
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set a constraint override for a route parameter.
     *
     * @param string $key Parameter name
     * @param string $type Constraint type (e.g. 'int', 'alpha', 'uuid')
     *
     * @return self Fluent return
     */
    public function where(string $key, string $type): self
    {
        $this->where[$key] = $type;
        return $this;
    }

    /**
     * Add middleware to this route.
     *
     * @param string|Closure $middleware Middleware class name or inline closure
     *
     * @throws RoutingException If the middleware class does not implement MiddlewareInterface
     *
     * @return self Fluent return
     */
    public function middleware(string|Closure $middleware): self
    {
        if (is_string($middleware)) {
            $implements = class_implements($middleware, true);
            if (!is_array($implements)) {
                throw new RoutingException('Invalid middleware');
            }
            if (!in_array(MiddlewareInterface::class, $implements, true)) {
                throw new RoutingException('Invalid middleware');
            }
            if (!in_array($middleware, $this->middlewares, true)) {
                $this->middlewares[] = $middleware;
            }
        } else {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    /**
     * Add a host constraint.
     *
     * @param string $host Hostname to restrict this route to
     *
     * @return self Fluent return
     */
    public function host(string $host): self
    {
        $this->hosts[] = $host;
        return $this;
    }

    /**
     * Set host constraints, replacing any existing ones.
     *
     * @param array<string> $hosts Allowed hostnames
     *
     * @return self Fluent return
     */
    public function hosts(array $hosts): self
    {
        $this->hosts = $hosts;
        return $this;
    }

    /**
     * Apply a scope bundle to this route: its path becomes a prefix on the
     * pattern, its hosts replace the route's own, its middleware runs before
     * the route's own.
     *
     * A route carries at most one scope, and the guard below is what keeps the
     * prefix applied exactly once — a second application would silently
     * produce '/admin/admin/sdp' rather than fail.
     *
     * @param RouteScope $scope Scope to apply
     *
     * @throws RoutingException If a scope is already set
     *
     * @return self Fluent return
     */
    public function scope(RouteScope $scope): self
    {
        if ($this->scope !== null) {
            throw new RoutingException('Scope already set');
        }

        $this->scope = $scope;

        $scope_path = $scope->getPath();
        if ($scope_path !== null && trim($scope_path, '/') !== '') {
            $this->pattern = trim($scope_path, '/') . '/' . ltrim($this->pattern, '/');
            $this->segments = Path::segments($this->pattern);
        }

        $scope_hosts = $scope->getHosts();
        if ($scope_hosts !== []) {
            $this->hosts($scope_hosts);
        }

        $scope_middlewares = $scope->getMiddlewares();
        if ($scope_middlewares !== []) {
            $this->middlewares = array_merge($scope_middlewares, $this->middlewares);
        }

        return $this;
    }

    /**
     * Mark this route as exposed to the client.
     *
     * @return self Fluent return
     */
    public function expose(): self
    {
        $this->exposed = true;
        return $this;
    }
}
