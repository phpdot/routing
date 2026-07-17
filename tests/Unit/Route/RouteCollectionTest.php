<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit\Route;

use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouteCollectionTest extends TestCase
{
    #[Test]
    public function startsEmpty(): void
    {
        $collection = new RouteCollection();

        self::assertTrue($collection->isEmpty());
        self::assertSame(0, $collection->count());
        self::assertSame([], $collection->all());
    }

    #[Test]
    public function addsAndCountsRoutes(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $collection->add(new Route(['POST'], 'users', ['users'], fn() => null));

        self::assertFalse($collection->isEmpty());
        self::assertSame(2, $collection->count());
    }

    #[Test]
    public function findsByName(): void
    {
        $collection = new RouteCollection();

        $route = new Route(['GET'], 'users', ['users'], fn() => null);
        $route->name('users.index');
        $collection->add($route);

        self::assertSame($route, $collection->findByName('users.index'));
        self::assertNull($collection->findByName('nonexistent'));
    }

    #[Test]
    public function getExposedReturnsOnlyExposed(): void
    {
        $collection = new RouteCollection();

        $exposed = new Route(['GET'], 'public', ['public'], fn() => null);
        $exposed->expose();

        $hidden = new Route(['GET'], 'private', ['private'], fn() => null);

        $collection->add($exposed);
        $collection->add($hidden);

        $result = $collection->getExposed();
        self::assertCount(1, $result);
        self::assertSame($exposed, $result[0]);
    }
}
