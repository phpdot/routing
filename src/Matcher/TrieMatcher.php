<?php

declare(strict_types=1);

/**
 * Trie Matcher
 *
 * Walks the compiled segment trie to match a request.
 * O(depth) matching regardless of route count.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Matcher;

use PHPdot\Routing\Route\Route;

final class TrieMatcher implements MatcherInterface
{
    /**
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
     * @return RouteMatch|MethodNotAllowed|null Match result, method-not-allowed, or null if not found
     */
    public function match(string $method, array $segments, string $host = ''): RouteMatch|MethodNotAllowed|null
    {
        /** @var array<string, string> $params */
        $params = [];
        /** @var array<string, string|null> $paramTypes */
        $paramTypes = [];
        $node = $this->walk($this->root, $segments, 0, $params, $paramTypes);

        if ($node === null) {
            return null;
        }

        if (isset($node->leaves[$method])) {
            $route = $node->leaves[$method];
            if (!$this->hostMatches($route, $host)) {
                return null;
            }

            return new RouteMatch($route, $this->castParams($params, $paramTypes));
        }

        if ($method === 'HEAD' && isset($node->leaves['GET'])) {
            $route = $node->leaves['GET'];
            if (!$this->hostMatches($route, $host)) {
                return null;
            }

            return new RouteMatch($route, $this->castParams($params, $paramTypes));
        }

        if ($node->allowedMethods !== []) {
            return new MethodNotAllowed($node->allowedMethods);
        }

        return null;
    }

    /**
     * Walk the trie depth-first with backtracking.
     *
     * Priority: static children (hash lookup), dynamic children (regex), wildcard.
     *
     * @param TrieNode $node Current trie node
     * @param array<string> $segments URL path segments
     * @param int $depth Current segment depth
     * @param array<string, string> $params Accumulated parameter values (passed by reference)
     * @param array<string, string|null> $paramTypes Accumulated parameter types (passed by reference)
     * @return TrieNode|null Matching leaf node, or null if no match
     */
    private function walk(TrieNode $node, array $segments, int $depth, array &$params, array &$paramTypes): TrieNode|null
    {
        $segmentCount = count($segments);

        if ($depth === $segmentCount) {
            if ($node->leaves !== [] || $node->allowedMethods !== []) {
                return $node;
            }

            return null;
        }

        $segment = $segments[$depth];

        if (isset($node->staticChildren[$segment])) {
            $result = $this->walk($node->staticChildren[$segment], $segments, $depth + 1, $params, $paramTypes);
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

                $result = $this->walk($child['node'], $segments, $depth + 1, $params, $paramTypes);
                if ($result !== null) {
                    return $result;
                }

                $params = $prevParams;
                $paramTypes = $prevTypes;
            }
        }

        if ($node->wildcard !== null) {
            $remaining = array_slice($segments, $depth);
            $params[$node->wildcard['name']] = implode('/', $remaining);
            $paramTypes[$node->wildcard['name']] = '*';

            $wildcardNode = new TrieNode();
            $wildcardNode->leaves = $node->wildcard['route_methods'];
            $wildcardNode->allowedMethods = array_keys($wildcardNode->leaves);

            return $wildcardNode;
        }

        return null;
    }

    /**
     * Check if the route's host constraints match the request host.
     *
     * @param Route $route Route with potential host constraints
     * @param string $host Request hostname
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
