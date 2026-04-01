<?php

declare(strict_types=1);

/**
 * Route Group
 *
 * Fluent builder for registering routes with shared prefix, middleware, and hosts.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Route;

use Closure;
use PHPdot\Routing\Router;
use PHPdot\Routing\Traits\HttpMethodsTrait;
use PHPdot\Routing\Utils\Path;

final class RouteGroup
{
    use HttpMethodsTrait;

    /** @var array<Route> */
    private array $routes = [];

    /** @var array<RouteGroup> */
    private array $groups = [];

    /** @var array<string|Closure> */
    private array $middlewares = [];

    /** @var array<string> */
    private array $hosts = [];

    /** @var array<string> */
    private array $prefixes = [];

    private RouteScope|null $scope = null;

    /**
     * @param Router $router Parent router instance
     */
    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * Register routes under a shared prefix.
     *
     * @param string $prefix URL prefix for grouped routes
     * @param Closure $callback Callback receiving this group for route registration
     * @return self Fluent return
     */
    public function handle(string $prefix, Closure $callback): self
    {
        $currentPrefixes = $this->router->getPrefixes();
        $groupPrefixes = Path::segments($prefix);
        $merged = array_merge($currentPrefixes, $groupPrefixes);
        $this->prefixes = array_merge($this->prefixes, $merged);

        $this->router->setPrefixes($merged);
        $callback($this);
        $this->router->setPrefixes($currentPrefixes);

        return $this;
    }

    /**
     * Register a route within this group.
     *
     * @param string|array<string> $method HTTP method(s)
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function addRoute(string|array $method, string $pattern, Closure|string|array $handler): Route
    {
        $route = $this->router->addRoute($method, $pattern, $handler);

        if ($this->scope !== null) {
            $route->scope($this->scope);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Create a nested sub-group.
     *
     * @param string $prefix URL prefix for the sub-group
     * @param Closure $callback Callback receiving the sub-group for route registration
     * @return RouteGroup The created sub-group
     */
    public function group(string $prefix, Closure $callback): RouteGroup
    {
        $group = $this->router->group($prefix, $callback);
        $this->groups[] = $group;

        return $group;
    }

    /**
     * Add middleware to all routes in this group.
     *
     * @param string|Closure $middleware Middleware class name or inline closure
     * @return self Fluent return
     */
    public function middleware(string|Closure $middleware): self
    {
        $this->middlewares[] = $middleware;

        foreach ($this->routes as $route) {
            $route->middleware($middleware);
        }

        foreach ($this->groups as $group) {
            $group->middleware($middleware);
        }

        return $this;
    }

    /**
     * Set host constraints for all routes in this group.
     *
     * @param array<string> $hosts Allowed hostnames
     * @return self Fluent return
     */
    public function hosts(array $hosts): self
    {
        $this->hosts = $hosts;
        foreach ($this->routes as $route) {
            $route->hosts($this->hosts);
        }

        return $this;
    }

    /**
     * Set a single host constraint for all routes.
     *
     * @param string $host Allowed hostname
     * @return self Fluent return
     */
    public function host(string $host): self
    {
        $this->hosts = [$host];
        foreach ($this->routes as $route) {
            $route->hosts($this->hosts);
        }

        return $this;
    }

    /**
     * Apply a scope bundle to all routes in this group.
     *
     * @param RouteScope $scope Scope to apply
     * @return self Fluent return
     */
    public function scope(RouteScope $scope): self
    {
        $this->scope = $scope;

        foreach ($this->routes as $route) {
            $route->scope($scope);
        }

        foreach ($this->groups as $group) {
            $group->scope($scope);
        }

        return $this;
    }

    /**
     * Mark all routes in this group as exposed.
     *
     * @return self Fluent return
     */
    public function expose(): self
    {
        foreach ($this->routes as $route) {
            $route->expose();
        }
        foreach ($this->groups as $group) {
            $group->expose();
        }

        return $this;
    }

    /**
     * Get all middleware registered on this group.
     *
     * @return array<string|Closure> Middleware class names or closures
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
