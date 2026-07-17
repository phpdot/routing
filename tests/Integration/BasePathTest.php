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
}
