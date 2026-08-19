<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Integration;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Http\Message\Response;
use PHPdot\Http\Message\ServerRequest;
use PHPdot\Routing\Router;
use PHPdot\Routing\Tests\Stubs\StubContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BasePathTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new StubContainer(), new ResponseFactory());
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return new ServerRequest($method, $path, ['Host' => 'localhost']);
    }

    // ── Default behaviour (no base path) ──

    #[Test]
    public function defaultBasePathIsEmpty(): void
    {
        self::assertSame('', $this->router->getBasePath());
    }

    #[Test]
    public function withoutBasePathRoutesMatchUnchanged(): void
    {
        $this->router->get('/hello', fn(): ResponseInterface => new Response(200, [], 'hello'));

        $response = $this->router->handle($this->request('GET', '/hello'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', (string) $response->getBody());
    }

    // ── Base path stripping ──

    #[Test]
    public function basePathIsStrippedFromRequestPath(): void
    {
        $this->router->setBasePath('/site/admin');
        $this->router->get('/hello', fn(): ResponseInterface => new Response(200, [], 'matched'));

        $response = $this->router->handle($this->request('GET', '/site/admin/hello'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('matched', (string) $response->getBody());
    }

    #[Test]
    public function basePathStrippingPreservesRouteParams(): void
    {
        $this->router->setBasePath('/site/admin');
        $this->router->get('/users/{id:int}', fn(ServerRequestInterface $req, int $id): ResponseInterface => new Response(200, [], "user:{$id}"));

        $response = $this->router->handle($this->request('GET', '/site/admin/users/42'));

        self::assertSame('user:42', (string) $response->getBody());
    }

    #[Test]
    public function basePathExactMatchResolvesToRoot(): void
    {
        $this->router->setBasePath('/site/admin');
        $this->router->get('/', fn(): ResponseInterface => new Response(200, [], 'root'));

        $response = $this->router->handle($this->request('GET', '/site/admin'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('root', (string) $response->getBody());
    }

    #[Test]
    public function basePathWithTrailingSlashResolvesToRoot(): void
    {
        $this->router->setBasePath('/site/admin');
        $this->router->get('/', fn(): ResponseInterface => new Response(200, [], 'root'));

        $response = $this->router->handle($this->request('GET', '/site/admin/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('root', (string) $response->getBody());
    }

    #[Test]
    public function pathOutsideBasePathReturns404(): void
    {
        $this->router->setBasePath('/site/admin');
        $this->router->get('/hello', fn(): ResponseInterface => new Response(200, [], 'hi'));

        $response = $this->router->handle($this->request('GET', '/different/hello'));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function rootPathOutsideBasePathReturns404Strict(): void
    {
        $this->router->setBasePath('/site/admin');
        $this->router->get('/', fn(): ResponseInterface => new Response(200, [], 'root'));

        $response = $this->router->handle($this->request('GET', '/'));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function basePathRequiresSegmentBoundary(): void
    {
        $this->router->setBasePath('/site/admin');
        $this->router->get('/ing', fn(): ResponseInterface => new Response(200, [], 'wrong'));

        $response = $this->router->handle($this->request('GET', '/site/administrators'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ── setBasePath normalisation ──

    #[Test]
    public function setBasePathNormalisesLeadingAndTrailingSlashes(): void
    {
        $this->router->setBasePath('site/admin');
        self::assertSame('/site/admin', $this->router->getBasePath());

        $this->router->setBasePath('/site/admin');
        self::assertSame('/site/admin', $this->router->getBasePath());

        $this->router->setBasePath('/site/admin/');
        self::assertSame('/site/admin', $this->router->getBasePath());

        $this->router->setBasePath('//site/admin//');
        self::assertSame('/site/admin', $this->router->getBasePath());
    }

    #[Test]
    public function setBasePathEmptyStringDisablesStripping(): void
    {
        $this->router->setBasePath('');

        self::assertSame('', $this->router->getBasePath());
    }

    #[Test]
    public function setBasePathWithOnlySlashesNormalisesToEmpty(): void
    {
        $this->router->setBasePath('///');

        self::assertSame('', $this->router->getBasePath());
    }

    #[Test]
    public function setBasePathReturnsSelfForChaining(): void
    {
        $result = $this->router->setBasePath('/site/admin');

        self::assertSame($this->router, $result);
    }

    // ── Multi-segment base path ──

    #[Test]
    public function multiSegmentBasePathWorks(): void
    {
        $this->router->setBasePath('/api/v1');
        $this->router->get('/users', fn(): ResponseInterface => new Response(200, [], 'list'));

        $response = $this->router->handle($this->request('GET', '/api/v1/users'));

        self::assertSame('list', (string) $response->getBody());
    }

    // ── Generation (a generated URL must reach the route it names) ──

    #[Test]
    public function generatedUrlIncludesTheBasePath(): void
    {
        $this->router->setBasePath('/app');
        $this->router->get('/users', fn(): ResponseInterface => new Response(200))->name('users');

        self::assertSame('/app/users', $this->router->url('users'));
    }

    #[Test]
    public function generatedUrlWithParametersIncludesTheBasePath(): void
    {
        $this->router->setBasePath('/api/v1');
        $this->router->get('/users/{id:int}', fn(): ResponseInterface => new Response(200))->name('users.show');

        self::assertSame('/api/v1/users/7', $this->router->url('users.show', ['id' => 7]));
        self::assertSame('/api/v1/users/7?page=2', $this->router->url('users.show', ['id' => 7], ['page' => 2]));
    }

    #[Test]
    public function generatedRootUrlIsTheBasePathWithoutTrailingSlash(): void
    {
        $this->router->setBasePath('/app');
        $this->router->get('/', fn(): ResponseInterface => new Response(200))->name('home');

        self::assertSame('/app', $this->router->url('home'));
    }

    #[Test]
    public function generatedUrlRoundTripsBackToItsRoute(): void
    {
        $this->router->setBasePath('/app');
        $this->router->get('/users/{id:int}', fn(): ResponseInterface => new Response(200, [], 'ok'))->name('users.show');
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', $this->router->url('users.show', ['id' => 7])));

        self::assertSame('ok', (string) $response->getBody());
    }

    #[Test]
    public function generatedUrlIsUnchangedWithoutABasePath(): void
    {
        $this->router->get('/users', fn(): ResponseInterface => new Response(200))->name('users');

        self::assertSame('/users', $this->router->url('users'));
    }

    // ── Exposed map (a client must build the same strings the server does) ──

    #[Test]
    public function exposedMapMatchesGeneratedUrls(): void
    {
        $this->router->get('/users', fn(): ResponseInterface => new Response(200))->name('users')->expose();
        $this->router->group('sdp', static function ($group): void {
            $group->get('', fn(): ResponseInterface => new Response(200))->name('sdp.index')->expose();
        });

        $exposed = $this->router->exposed();

        self::assertSame($this->router->url('users'), $exposed['users']);
        self::assertSame($this->router->url('sdp.index'), $exposed['sdp.index']);
        self::assertSame('/sdp', $exposed['sdp.index']);
    }

    #[Test]
    public function exposedMapIncludesTheBasePath(): void
    {
        $this->router->setBasePath('/app');
        $this->router->get('/users/{id:int}', fn(): ResponseInterface => new Response(200))->name('users.show')->expose();

        self::assertSame(['users.show' => '/app/users/{id:int}'], $this->router->exposed());
    }
}
