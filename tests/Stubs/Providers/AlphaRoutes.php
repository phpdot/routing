<?php

declare(strict_types=1);

/**
 * A provider with no priority attribute, so it orders by class name.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Tests\Stubs\Providers;

use PHPdot\Routing\Contract\RouteProviderInterface;
use PHPdot\Routing\Router;

final class AlphaRoutes implements RouteProviderInterface
{
    public function routes(Router $router): void
    {
        $router->addRoute('GET', '/alpha', static fn(): string => 'alpha')->name('alpha');
    }
}
