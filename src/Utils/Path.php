<?php

declare(strict_types=1);

/**
 * Path
 *
 * URL path manipulation utilities.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Utils;

final class Path
{
    /**
     * Split a path into non-empty segments.
     *
     * @param string $path URL path to split
     * @return array<string> Non-empty path segments
     */
    public static function segments(string $path): array
    {
        return array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $s): bool => $s !== '',
        ));
    }

    /**
     * Build a path from segments.
     *
     * @param array<string> $segments Path segments to join
     * @return string Assembled URL path with leading slash
     */
    public static function build(array $segments): string
    {
        return '/' . implode('/', $segments);
    }

    /**
     * Get the first segment of a path.
     *
     * @param string $path URL path
     * @return string|null First segment, or null if path is empty
     */
    public static function first(string $path): string|null
    {
        $segments = self::segments($path);

        return $segments[0] ?? null;
    }

    /**
     * Remove the first segment and return the remaining path.
     *
     * @param string $path URL path to shift
     * @return string Remaining path after removing the first segment
     */
    public static function shift(string $path): string
    {
        $segments = self::segments($path);
        array_shift($segments);

        return self::build($segments);
    }
}
