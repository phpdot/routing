<?php

declare(strict_types=1);

/**
 * Route Collection
 *
 * Flat list of all registered routes. Passed to the compiler at boot.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Route;

final class RouteCollection
{
    /** @var array<Route> */
    private array $routes = [];

    /**
     * Add a route to the collection.
     *
     * @param Route $route Route to add
     */
    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * Get all registered routes.
     *
     * @return array<Route> All routes in registration order
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Find a route by its name.
     *
     * @param string $name Route name to search for
     * @return Route|null Matching route, or null if not found
     */
    public function findByName(string $name): Route|null
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Get the total number of routes.
     *
     * @return int Route count
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Check if the collection is empty.
     *
     * @return bool True if no routes are registered
     */
    public function isEmpty(): bool
    {
        return $this->routes === [];
    }

    /**
     * Get all routes marked as exposed.
     *
     * @return array<Route> Exposed routes
     */
    public function getExposed(): array
    {
        return array_values(array_filter(
            $this->routes,
            static fn(Route $route): bool => $route->isExposed(),
        ));
    }
}
