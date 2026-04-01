<?php

declare(strict_types=1);

/**
 * Pattern Registry
 *
 * Named regex patterns for route segment matching.
 * Built-in types: int, string, slug, uuid4, mongo_id, locale, etc.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Compiler;

use InvalidArgumentException;

final class PatternRegistry
{
    /** @var array<string, string> */
    private array $patterns = [
        'int'      => '[0-9]+',
        'string'   => '[a-zA-Z]+',
        'alpha'    => '[a-zA-Z]+',
        'any'      => '[a-zA-Z0-9]+',
        'slug'     => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'uuid'     => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'uuid4'    => '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
        'mongo_id' => '[a-f0-9]{24}',
        'locale'   => '[a-z]{2}(?:-[A-Z]{2})?',
    ];

    /**
     * Get the regex for a named pattern type.
     *
     * @param string $name Pattern type name
     * @return string|null Regex string or null if not found
     */
    public function get(string $name): string|null
    {
        return $this->patterns[$name] ?? null;
    }

    /**
     * Register a custom pattern type.
     *
     * @param string $name Pattern type name
     * @param string $regex Regex pattern (must not contain unsafe constructs)
     * @throws InvalidArgumentException If the regex is invalid or uses unsafe constructs
     */
    public function add(string $name, string $regex): void
    {
        $this->validateRegex($regex);
        $this->patterns[$name] = $regex;
    }

    /**
     * Check if a named pattern type exists.
     *
     * @param string $name Pattern type name
     * @return bool True if the pattern type is registered
     */
    public function has(string $name): bool
    {
        return isset($this->patterns[$name]);
    }

    /**
     * Parse a dynamic segment like {id:int}, {name}, {id?}, {id:int?}, {path:*}.
     *
     * Returns null for static (non-parameter) segments.
     *
     * @param string $segment URL segment to parse
     * @param array<string, string> $where Route-level constraint overrides
     * @return array{name: string, type: string, regex: string, optional: bool, wildcard: bool}|null
     */
    public function parseSegment(string $segment, array $where = []): array|null
    {
        if (preg_match('/^\{(?P<n>[a-zA-Z_]\w*)(?::(?P<type>[a-zA-Z_*][a-zA-Z0-9_*]*))?(?P<opt>\?)?\}$/', $segment, $m) !== 1) {
            return null;
        }

        $name = $m['n'];
        $type = $m['type'] ?? '';
        $optional = ($m['opt'] ?? '') === '?';
        $wildcard = $type === '*';

        if (isset($where[$name])) {
            $type = $where[$name];
        }

        if ($wildcard) {
            return [
                'name' => $name,
                'type' => '*',
                'regex' => '.+',
                'optional' => false,
                'wildcard' => true,
            ];
        }

        $regex = '.+';
        if ($type !== '' && $this->has($type)) {
            $regex = (string) $this->get($type);
        }

        return [
            'name' => $name,
            'type' => $type,
            'regex' => $regex,
            'optional' => $optional,
            'wildcard' => false,
        ];
    }

    /**
     * Validate a regex pattern is safe to use in route matching.
     *
     * @param string $regex Pattern to validate
     * @throws InvalidArgumentException If the regex is invalid or uses unsafe constructs
     */
    private function validateRegex(string $regex): void
    {
        if (preg_match('/\(\?[<=!]|\(\?P[<=]|\(\?\(|\(\?R\)|\(\?\d|\\\\\d/', $regex) === 1) {
            throw new InvalidArgumentException("Pattern contains unsafe regex constructs: '{$regex}'");
        }

        if (@preg_match('/^' . $regex . '$/', '') === false) {
            throw new InvalidArgumentException("Invalid regex pattern: '{$regex}'");
        }
    }
}
