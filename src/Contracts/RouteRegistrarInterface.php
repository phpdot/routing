<?php

declare(strict_types=1);

/**
 * Route Registrar Interface
 *
 * Contract for classes that register routes on the router.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Contracts;

use PHPdot\Routing\Router;

interface RouteRegistrarInterface
{
    /**
     * Register routes on the router.
     *
     * @param Router $router Router instance to register routes on
     */
    public function register(Router $router): void;
}
