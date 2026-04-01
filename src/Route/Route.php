<?php

declare(strict_types=1);

/**
 * Route
 *
 * Immutable route definition. Created during registration,
 * compiled into the trie, matched at dispatch time.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Route;

use Closure;
use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;

final class Route
{
    private string|null $name = null;

    /** @var array<string, string> */
    private array $where = [];

    /** @var array<string|Closure> */
    private array $middlewares = [];

    /** @var array<string> */
    private array $hosts = [];

    private RouteScope|null $scope = null;

    private bool $exposed = false;

    /**
     * @param array<string> $methods HTTP methods this route responds to
     * @param string $pattern Full URI pattern with prefixes applied
     * @param array<string> $segments Parsed pattern segments
     * @param Closure|string|array<int, string> $handler Route handler
     */
    public function __construct(
        private readonly array $methods,
        private readonly string $pattern,
        private readonly array $segments,
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
     * @throws InvalidArgumentException If the middleware class does not implement MiddlewareInterface
     * @return self Fluent return
     */
    public function middleware(string|Closure $middleware): self
    {
        if (is_string($middleware)) {
            $implements = class_implements($middleware, true);
            if (!is_array($implements)) {
                throw new InvalidArgumentException('Invalid middleware');
            }
            if (!in_array(MiddlewareInterface::class, $implements, true)) {
                throw new InvalidArgumentException('Invalid middleware');
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
     * @return self Fluent return
     */
    public function hosts(array $hosts): self
    {
        $this->hosts = $hosts;
        return $this;
    }

    /**
     * Apply a scope bundle to this route.
     *
     * @param RouteScope $scope Scope to apply
     *
     * @throws InvalidArgumentException If a scope is already set
     * @return self Fluent return
     */
    public function scope(RouteScope $scope): self
    {
        if ($this->scope !== null) {
            throw new InvalidArgumentException('Scope already set');
        }

        $this->scope = $scope;

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
