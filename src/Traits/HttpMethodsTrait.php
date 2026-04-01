<?php

declare(strict_types=1);

/**
 * HTTP Methods Trait
 *
 * Convenience methods for registering routes by HTTP verb.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Traits;

use Closure;
use PHPdot\Routing\Route\Route;

trait HttpMethodsTrait
{
    /**
     * Register a route for the given method(s) and pattern.
     *
     * @param string|array<string> $method HTTP method(s)
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    abstract public function addRoute(string|array $method, string $pattern, Closure|string|array $handler): Route;

    /**
     * Register a GET route.
     *
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function get(string $pattern, Closure|string|array $handler): Route
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function post(string $pattern, Closure|string|array $handler): Route
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    /**
     * Register a PUT route.
     *
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function put(string $pattern, Closure|string|array $handler): Route
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    /**
     * Register a PATCH route.
     *
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function patch(string $pattern, Closure|string|array $handler): Route
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function delete(string $pattern, Closure|string|array $handler): Route
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Register an OPTIONS route.
     *
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function options(string $pattern, Closure|string|array $handler): Route
    {
        return $this->addRoute('OPTIONS', $pattern, $handler);
    }

    /**
     * Register a HEAD route.
     *
     * @param string $pattern URL pattern
     * @param Closure|string|array<int, string> $handler Route handler
     * @return Route The registered route for chaining
     */
    public function head(string $pattern, Closure|string|array $handler): Route
    {
        return $this->addRoute('HEAD', $pattern, $handler);
    }
}
