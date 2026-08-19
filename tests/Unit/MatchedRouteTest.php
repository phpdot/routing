<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit;

use PHPdot\Routing\MatchedRoute;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Router;
use PHPdot\Routing\Tests\Stubs\RequestFactory;
use PHPdot\Routing\Utils\Path;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MatchedRouteTest extends TestCase
{
    private function route(): Route
    {
        $pattern = 'users/{id}';

        return (new Route(['GET'], $pattern, Path::segments($pattern), static fn() => null))
            ->name('users.show');
    }

    #[Test]
    public function returnsNullWhenNoRouteIsAttached(): void
    {
        $request = RequestFactory::create('GET', '/users/42');

        $this->assertNull(MatchedRoute::from($request));
    }

    #[Test]
    public function readsRouteAndParametersFromTheRequest(): void
    {
        $route = $this->route();
        $params = ['id' => 42];

        $request = RequestFactory::create('GET', '/users/42')
            ->withAttribute(Router::ROUTE_ATTRIBUTE, $route)
            ->withAttribute(Router::ROUTE_PARAMS_ATTRIBUTE, $params);

        $matched = MatchedRoute::from($request);

        $this->assertNotNull($matched);
        $this->assertSame($route, $matched->route);
        $this->assertSame($params, $matched->parameters);
    }

    #[Test]
    public function defaultsParametersToEmptyArrayWhenAbsent(): void
    {
        $route = $this->route();

        $request = RequestFactory::create('GET', '/users/42')
            ->withAttribute(Router::ROUTE_ATTRIBUTE, $route);

        $matched = MatchedRoute::from($request);

        $this->assertNotNull($matched);
        $this->assertSame([], $matched->parameters);
    }

    #[Test]
    public function returnsNullWhenAttributeHoldsSomethingOtherThanARoute(): void
    {
        $request = RequestFactory::create('GET', '/users/42')
            ->withAttribute(Router::ROUTE_ATTRIBUTE, 'not-a-route');

        $this->assertNull(MatchedRoute::from($request));
    }

    #[Test]
    public function convenienceGettersDelegateToTheRoute(): void
    {
        $route = $this->route();

        $request = RequestFactory::create('GET', '/users/42')
            ->withAttribute(Router::ROUTE_ATTRIBUTE, $route)
            ->withAttribute(Router::ROUTE_PARAMS_ATTRIBUTE, ['id' => 42]);

        $matched = MatchedRoute::from($request);

        $this->assertNotNull($matched);
        $this->assertSame('users.show', $matched->getName());
        $this->assertSame('users/{id}', $matched->getPattern());
        $this->assertSame(['GET'], $matched->getMethods());
    }
}
