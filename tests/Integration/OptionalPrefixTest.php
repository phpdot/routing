<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPdot\Routing\Router;
use PHPdot\Routing\Tests\Stubs\StubContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OptionalPrefixTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $container = new StubContainer();
        $factory = new Psr17Factory();
        $this->router = new Router($container, $factory);
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return new ServerRequest($method, $path, ['Host' => 'localhost']);
    }

    // ── Locale prefix ──

    #[Test]
    public function matchesWithLocalePrefix(): void
    {
        $this->router->get('/{lang:locale?}/users', fn(ServerRequestInterface $req, string $lang = 'en') => new Response(200, [], "lang:{$lang}"));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/en/users'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('lang:en', (string) $response->getBody());
    }

    #[Test]
    public function matchesWithArabicLocalePrefix(): void
    {
        $this->router->get('/{lang:locale?}/users', fn(ServerRequestInterface $req, string $lang = 'en') => new Response(200, [], "lang:{$lang}"));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/ar/users'));

        self::assertSame('lang:ar', (string) $response->getBody());
    }

    #[Test]
    public function matchesWithoutLocalePrefix(): void
    {
        $this->router->get('/{lang:locale?}/users', fn(ServerRequestInterface $req, string $lang = 'en') => new Response(200, [], "lang:{$lang}"));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/users'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('lang:en', (string) $response->getBody());
    }

    #[Test]
    public function localeRejectsInvalidPrefix(): void
    {
        $this->router->get('/{lang:locale?}/users', fn(ServerRequestInterface $req, string $lang = 'en') => new Response(200, [], "lang:{$lang}"));
        $this->router->compile();

        // "ZZ" is uppercase — doesn't match locale pattern [a-z]{2}
        $response = $this->router->handle($this->request('GET', '/ZZ/users'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ── Locale with deeper paths ──

    #[Test]
    public function localePrefixWithNestedPath(): void
    {
        $this->router->get('/{lang:locale?}/users/{id:int}', function (ServerRequestInterface $req): ResponseInterface {
            $lang = $req->getAttribute('lang', 'en');
            $id = $req->getAttribute('id');

            return new Response(200, [], "lang:{$lang},id:{$id}");
        });
        $this->router->compile();

        $withLocale = $this->router->handle($this->request('GET', '/ar/users/42'));
        self::assertSame('lang:ar,id:42', (string) $withLocale->getBody());

        $withoutLocale = $this->router->handle($this->request('GET', '/users/42'));
        self::assertSame('lang:en,id:42', (string) $withoutLocale->getBody());
    }

    // ── Multiple routes with locale prefix ──

    #[Test]
    public function multipleRoutesWithLocalePrefix(): void
    {
        $this->router->get('/{lang:locale?}/users', fn(ServerRequestInterface $req, string $lang = 'en') => new Response(200, [], "users:{$lang}"));
        $this->router->get('/{lang:locale?}/posts', fn(ServerRequestInterface $req, string $lang = 'en') => new Response(200, [], "posts:{$lang}"));
        $this->router->compile();

        self::assertSame('users:fr', (string) $this->router->handle($this->request('GET', '/fr/users'))->getBody());
        self::assertSame('posts:de', (string) $this->router->handle($this->request('GET', '/de/posts'))->getBody());
        self::assertSame('users:en', (string) $this->router->handle($this->request('GET', '/users'))->getBody());
        self::assertSame('posts:en', (string) $this->router->handle($this->request('GET', '/posts'))->getBody());
    }

    // ── Locale with region code ──

    #[Test]
    public function localeWithRegionCode(): void
    {
        $this->router->get('/{lang:locale?}/about', fn(ServerRequestInterface $req, string $lang = 'en') => new Response(200, [], "lang:{$lang}"));
        $this->router->compile();

        $response = $this->router->handle($this->request('GET', '/en-US/about'));

        self::assertSame('lang:en-US', (string) $response->getBody());
    }

    // ── Optional in middle of path ──

    #[Test]
    public function optionalInMiddleOfPath(): void
    {
        $this->router->get('/api/{version:any?}/users', fn(ServerRequestInterface $req, string $version = 'v1') => new Response(200, [], "ver:{$version}"));
        $this->router->compile();

        $withVersion = $this->router->handle($this->request('GET', '/api/v2/users'));
        self::assertSame('ver:v2', (string) $withVersion->getBody());

        $withoutVersion = $this->router->handle($this->request('GET', '/api/users'));
        self::assertSame('ver:v1', (string) $withoutVersion->getBody());
    }
}
