<?php

declare(strict_types=1);

/**
 * An assertion over the assembled surface, run after mounting and before compilation.
 *
 * Verifiers see every provider's routes together; a provider sees only its own.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Contract;

use PHPdot\Routing\Exception\RoutingException;
use PHPdot\Routing\Router;

interface MountVerifierInterface
{
    /**
     * @throws RoutingException When the assembled surface is invalid.
     */
    public function verify(Router $router): void;
}
