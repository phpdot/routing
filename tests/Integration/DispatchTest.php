<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPdot\Routing\Route\RouteGroup;
use PHPdot\Routing\Router;
use PHPdot\Routing\Tests\Stubs\StubContainer;
use PHPdot\Routing\Tests\Stubs\StubController;
use PHPdot\Routing\Tests\Stubs\StubMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DispatchTest extends TestCase
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

    // ── Closure handlers ──

    #[Test]
    public function dispatchesClosureHandler(): void
    {
        $this->router->get('/hello', fn(ServerRequestInterface $req): ResponseInterface => new Response(200, [], 'hello'));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/hello'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());
    }

    #[Test]
    public function closureReceivesRouteParams(): void
    {
        $this->router->get('/users/{id:int}', fn(ServerRequestInterface $req, int $id): ResponseInterface => new Response(200, [], "user:{$id}"));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/users/42'));

        self::assertSame('user:42', (string) $response->getBody());
    }

    // ── Controller handlers ──

    #[Test]
    public function dispatchesControllerAction(): void
    {
        $this->router->get('/users', [StubController::class, 'index']);
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/users'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('index', (string) $response->getBody());
    }

    #[Test]
    public function controllerReceivesRouteParams(): void
    {
        $this->router->get('/users/{id:int}', [StubController::class, 'show']);
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/users/7'));

        self::assertSame('show:7', (string) $response->getBody());
    }

    // ── 404 / 405 ──

    #[Test]
    public function returns404ForUnknownRoute(): void
    {
        $this->router->get('/users', fn() => new Response(200));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/nope'));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function returns405WithAllowHeader(): void
    {
        $this->router->get('/users', fn() => new Response(200));
        $this->router->post('/users', fn() => new Response(201));
        $this->router->compile();

        $response = $this->router->handle($this->request('DELETE', '/users'));

        self::assertSame(405, $response->getStatusCode());
        $allow = $response->getHeaderLine('Allow');
        self::assertStringContainsString('GET', $allow);
        self::assertStringContainsString('POST', $allow);
    }

    // ── Fallback ──

    #[Test]
    public function fallbackHandlerCalled(): void
    {
        $this->router->get('/users', fn() => new Response(200));
        $this->router->fallback(fn(ServerRequestInterface $req): ResponseInterface => new Response(404, [], 'custom 404'));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/nope'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('custom 404', (string) $response->getBody());
    }

    // ── Middleware ──

    #[Test]
    public function middlewareIsApplied(): void
    {
        $this->router->get('/users', fn(ServerRequestInterface $req): ResponseInterface => new Response(200, [], 'ok'));
        $this->router->middleware(StubMiddleware::class);
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/users'));

        self::assertSame('applied', $response->getHeaderLine('X-Middleware'));
        self::assertSame('ok', (string) $response->getBody());
    }

    #[Test]
    public function closureMiddlewareIsApplied(): void
    {
        $this->router->get('/users', fn(ServerRequestInterface $req): ResponseInterface => new Response(200, [], 'ok'));
        $this->router->middleware(function (ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): ResponseInterface {
            $response = $handler->handle($request);
            return $response->withHeader('X-Custom', 'yes');
        });
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/users'));

        self::assertSame('yes', $response->getHeaderLine('X-Custom'));
    }

    // ── Groups ──

    #[Test]
    public function groupPrefixIsApplied(): void
    {
        $this->router->group('/api/v1', function (RouteGroup $group): void {
            $group->get('/users', fn(ServerRequestInterface $req): ResponseInterface => new Response(200, [], 'api-users'));
        });
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/api/v1/users'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('api-users', (string) $response->getBody());
    }

    #[Test]
    public function nestedGroupsWork(): void
    {
        $this->router->group('/api', function (RouteGroup $api): void {
            $api->group('/v1', function (RouteGroup $v1): void {
                $v1->get('/health', fn(ServerRequestInterface $req): ResponseInterface => new Response(200, [], 'ok'));
            });
        });
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/api/v1/health'));

        self::assertSame('ok', (string) $response->getBody());
    }

    // ── HEAD ──

    #[Test]
    public function headReturnsEmptyBody(): void
    {
        $this->router->get('/users', fn(ServerRequestInterface $req): ResponseInterface => new Response(200, [], 'body'));
        $this->router->compile();

        $response = $this->router->handle($this->request('HEAD', '/users'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    // ── Request attributes ──

    #[Test]
    public function routeParamsInjectedAsRequestAttributes(): void
    {
        $captured = null;
        $this->router->get('/users/{id:int}', function (ServerRequestInterface $req, int $id) use (&$captured): ResponseInterface {
            $captured = $req->getAttribute('id');
            return new Response(200);
        });
        $this->router->compile();

        $this->router->handle($this->request('GET', '/users/42'));

        self::assertSame(42, $captured);
    }

    // ── Optional + Wildcard ──

    #[Test]
    public function optionalParamDispatch(): void
    {
        $this->router->get('/posts/{page:int?}', fn(ServerRequestInterface $req, int $page = 1): ResponseInterface => new Response(200, [], "page:{$page}"));
        $this->router->compile();

        $withParam = $this->router->handle($this->request('GET', '/posts/3'));
        self::assertSame('page:3', (string) $withParam->getBody());

        $withoutParam = $this->router->handle($this->request('GET', '/posts'));
        self::assertSame('page:1', (string) $withoutParam->getBody());
    }

    #[Test]
    public function wildcardDispatch(): void
    {
        $this->router->get('/docs/{path:*}', fn(ServerRequestInterface $req, string $path): ResponseInterface => new Response(200, [], "path:{$path}"));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/docs/guide/install'));

        self::assertSame('path:guide/install', (string) $response->getBody());
    }
}
