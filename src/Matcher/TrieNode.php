<?php

declare(strict_types=1);

/**
 * Trie Node
 *
 * Single node in the compiled segment trie.
 * Static children use hash lookup, dynamic children use regex matching.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Matcher;

use PHPdot\Routing\Route\Route;

final class TrieNode
{
    /** @var array<string, TrieNode> Static segment children (hash lookup) */
    public array $staticChildren = [];

    /** @var array<array{name: string, pattern: string|null, regex: string, node: TrieNode}> */
    public array $dynamicChildren = [];

    /** @var array{name: string, route_methods: array<string, Route>}|null */
    public array|null $wildcard = null;

    /** @var array<string, Route> Method -> Route for routes terminating here */
    public array $leaves = [];

    /** @var array<string> All methods across leaves (for 405 detection) */
    public array $allowedMethods = [];
}
