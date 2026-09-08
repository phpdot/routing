<?php

declare(strict_types=1);

/**
 * Last by class name and first by priority, so an ordering assertion cannot pass by accident.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Tests\Stubs\Providers;

use PHPdot\Routing\Attribute\Routes;
use PHPdot\Routing\Contract\RouteProviderInterface;
use PHPdot\Routing\Router;

#[Routes(priority: 100)]
final class ZuluRoutes implements RouteProviderInterface
{
    public function routes(Router $router): void
    {
        $router->addRoute('GET', '/zulu', static fn(): string => 'zulu')->name('zulu');
    }
}
