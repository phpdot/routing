<?php

declare(strict_types=1);

/**
 * Re-declares AlphaRoutes' name on a different pattern, which only a verifier can see.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Tests\Stubs\Providers;

use PHPdot\Routing\Contract\RouteProviderInterface;
use PHPdot\Routing\Router;

final class DuplicateNameRoutes implements RouteProviderInterface
{
    public function routes(Router $router): void
    {
        $router->addRoute('GET', '/duplicate', static fn(): string => 'dup')->name('alpha');
    }
}
