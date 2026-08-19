<?php

declare(strict_types=1);

/**
 * Trie Matcher
 *
 * Walks the compiled segment trie to match a request. Matching is method- and
 * host-aware: a terminal is accepted only if it carries the requested method
 * (GET stands in for HEAD) and its host constraint is satisfied, and the walk
 * keeps searching sibling branches when a terminal does not qualify — so a
 * sibling dynamic route or an unhosted route is never shadowed into a spurious
 * 404/405. O(depth) for the matched path; a full traversal runs only to decide
 * 405 vs 404 when nothing matched.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Matcher;

use PHPdot\Routing\Contract\MatcherInterface;
use PHPdot\Routing\Route\Route;

final class TrieMatcher implements MatcherInterface
{
    /**
     * A matcher that walks a compiled route trie to resolve a request.
     *
     * @param TrieNode $root Root node of the compiled trie
     */
    public function __construct(
        private readonly TrieNode $root,
    ) {}

    /**
     * Match a request method and path segments against the compiled trie.
     *
     * @param string $method HTTP method
     * @param array<string> $segments URL path segments
     * @param string $host Request hostname
     *
     * @return RouteMatch|MethodNotAllowed|null Match result, method-not-allowed, or null if not found
     */
    public function match(string $method, array $segments, string $host = ''): RouteMatch|MethodNotAllowed|null
    {
        /**
         * @var array<string, string> $params
         */
        $params = [];
        /**
         * @var array<string, string|null> $paramTypes
         */
        $paramTypes = [];

        $route = $this->findMatch($this->root, $segments, 0, $method, $host, $params, $paramTypes);

        if ($route !== null) {
            return new RouteMatch($route, $this->castParams($params, $paramTypes));
        }

        $allowed = $this->collectAllowed($this->root, $segments, 0, $host);

        if ($allowed !== []) {
            return new MethodNotAllowed($allowed);
        }

        return null;
    }

    /**
     * Depth-first search for the first method- and host-matching terminal.
     *
     * Priority: static children (hash lookup), then dynamic children (regex),
     * then the wildcard slot. A terminal is accepted only via {@see leafFor()} /
     * {@see wildcardRouteFor()}; non-qualifying terminals are skipped and the
     * search continues into sibling branches, so a sibling that does qualify is
     * never shadowed.
     *
     * @param TrieNode $node Current trie node
     * @param array<string> $segments URL path segments
     * @param int $depth Current segment depth
     * @param string $method HTTP method
     * @param string $host Request hostname
     * @param array<string, string> $params Accumulated parameter values (passed by reference)
     * @param array<string, string|null> $paramTypes Accumulated parameter types (passed by reference)
     *
     * @return Route|null Matching route, or null if no qualifying terminal is reachable
     */
    private function findMatch(
        TrieNode $node,
        array $segments,
        int $depth,
        string $method,
        string $host,
        array &$params,
        array &$paramTypes,
    ): Route|null {
        if ($depth === count($segments)) {
            return $this->leafFor($node, $method, $host);
        }

        $segment = $segments[$depth];

        if (isset($node->staticChildren[$segment])) {
            $result = $this->findMatch(
                $node->staticChildren[$segment],
                $segments,
                $depth + 1,
                $method,
                $host,
                $params,
                $paramTypes,
            );

            if ($result !== null) {
                return $result;
            }
        }

        foreach ($node->dynamicChildren as $child) {
            if (preg_match('/^' . $child['regex'] . '$/', $segment) === 1) {
                $prevParams = $params;
                $prevTypes = $paramTypes;
                $params[$child['name']] = $segment;
                $paramTypes[$child['name']] = $child['pattern'];

                $result = $this->findMatch(
                    $child['node'],
                    $segments,
                    $depth + 1,
                    $method,
                    $host,
                    $params,
                    $paramTypes,
                );

                if ($result !== null) {
                    return $result;
                }

                $params = $prevParams;
                $paramTypes = $prevTypes;
            }
        }

        if ($node->wildcard !== null) {
            $route = $this->wildcardRouteFor($node->wildcard, $method, $host);

            if ($route !== null) {
                $params[$node->wildcard['name']] = implode('/', array_slice($segments, $depth));
                $paramTypes[$node->wildcard['name']] = '*';

                return $route;
            }
        }

        return null;
    }

    /**
     * Collect the union of allowed methods across every host-matching terminal
     * reachable for the path. Runs only when {@see findMatch()} found nothing,
     * to distinguish 405 (path exists under a different method) from 404.
     *
     * @param TrieNode $node Current trie node
     * @param array<string> $segments URL path segments
     * @param int $depth Current segment depth
     * @param string $host Request hostname
     *
     * @return list<string>
     */
    private function collectAllowed(TrieNode $node, array $segments, int $depth, string $host): array
    {
        if ($depth === count($segments)) {
            return $this->allowedFromLeaves($node, $host);
        }

        $segment = $segments[$depth];
        $allowed = [];

        if (isset($node->staticChildren[$segment])) {
            $allowed = array_merge(
                $allowed,
                $this->collectAllowed($node->staticChildren[$segment], $segments, $depth + 1, $host),
            );
        }

        foreach ($node->dynamicChildren as $child) {
            if (preg_match('/^' . $child['regex'] . '$/', $segment) === 1) {
                $allowed = array_merge(
                    $allowed,
                    $this->collectAllowed($child['node'], $segments, $depth + 1, $host),
                );
            }
        }

        if ($node->wildcard !== null) {
            $allowed = array_merge($allowed, $this->wildcardAllowed($node->wildcard, $host));
        }

        return array_values(array_unique($allowed));
    }

    /**
     * The route a leaf node serves for the method (GET for HEAD) when its host
     * matches, or null.
     *
     * @param TrieNode $node
     * @param string $method
     * @param string $host
     *
     * @return Route|null
     */
    private function leafFor(TrieNode $node, string $method, string $host): Route|null
    {
        $route = $node->leaves[$method] ?? null;

        if ($route === null && $method === 'HEAD') {
            $route = $node->leaves['GET'] ?? null;
        }

        if ($route === null) {
            return null;
        }

        return $this->hostMatches($route, $host) ? $route : null;
    }

    /**
     * The route a wildcard slot serves for the method (GET for HEAD) when its
     * host matches, or null.
     *
     * @param array{name: string, route_methods: array<string, Route>} $wildcard
     * @param string $method
     * @param string $host
     *
     * @return Route|null
     */
    private function wildcardRouteFor(array $wildcard, string $method, string $host): Route|null
    {
        $route = $wildcard['route_methods'][$method] ?? null;

        if ($route === null && $method === 'HEAD') {
            $route = $wildcard['route_methods']['GET'] ?? null;
        }

        if ($route === null) {
            return null;
        }

        return $this->hostMatches($route, $host) ? $route : null;
    }

    /**
     * Methods served by a node's leaves whose host matches the request.
     *
     * @param TrieNode $node
     * @param string $host
     *
     * @return list<string>
     */
    private function allowedFromLeaves(TrieNode $node, string $host): array
    {
        $allowed = [];

        foreach ($node->leaves as $method => $route) {
            if ($this->hostMatches($route, $host)) {
                $allowed[] = $method;
            }
        }

        return $allowed;
    }

    /**
     * Methods served by a wildcard slot whose host matches the request.
     *
     * @param array{name: string, route_methods: array<string, Route>} $wildcard
     * @param string $host
     *
     * @return list<string>
     */
    private function wildcardAllowed(array $wildcard, string $host): array
    {
        $allowed = [];

        foreach ($wildcard['route_methods'] as $method => $route) {
            if ($this->hostMatches($route, $host)) {
                $allowed[] = $method;
            }
        }

        return $allowed;
    }

    /**
     * Check if the route's host constraints match the request host.
     *
     * @param Route $route Route with potential host constraints
     * @param string $host Request hostname
     *
     * @return bool True if the host matches or no constraint is set
     */
    private function hostMatches(Route $route, string $host): bool
    {
        $hosts = $route->getHosts();

        return $hosts === [] || in_array($host, $hosts, true);
    }

    /**
     * Cast extracted parameters based on their pattern types.
     *
     * @param array<string, string> $params Raw parameter values
     * @param array<string, string|null> $paramTypes Parameter type hints
     *
     * @return array<string, mixed> Parameters with appropriate type casting applied
     */
    private function castParams(array $params, array $paramTypes): array
    {
        $cast = [];

        foreach ($params as $key => $value) {
            $type = $paramTypes[$key] ?? null;
            $cast[$key] = ($type === 'int') ? (int) $value : $value;
        }

        return $cast;
    }
}
