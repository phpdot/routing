<?php

declare(strict_types=1);

/**
 * Route Scope
 *
 * Reusable preset bundle of prefix, hosts, and middleware.
 * Apply to routes via $route->scope($scope).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Route;

final class RouteScope
{
    private string|null $path = null;

    /** @var array<string> */
    private array $hosts = [];

    /** @var array<string> */
    private array $middlewares = [];

    /**
     * @param string $name Scope identifier
     */
    public function __construct(
        private readonly string $name,
    ) {}

    /**
     * Get the scope name.
     *
     * @return string Scope identifier
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the scope path prefix.
     *
     * @param string $path URL path prefix
     * @return self Fluent return
     */
    public function path(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Get the scope path prefix.
     *
     * @return string|null Path prefix, or null if not set
     */
    public function getPath(): string|null
    {
        return $this->path;
    }

    /**
     * Set host constraints, replacing any existing ones.
     *
     * @param array<string> $hosts Allowed hostnames
     * @return self Fluent return
     */
    public function hosts(array $hosts): self
    {
        $this->hosts = $hosts;
        return $this;
    }

    /**
     * Set a single host constraint.
     *
     * @param string $host Allowed hostname
     * @return self Fluent return
     */
    public function host(string $host): self
    {
        $this->hosts = [$host];
        return $this;
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
     * Set middleware, replacing any existing ones.
     *
     * @param array<string> $middlewares Middleware class names
     * @return self Fluent return
     */
    public function middlewares(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    /**
     * Add a single middleware.
     *
     * @param string $middleware Middleware class name
     * @return self Fluent return
     */
    public function middleware(string $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Get all registered middleware.
     *
     * @return array<string> Middleware class names
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
