<?php

declare(strict_types=1);

/**
 * One block of the URL surface, declared with the full Router API.
 *
 * Implementations are discovered by interface and resolved from the container.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Contract;

use PHPdot\Routing\Router;

interface RouteProviderInterface
{
    public function routes(Router $router): void;
}
