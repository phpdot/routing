<?php

declare(strict_types=1);

/**
 * Method Not Allowed
 *
 * Match result when the path exists but the HTTP method is not allowed.
 * Carries the list of allowed methods for the 405 Allow header.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Matcher;

final class MethodNotAllowed
{
    /**
     * @param array<string> $allowedMethods HTTP methods that would match this path
     */
    public function __construct(
        public readonly array $allowedMethods,
    ) {}
}
