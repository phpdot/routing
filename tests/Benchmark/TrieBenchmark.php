<?php

declare(strict_types=1);

/**
 * Routing Benchmark — Trie Matcher
 *
 * Measures compilation time and per-match latency across various route counts.
 *
 * Usage: php tests/Benchmark/TrieBenchmark.php
 */

namespace PHPdot\Routing\Tests\Benchmark;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPdot\Routing\Compiler\PatternRegistry;
use PHPdot\Routing\Compiler\RouteCompiler;
use PHPdot\Routing\Matcher\RouteMatch;
use PHPdot\Routing\Matcher\TrieMatcher;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;

final class TrieBenchmark
{
    private const ITERATIONS = 50_000;
    private const WARMUP = 1_000;

    /**
     * @param array<array{method: string, path: string}> $requests
     */
    private static function bench(string $label, TrieMatcher $matcher, array $requests): void
    {
        $count = count($requests);

        // Warmup
        for ($i = 0; $i < self::WARMUP; $i++) {
            $req = $requests[$i % $count];
            $segments = array_values(array_filter(explode('/', trim($req['path'], '/')), fn(string $s): bool => $s !== ''));
            $matcher->match($req['method'], $segments);
        }

        // Benchmark
        $start = hrtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $req = $requests[$i % $count];
            $segments = array_values(array_filter(explode('/', trim($req['path'], '/')), fn(string $s): bool => $s !== ''));
            $matcher->match($req['method'], $segments);
        }
        $elapsed = hrtime(true) - $start;

        $totalMs = $elapsed / 1_000_000;
        $perMatchNs = $elapsed / self::ITERATIONS;
        $perMatchUs = $perMatchNs / 1_000;
        $matchesPerSec = self::ITERATIONS / ($totalMs / 1_000);

        printf(
            "  %-40s %8.2f µs/match  %10s matches/sec\n",
            $label,
            $perMatchUs,
            number_format((int) $matchesPerSec),
        );
    }

    /**
     * @param array<array{methods: array<string>, pattern: string, segments: array<string>}> $routeDefs
     */
    private static function compile(array $routeDefs): TrieMatcher
    {
        $collection = new RouteCollection();
        foreach ($routeDefs as $def) {
            $collection->add(new Route($def['methods'], $def['pattern'], $def['segments'], fn() => null));
        }

        $compiler = new RouteCompiler(new PatternRegistry());
        $root = $compiler->compile($collection);

        return new TrieMatcher($root);
    }

    /**
     * @return array<array{methods: array<string>, pattern: string, segments: array<string>}>
     */
    private static function generateRoutes(int $count, bool $dynamic = false): array
    {
        $routes = [];
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        for ($i = 0; $i < $count; $i++) {
            $method = $methods[$i % count($methods)];
            $prefix = match ($i % 5) {
                0 => 'api',
                1 => 'admin',
                2 => 'web',
                3 => 'auth',
                4 => 'public',
            };

            if ($dynamic) {
                $pattern = "{$prefix}/resource{$i}/{id:int}";
                $segments = [$prefix, "resource{$i}", '{id:int}'];
            } else {
                $pattern = "{$prefix}/resource{$i}/action";
                $segments = [$prefix, "resource{$i}", 'action'];
            }

            $routes[] = [
                'methods' => [$method],
                'pattern' => $pattern,
                'segments' => $segments,
            ];
        }

        return $routes;
    }

    /**
     * @param array<array{methods: array<string>, pattern: string, segments: array<string>}> $routeDefs
     * @return array<array{method: string, path: string}>
     */
    private static function generateRequests(array $routeDefs, int $count): array
    {
        $requests = [];
        $total = count($routeDefs);

        for ($i = 0; $i < $count; $i++) {
            $def = $routeDefs[$i % $total];
            $path = '/' . str_replace('{id:int}', (string) ($i + 1), $def['pattern']);
            $requests[] = [
                'method' => $def['methods'][0],
                'path' => $path,
            ];
        }

        return $requests;
    }

    public static function run(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════════════╗\n";
        echo "║                   PHPdot Routing — Trie Benchmark                   ║\n";
        echo "╠══════════════════════════════════════════════════════════════════════╣\n";
        printf("║  Iterations: %-54s ║\n", number_format(self::ITERATIONS));
        printf("║  Warmup:     %-54s ║\n", number_format(self::WARMUP));
        printf("║  PHP:        %-54s ║\n", PHP_VERSION);
        echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

        // ── Compilation benchmark ──
        echo "  COMPILATION\n";
        echo "  " . str_repeat('─', 66) . "\n";

        foreach ([10, 50, 100, 500, 1000] as $routeCount) {
            $routes = self::generateRoutes($routeCount, true);
            $collection = new RouteCollection();
            foreach ($routes as $def) {
                $collection->add(new Route($def['methods'], $def['pattern'], $def['segments'], fn() => null));
            }

            $compiler = new RouteCompiler(new PatternRegistry());

            // Warmup
            for ($i = 0; $i < 10; $i++) {
                $compiler->compile($collection);
            }

            $start = hrtime(true);
            $iterations = 1000;
            for ($i = 0; $i < $iterations; $i++) {
                $compiler->compile($collection);
            }
            $elapsed = hrtime(true) - $start;

            $perCompileUs = ($elapsed / $iterations) / 1_000;
            printf("  %-40s %8.1f µs/compile\n", "{$routeCount} routes", $perCompileUs);
        }

        echo "\n";

        // ── Static route matching ──
        echo "  STATIC ROUTE MATCHING\n";
        echo "  " . str_repeat('─', 66) . "\n";

        foreach ([10, 100, 500, 1000] as $routeCount) {
            $routes = self::generateRoutes($routeCount, false);
            $matcher = self::compile($routes);
            $requests = self::generateRequests($routes, 100);
            self::bench("{$routeCount} routes (static)", $matcher, $requests);
        }

        echo "\n";

        // ── Dynamic route matching ──
        echo "  DYNAMIC ROUTE MATCHING\n";
        echo "  " . str_repeat('─', 66) . "\n";

        foreach ([10, 100, 500, 1000] as $routeCount) {
            $routes = self::generateRoutes($routeCount, true);
            $matcher = self::compile($routes);
            $requests = self::generateRequests($routes, 100);
            self::bench("{$routeCount} routes (dynamic)", $matcher, $requests);
        }

        echo "\n";

        // ── Worst case: last route matches ──
        echo "  WORST CASE (last route matches)\n";
        echo "  " . str_repeat('─', 66) . "\n";

        foreach ([100, 500, 1000] as $routeCount) {
            $routes = self::generateRoutes($routeCount, true);
            $matcher = self::compile($routes);
            $lastRoute = $routes[$routeCount - 1];
            $lastRequest = [
                'method' => $lastRoute['methods'][0],
                'path' => '/' . str_replace('{id:int}', '999', $lastRoute['pattern']),
            ];
            self::bench("{$routeCount} routes (worst case)", $matcher, [$lastRequest]);
        }

        echo "\n";

        // ── 404 not found (no match) ──
        echo "  NOT FOUND (404 — full trie traversal)\n";
        echo "  " . str_repeat('─', 66) . "\n";

        foreach ([100, 500, 1000] as $routeCount) {
            $routes = self::generateRoutes($routeCount, true);
            $matcher = self::compile($routes);
            $notFound = [
                'method' => 'GET',
                'path' => '/this/path/does/not/exist',
            ];
            self::bench("{$routeCount} routes (404)", $matcher, [$notFound]);
        }

        echo "\n";

        // ── Deep nesting ──
        echo "  DEEP NESTING (10 segments)\n";
        echo "  " . str_repeat('─', 66) . "\n";

        $deepRoutes = [];
        for ($i = 0; $i < 100; $i++) {
            $segments = [];
            $pattern = '';
            for ($d = 0; $d < 10; $d++) {
                $seg = "s{$d}r{$i}";
                $segments[] = $seg;
                $pattern .= ($pattern !== '' ? '/' : '') . $seg;
            }
            $deepRoutes[] = ['methods' => ['GET'], 'pattern' => $pattern, 'segments' => $segments];
        }

        $matcher = self::compile($deepRoutes);
        $deepRequests = self::generateRequests($deepRoutes, 100);
        self::bench('100 routes × 10 depth', $matcher, $deepRequests);

        echo "\n";

        // ── Verify correctness ──
        echo "  CORRECTNESS CHECK\n";
        echo "  " . str_repeat('─', 66) . "\n";

        $routes = self::generateRoutes(100, true);
        $matcher = self::compile($routes);
        $requests = self::generateRequests($routes, 100);

        $matched = 0;
        $missed = 0;
        foreach ($requests as $req) {
            $segments = array_values(array_filter(explode('/', trim($req['path'], '/')), fn(string $s): bool => $s !== ''));
            $result = $matcher->match($req['method'], $segments);
            if ($result instanceof RouteMatch) {
                $matched++;
            } else {
                $missed++;
            }
        }

        printf("  Matched: %d / %d  Missed: %d\n", $matched, count($requests), $missed);
        echo $missed === 0 ? "  ✓ All routes matched correctly\n" : "  ✗ Some routes failed to match\n";

        echo "\n";
    }
}

TrieBenchmark::run();
