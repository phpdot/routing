<?php

declare(strict_types=1);

/**
 * A duplicate name is unreachable through url() and invisible to both declaring sites.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Tests\Unit;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Routing\Exception\RoutingException;
use PHPdot\Routing\Router;
use PHPdot\Routing\Tests\Stubs\StubContainer;
use PHPdot\Routing\Verifier\UniqueRouteNames;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UniqueRouteNamesTest extends TestCase
{
    #[Test]
    public function itAcceptsASurfaceWhoseNamesAreUnique(): void
    {
        $router = $this->router();
        $router->addRoute('GET', '/a', static fn(): string => 'a')->name('a');
        $router->addRoute('GET', '/b', static fn(): string => 'b')->name('b');

        new UniqueRouteNames()->verify($router);

        self::assertCount(2, $router->list());
    }

    #[Test]
    public function itIgnoresUnnamedRoutes(): void
    {
        $router = $this->router();
        $router->addRoute('GET', '/a', static fn(): string => 'a');
        $router->addRoute('GET', '/b', static fn(): string => 'b');

        new UniqueRouteNames()->verify($router);

        self::assertCount(2, $router->list());
    }

    #[Test]
    public function itRefusesADuplicateNameAndNamesIt(): void
    {
        $router = $this->router();
        $router->addRoute('GET', '/a', static fn(): string => 'a')->name('shared');
        $router->addRoute('GET', '/b', static fn(): string => 'b')->name('shared');

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessageMatches('/Declared more than once: shared/');

        new UniqueRouteNames()->verify($router);
    }

    private function router(): Router
    {
        return new Router(new StubContainer(), new ResponseFactory());
    }
}
