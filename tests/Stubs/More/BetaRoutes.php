<?php

declare(strict_types=1);

/**
 * A second directory of providers, for asserting that two mounts both survive.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Tests\Stubs\More;

use PHPdot\Routing\Contract\RouteProviderInterface;
use PHPdot\Routing\Router;

final class BetaRoutes implements RouteProviderInterface
{
    public function routes(Router $router): void
    {
        $router->addRoute('GET', '/beta', static fn(): string => 'beta')->name('beta');
    }
}
