<?php

declare(strict_types=1);

/**
 * Route Compiler
 *
 * Compiles a flat RouteCollection into a TrieNode tree for fast matching.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Compiler;

use PHPdot\Routing\Matcher\TrieNode;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;
use RuntimeException;

final class RouteCompiler
{
    /**
     * @param PatternRegistry $patterns Segment pattern parser
     */
    public function __construct(
        private readonly PatternRegistry $patterns,
    ) {}

    /**
     * Compile a flat route collection into a trie tree.
     *
     * @param RouteCollection $routes Route collection to compile
     * @return TrieNode Root node of the compiled trie
     */
    public function compile(RouteCollection $routes): TrieNode
    {
        $root = new TrieNode();

        foreach ($routes->all() as $route) {
            $this->insertRoute($root, $route);
        }

        return $root;
    }

    /**
     * Insert a single route into the trie, expanding optional segments.
     *
     * @param TrieNode $root Root node of the trie
     * @param Route $route Route to insert
     */
    private function insertRoute(TrieNode $root, Route $route): void
    {
        $segments = $route->getSegments();
        $where = $route->getWhere();

        $optionalIndex = $this->findOptionalIndex($segments, $where);

        if ($optionalIndex !== null) {
            $withoutSegments = array_values(
                array_filter($segments, static fn(int $i): bool => $i !== $optionalIndex, ARRAY_FILTER_USE_KEY),
            );
            $this->insertSegments($root, $withoutSegments, $route, $where);

            $withSegments = $segments;
            $withSegments[$optionalIndex] = preg_replace('/\?\}$/', '}', $segments[$optionalIndex]) ?? $segments[$optionalIndex];
            $this->insertSegments($root, $withSegments, $route, $where);
        } else {
            $this->insertSegments($root, $segments, $route, $where);
        }
    }

    /**
     * Insert parsed segments into the trie for a given route.
     *
     * @param TrieNode $root Root node of the trie
     * @param array<string> $segments Parsed URL segments
     * @param Route $route Route being inserted
     * @param array<string, string> $where Parameter constraint overrides
     *
     * @throws RuntimeException If a duplicate route is detected
     */
    private function insertSegments(TrieNode $root, array $segments, Route $route, array $where): void
    {
        $node = $root;

        foreach ($segments as $segment) {
            $parsed = $this->patterns->parseSegment($segment, $where);

            if ($parsed === null) {
                if (!isset($node->staticChildren[$segment])) {
                    $node->staticChildren[$segment] = new TrieNode();
                }
                $node = $node->staticChildren[$segment];
                continue;
            }

            if ($parsed['wildcard']) {
                if ($node->wildcard === null) {
                    $node->wildcard = [
                        'name' => $parsed['name'],
                        'route_methods' => [],
                    ];
                }
                foreach ($route->getMethods() as $method) {
                    $node->wildcard['route_methods'][$method] = $route;
                }

                return;
            }

            $found = false;
            foreach ($node->dynamicChildren as &$child) {
                if ($child['name'] === $parsed['name'] && $child['regex'] === $parsed['regex']) {
                    $node = $child['node'];
                    $found = true;
                    break;
                }
            }
            unset($child);

            if (!$found) {
                $newNode = new TrieNode();
                $node->dynamicChildren[] = [
                    'name' => $parsed['name'],
                    'pattern' => $parsed['type'] !== '' ? $parsed['type'] : null,
                    'regex' => $parsed['regex'],
                    'node' => $newNode,
                ];
                $node = $newNode;
            }
        }

        foreach ($route->getMethods() as $method) {
            if (isset($node->leaves[$method])) {
                $existing = $node->leaves[$method]->getPattern();
                $new = $route->getPattern();
                throw new RuntimeException("Duplicate route: {$method} /{$new} conflicts with {$method} /{$existing}");
            }
            $node->leaves[$method] = $route;
        }
        $node->allowedMethods = array_keys($node->leaves);
    }

    /**
     * Find the index of the first optional segment.
     *
     * @param array<string> $segments Parsed URL segments
     * @param array<string, string> $where Parameter constraint overrides
     * @return int|null Index of the first optional segment, or null if none
     */
    private function findOptionalIndex(array $segments, array $where): int|null
    {
        foreach ($segments as $i => $segment) {
            $parsed = $this->patterns->parseSegment($segment, $where);
            if ($parsed !== null && $parsed['optional']) {
                return $i;
            }
        }

        return null;
    }
}
