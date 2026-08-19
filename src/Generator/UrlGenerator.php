<?php

declare(strict_types=1);

/**
 * URL Generator
 *
 * Generates URL paths from named routes with parameter substitution.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Generator;

use PHPdot\Routing\Exception\RoutingException;
use PHPdot\Routing\Route\RouteCollection;
use PHPdot\Routing\Utils\Path;

final class UrlGenerator
{
    /**
     * Generates URLs for named routes from the registered collection.
     *
     * The base path is part of generation, not just of matching: the router
     * strips it from incoming requests, so a URL generated without it points
     * outside the deployment and 404s. An app served from /app must be linked
     * to as /app/…, and this is the only place that can know it.
     *
     * @param RouteCollection $routes Collection of registered routes
     * @param string $basePath Deployment prefix, '' or '/prefix' (no trailing slash)
     */
    public function __construct(
        private readonly RouteCollection $routes,
        private readonly string $basePath = '',
    ) {}

    /**
     * Generate a URL path from a named route.
     *
     * @param string $name Route name
     * @param array<string, string|int> $parameters Route parameter values
     * @param array<string, string|int> $query Query string parameters
     *
     * @throws RoutingException If route name not found or required parameter missing
     *
     * @return string Generated URL path with optional query string
     */
    public function generate(string $name, array $parameters = [], array $query = []): string
    {
        $route = $this->routes->findByName($name);
        if ($route === null) {
            throw new RoutingException("Route '{$name}' not found.");
        }

        $segments = $route->getSegments();
        $path = [];

        foreach ($segments as $segment) {
            if (!str_starts_with($segment, '{')) {
                $path[] = $segment;
                continue;
            }

            $parsed = $this->parseParam($segment);
            $paramName = $parsed['name'];

            if (isset($parameters[$paramName])) {
                $path[] = $this->encodeSegment((string) $parameters[$paramName], $parsed['wildcard']);
                continue;
            }

            if ($parsed['optional']) {
                break;
            }

            if ($parsed['wildcard']) {
                throw new RoutingException("Wildcard parameter '{$paramName}' is required for route '{$name}'.");
            }

            throw new RoutingException("Missing required parameter '{$paramName}' for route '{$name}'.");
        }

        $url = Path::deployed($this->basePath, $path);

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Generate a URL path without query string.
     *
     * @param string $name Route name
     * @param array<string, string|int> $parameters Route parameter values
     *
     * @throws RoutingException If route name not found or required parameter missing
     *
     * @return string Generated URL path
     */
    public function path(string $name, array $parameters = []): string
    {
        return $this->generate($name, $parameters);
    }

    /**
     * Check if a named route exists.
     *
     * @param string $name Route name to check
     *
     * @return bool True if the route exists
     */
    public function has(string $name): bool
    {
        return $this->routes->findByName($name) !== null;
    }

    /**
     * Parse a parameter segment into its components.
     *
     * @param string $segment Parameter segment (e.g. {id:int}, {page:int?}, {path:*})
     *
     * @return array{name: string, optional: bool, wildcard: bool} Parsed parameter metadata
     */
    private function parseParam(string $segment): array
    {
        $inner = substr($segment, 1, -1);

        $optional = str_ends_with($inner, '?');
        if ($optional) {
            $inner = substr($inner, 0, -1);
        }

        $parts = explode(':', $inner, 2);
        $name = $parts[0];
        $type = $parts[1] ?? '';

        return [
            'name' => $name,
            'optional' => $optional,
            'wildcard' => $type === '*',
        ];
    }

    /**
     * Percent-encode a parameter value for a URL path segment.
     *
     * Wildcard values are multi-segment catch-all paths: each segment is
     * encoded, but the separating slashes are preserved so the path structure
     * survives. A slash in an ordinary single-segment parameter is encoded
     * (it would otherwise escape its segment and corrupt the path).
     *
     * @param string $value
     * @param bool $wildcard
     *
     * @return string
     */
    private function encodeSegment(string $value, bool $wildcard): string
    {
        if (!$wildcard) {
            return rawurlencode($value);
        }

        return implode('/', array_map('rawurlencode', explode('/', $value)));
    }
}
