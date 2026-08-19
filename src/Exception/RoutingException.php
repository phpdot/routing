<?php

declare(strict_types=1);

/**
 * Base exception for the routing package.
 *
 * Every failure the router raises — duplicate names or scopes, malformed
 * patterns, unresolvable handlers, URL generation against unknown routes —
 * extends this type, so a consumer can catch the package with one clause.
 * Extends RuntimeException, keeping pre-hierarchy catch sites working.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Exception;

use RuntimeException;

class RoutingException extends RuntimeException {}
