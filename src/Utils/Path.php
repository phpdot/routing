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
     *
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
     *
     * @return string Assembled URL path with leading slash
     */
    public static function build(array $segments): string
    {
        return '/' . implode('/', $segments);
    }

    /**
     * Build a deployed path: the base path, then the segments.
     *
     * The ONE place an outgoing path is assembled. URL generation and the
     * exposed-route map both call it, because a client that builds links from
     * the exposed map must produce byte-identical strings to the ones the
     * server generates — they get compared, cached and matched against each
     * other. Deriving one from the pattern and the other from the segments is
     * how '/admin/sdp/' and '/admin/sdp' came to mean the same route.
     *
     * @param string $basePath Deployment prefix, '' or '/prefix' (no trailing slash)
     * @param array<string> $segments Path segments to join
     *
     * @return string Assembled URL path with leading slash
     */
    public static function deployed(string $basePath, array $segments): string
    {
        if ($segments === []) {
            return $basePath === '' ? '/' : $basePath;
        }

        return $basePath . '/' . implode('/', $segments);
    }

    /**
     * Get the first segment of a path.
     *
     * @param string $path URL path
     *
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
     *
     * @return string Remaining path after removing the first segment
     */
    public static function shift(string $path): string
    {
        $segments = self::segments($path);
        array_shift($segments);

        return self::build($segments);
    }
}
