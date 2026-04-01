<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPdot\Routing\Contracts\ControllerInterface;
use PHPdot\Routing\Route\RouteGroup;
use PHPdot\Routing\Route\RouteScope;
use PHPdot\Routing\Router;
use PHPdot\Routing\Tests\Stubs\StubContainer;
use PHPdot\Routing\Tests\Stubs\StubController;
use PHPdot\Routing\Tests\Stubs\StubMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class EdgeCaseTest extends TestCase
{
    private Router $router;
    private StubContainer $container;

    protected function setUp(): void
    {
        $this->container = new StubContainer();
        $factory = new Psr17Factory();
        $this->router = new Router($this->container, $factory);
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return new ServerRequest($method, $path, ['Host' => 'localhost']);
    }

    // ── Trailing slashes ──

    #[Test]
    public function trailingSlashIsNormalized(): void
    {
        $this->router->get('/users', fn() => new Response(200, [], 'ok'));
        $this->router->compile();

        // Trailing slash is stripped — /users/ matches /users
        $response = $this->router->handle($this->request('GET', '/users/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', (string) $response->getBody());
    }

    #[Test]
    public function rootPathMatches(): void
    {
        $this->router->get('/', fn() => new Response(200, [], 'root'));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('root', (string) $response->getBody());
    }

    // ── String handler "Class@method" ──

    #[Test]
    public function stringHandlerWorks(): void
    {
        $this->router->get('/users', StubController::class . '@index');
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/users'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('index', (string) $response->getBody());
    }

    #[Test]
    public function stringHandlerWithoutAtThrows(): void
    {
        $this->router->get('/users', 'SomeClass');
        $this->router->compile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("'Class@method'");

        $this->router->handle($this->request('GET', '/users'));
    }

    // ── Controller without ControllerInterface ──

    #[Test]
    public function controllerWithoutInterfaceThrows(): void
    {
        $this->container->set('FakeController', new class {
            public function index(): void {}
        });

        $this->router->get('/test', ['FakeController', 'index']);
        $this->router->compile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ControllerInterface');

        $this->router->handle($this->request('GET', '/test'));
    }

    // ── Controller with missing method ──

    #[Test]
    public function controllerMissingMethodThrows(): void
    {
        $this->router->get('/test', [StubController::class, 'nonexistent']);
        $this->router->compile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $this->router->handle($this->request('GET', '/test'));
    }

    // ── Auto-compile ──

    #[Test]
    public function autoCompilesOnFirstDispatch(): void
    {
        $this->router->get('/test', fn() => new Response(200, [], 'auto'));

        // No compile() call — should auto-compile
        $response = $this->router->handle($this->request('GET', '/test'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('auto', (string) $response->getBody());
    }

    // ── Untyped {param} matches anything ──

    #[Test]
    public function untypedParamMatchesAnything(): void
    {
        $this->router->get('/files/{name}', fn(ServerRequestInterface $req, string $name) => new Response(200, [], "file:{$name}"));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/files/my-doc.pdf'));

        self::assertSame('file:my-doc.pdf', (string) $response->getBody());
    }

    // ── Custom pattern via addPattern ──

    #[Test]
    public function customPatternWorks(): void
    {
        $this->router->addPattern('hex8', '[a-f0-9]{8}');
        $this->router->get('/codes/{code:hex8}', fn(ServerRequestInterface $req, string $code) => new Response(200, [], "code:{$code}"));
        $this->router->compile();

        $match = $this->router->handle($this->request('GET', '/codes/deadbeef'));
        self::assertSame('code:deadbeef', (string) $match->getBody());

        $noMatch = $this->router->handle($this->request('GET', '/codes/ZZZZZZZZ'));
        self::assertSame(404, $noMatch->getStatusCode());
    }

    // ── where() override on route ──

    #[Test]
    public function whereOverrideConstrainsParam(): void
    {
        $route = $this->router->get('/items/{id}', fn(ServerRequestInterface $req, int $id) => new Response(200, [], "item:{$id}"));
        $route->where('id', 'int');
        $this->router->compile();

        $match = $this->router->handle($this->request('GET', '/items/42'));
        self::assertSame('item:42', (string) $match->getBody());

        $noMatch = $this->router->handle($this->request('GET', '/items/abc'));
        self::assertSame(404, $noMatch->getStatusCode());
    }

    // ── Middleware ordering ──

    #[Test]
    public function multipleMiddlewareRunInOrder(): void
    {
        $order = [];

        $mw1 = function (ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$order): ResponseInterface {
            $order[] = 'before1';
            $res = $handler->handle($req);
            $order[] = 'after1';
            return $res;
        };

        $mw2 = function (ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$order): ResponseInterface {
            $order[] = 'before2';
            $res = $handler->handle($req);
            $order[] = 'after2';
            return $res;
        };

        $this->router->middleware($mw1);
        $this->router->middleware($mw2);
        $this->router->get('/test', function () use (&$order): ResponseInterface {
            $order[] = 'handler';
            return new Response(200);
        });
        $this->router->compile();

        $this->router->handle($this->request('GET', '/test'));

        self::assertSame(['before1', 'before2', 'handler', 'after2', 'after1'], $order);
    }

    // ── Middleware short-circuit ──

    #[Test]
    public function middlewareCanShortCircuit(): void
    {
        $handlerCalled = false;

        $this->router->middleware(function (ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface {
            return new Response(403, [], 'blocked');
        });
        $this->router->get('/test', function () use (&$handlerCalled): ResponseInterface {
            $handlerCalled = true;
            return new Response(200);
        });
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/test'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('blocked', (string) $response->getBody());
        self::assertFalse($handlerCalled);
    }

    // ── Group middleware applied to routes ──

    #[Test]
    public function groupMiddlewareAppliedToRoutes(): void
    {
        $this->router->group('/api', function (RouteGroup $group): void {
            $group->get('/data', fn() => new Response(200, [], 'data'));
        })->middleware(StubMiddleware::class);
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/api/data'));

        self::assertSame('applied', $response->getHeaderLine('X-Middleware'));
    }

    // ── Overlapping dynamic routes with different types ──

    #[Test]
    public function intRouteRejectsSlugAndFallsThrough(): void
    {
        $this->router->get('/items/{id:int}', fn(ServerRequestInterface $req, int $id) => new Response(200, [], "int:{$id}"));
        $this->router->get('/items/{slug:slug}', fn(ServerRequestInterface $req, string $slug) => new Response(200, [], "slug:{$slug}"));
        $this->router->compile();

        $intMatch = $this->router->handle($this->request('GET', '/items/42'));
        self::assertSame('int:42', (string) $intMatch->getBody());

        $slugMatch = $this->router->handle($this->request('GET', '/items/hello-world'));
        self::assertSame('slug:hello-world', (string) $slugMatch->getBody());
    }

    // ── Scopes ──

    #[Test]
    public function scopeAppliesMiddlewareAndHosts(): void
    {
        $scope = new RouteScope('api');
        $scope->middleware(StubMiddleware::class);
        $this->router->addScope($scope);

        $route = $this->router->get('/data', fn() => new Response(200, [], 'scoped'));
        $route->scope($this->router->getScope('api'));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/data'));

        self::assertSame('applied', $response->getHeaderLine('X-Middleware'));
    }

    #[Test]
    public function duplicateScopeThrows(): void
    {
        $this->router->addScope(new RouteScope('api'));

        $this->expectException(RuntimeException::class);
        $this->router->addScope(new RouteScope('api'));
    }

    #[Test]
    public function unknownScopeThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->router->getScope('nonexistent');
    }

    // ── Route naming ──

    #[Test]
    public function namedRoutesAccessible(): void
    {
        $this->router->get('/users/{id:int}', fn() => new Response(200))->name('users.show');
        $this->router->compile();

        $route = $this->router->getRoutes()->findByName('users.show');
        self::assertNotNull($route);
        self::assertSame('users/{id:int}', $route->getPattern());
    }

    // ── Exposed routes ──

    #[Test]
    public function exposedRoutesListed(): void
    {
        $this->router->get('/public', fn() => new Response(200))->name('public.index')->expose();
        $this->router->get('/private', fn() => new Response(200))->name('private.index');
        $this->router->compile();

        $exposed = $this->router->exposed();

        self::assertArrayHasKey('public.index', $exposed);
        self::assertArrayNotHasKey('private.index', $exposed);
    }

    // ── Introspection list() ──

    #[Test]
    public function listReturnsAllRoutes(): void
    {
        $this->router->get('/a', fn() => new Response(200))->name('a');
        $this->router->post('/b', [StubController::class, 'store'])->name('b');
        $this->router->compile();

        $list = $this->router->list();

        self::assertCount(2, $list);
        self::assertSame('Closure', $list[0]['handler']);
        self::assertSame(StubController::class . '@store', $list[1]['handler']);
    }

    // ── Same path, different methods ──

    #[Test]
    public function samePathDifferentMethodsDispatchCorrectly(): void
    {
        $this->router->get('/resource', fn() => new Response(200, [], 'get'));
        $this->router->post('/resource', fn() => new Response(201, [], 'post'));
        $this->router->put('/resource', fn() => new Response(200, [], 'put'));
        $this->router->delete('/resource', fn() => new Response(204, [], 'delete'));
        $this->router->compile();

        self::assertSame('get', (string) $this->router->handle($this->request('GET', '/resource'))->getBody());
        self::assertSame('post', (string) $this->router->handle($this->request('POST', '/resource'))->getBody());
        self::assertSame('put', (string) $this->router->handle($this->request('PUT', '/resource'))->getBody());
        self::assertSame('delete', (string) $this->router->handle($this->request('DELETE', '/resource'))->getBody());
    }

    // ── Wildcard does not match zero segments ──

    #[Test]
    public function wildcardRequiresAtLeastOneSegment(): void
    {
        $this->router->get('/docs/{path:*}', fn(ServerRequestInterface $req, string $path) => new Response(200, [], $path));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/docs'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ── Multiple groups at same level ──

    #[Test]
    public function multipleGroupsSameLevel(): void
    {
        $this->router->group('/api', function (RouteGroup $group): void {
            $group->get('/users', fn() => new Response(200, [], 'api-users'));
        });
        $this->router->group('/admin', function (RouteGroup $group): void {
            $group->get('/users', fn() => new Response(200, [], 'admin-users'));
        });
        $this->router->compile();

        self::assertSame('api-users', (string) $this->router->handle($this->request('GET', '/api/users'))->getBody());
        self::assertSame('admin-users', (string) $this->router->handle($this->request('GET', '/admin/users'))->getBody());
    }

    // ── Path utility edge cases moved to PathTest ──
}
