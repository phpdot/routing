<?php

declare(strict_types=1);

/**
 * Optional mount ordering for a route provider; absent means priority 0.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Routes
{
    /**
     * @param int $priority Higher mounts first; ties break on class name.
     */
    public function __construct(public int $priority = 0) {}
}
