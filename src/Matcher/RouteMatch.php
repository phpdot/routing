<?php

declare(strict_types=1);

/**
 * Route Match
 *
 * Immutable result of a successful route match.
 * Contains the matched route and extracted parameters.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Matcher;

use PHPdot\Routing\Route\Route;

final class RouteMatch
{
    /**
     * @param Route $route Matched route definition
     * @param array<string, mixed> $parameters Extracted route parameters
     */
    public function __construct(
        private readonly Route $route,
        private readonly array $parameters = [],
    ) {}

    /**
     * Get the matched route.
     *
     * @return Route Matched route definition
     */
    public function getRoute(): Route
    {
        return $this->route;
    }

    /**
     * Get all extracted route parameters.
     *
     * @return array<string, mixed> Parameter name-value pairs
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a single parameter by key with optional default.
     *
     * @param string $key Parameter name
     * @param mixed $default Fallback value if parameter is absent
     * @return mixed Parameter value or default
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Check if a parameter exists.
     *
     * @param string $key Parameter name
     * @return bool True if the parameter is set
     */
    public function hasParameter(string $key): bool
    {
        return isset($this->parameters[$key]);
    }
}
