<?php

declare(strict_types=1);

/**
 * Matcher Interface
 *
 * Contract for route matching implementations.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Matcher;

interface MatcherInterface
{
    /**
     * Match a request against compiled routes.
     *
     * @param string $method HTTP method
     * @param array<string> $segments URL path segments
     * @param string $host Request hostname
     * @return RouteMatch|MethodNotAllowed|null Match result, method-not-allowed, or null if not found
     */
    public function match(string $method, array $segments, string $host = ''): RouteMatch|MethodNotAllowed|null;
}
