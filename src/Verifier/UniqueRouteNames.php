<?php

declare(strict_types=1);

/**
 * Refuses the boot when two routes share a name.
 *
 * Route names are not checked elsewhere; findByName() returns the first match.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Verifier;

use PHPdot\Routing\Contract\MountVerifierInterface;
use PHPdot\Routing\Exception\RoutingException;
use PHPdot\Routing\Router;

final class UniqueRouteNames implements MountVerifierInterface
{
    /**
     * @throws RoutingException When a route name is declared more than once.
     */
    public function verify(Router $router): void
    {
        $seen = [];
        $duplicates = [];

        foreach ($router->list() as $route) {
            $name = $route['name'];

            if ($name === null) {
                continue;
            }

            if (isset($seen[$name])) {
                $duplicates[$name] = true;
            }

            $seen[$name] = true;
        }

        if ($duplicates !== []) {
            throw new RoutingException(
                'Route names must be unique. Declared more than once: '
                . implode(', ', array_keys($duplicates)),
            );
        }
    }
}
