<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit\Generator;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Routing\Generator\UrlGenerator;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;
use PHPdot\Routing\Router;
use PHPdot\Routing\Tests\Stubs\StubContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    private function generator(RouteCollection $collection): UrlGenerator
    {
        return new UrlGenerator($collection);
    }

    private function collection(): RouteCollection
    {
        return new RouteCollection();
    }

    // ── Basic generation ──

    #[Test]
    public function generatesStaticUrl(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users', ['users'], fn() => null);
        $route->name('users.index');
        $c->add($route);

        self::assertSame('/users', $this->generator($c)->generate('users.index'));
    }

    #[Test]
    public function generatesNestedStaticUrl(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'api/v1/health', ['api', 'v1', 'health'], fn() => null);
        $route->name('health');
        $c->add($route);

        self::assertSame('/api/v1/health', $this->generator($c)->generate('health'));
    }

    #[Test]
    public function generatesRootUrl(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], '', [], fn() => null);
        $route->name('home');
        $c->add($route);

        self::assertSame('/', $this->generator($c)->generate('home'));
    }

    // ── Dynamic parameters ──

    #[Test]
    public function substitutesIntParameter(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null);
        $route->name('users.show');
        $c->add($route);

        self::assertSame('/users/42', $this->generator($c)->generate('users.show', ['id' => 42]));
    }

    #[Test]
    public function substitutesSlugParameter(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'posts/{slug:slug}', ['posts', '{slug:slug}'], fn() => null);
        $route->name('posts.show');
        $c->add($route);

        self::assertSame('/posts/hello-world', $this->generator($c)->generate('posts.show', ['slug' => 'hello-world']));
    }

    #[Test]
    public function substitutesMultipleParameters(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users/{id:int}/posts/{postId:int}', ['users', '{id:int}', 'posts', '{postId:int}'], fn() => null);
        $route->name('users.posts.show');
        $c->add($route);

        self::assertSame(
            '/users/5/posts/99',
            $this->generator($c)->generate('users.posts.show', ['id' => 5, 'postId' => 99]),
        );
    }

    #[Test]
    public function substitutesUntypedParameter(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'files/{name}', ['files', '{name}'], fn() => null);
        $route->name('files.show');
        $c->add($route);

        self::assertSame('/files/report.pdf', $this->generator($c)->generate('files.show', ['name' => 'report.pdf']));
    }

    // ── Optional parameters ──

    #[Test]
    public function optionalParamIncludedWhenProvided(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'posts/{page:int?}', ['posts', '{page:int?}'], fn() => null);
        $route->name('posts.index');
        $c->add($route);

        self::assertSame('/posts/3', $this->generator($c)->generate('posts.index', ['page' => 3]));
    }

    #[Test]
    public function optionalParamOmittedWhenNotProvided(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'posts/{page:int?}', ['posts', '{page:int?}'], fn() => null);
        $route->name('posts.index');
        $c->add($route);

        self::assertSame('/posts', $this->generator($c)->generate('posts.index'));
    }

    // ── Wildcard ──

    #[Test]
    public function wildcardParameterSubstituted(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'docs/{path:*}', ['docs', '{path:*}'], fn() => null);
        $route->name('docs.show');
        $c->add($route);

        self::assertSame('/docs/guide/install', $this->generator($c)->generate('docs.show', ['path' => 'guide/install']));
    }

    #[Test]
    public function wildcardMissingThrows(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'docs/{path:*}', ['docs', '{path:*}'], fn() => null);
        $route->name('docs.show');
        $c->add($route);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Wildcard parameter 'path'");

        $this->generator($c)->generate('docs.show');
    }

    // ── Query string ──

    #[Test]
    public function appendsQueryString(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users', ['users'], fn() => null);
        $route->name('users.index');
        $c->add($route);

        self::assertSame('/users?page=2&sort=name', $this->generator($c)->generate('users.index', [], ['page' => 2, 'sort' => 'name']));
    }

    #[Test]
    public function queryStringWithDynamicParams(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null);
        $route->name('users.show');
        $c->add($route);

        self::assertSame(
            '/users/42?tab=posts',
            $this->generator($c)->generate('users.show', ['id' => 42], ['tab' => 'posts']),
        );
    }

    #[Test]
    public function emptyQueryStringNotAppended(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users', ['users'], fn() => null);
        $route->name('users.index');
        $c->add($route);

        self::assertSame('/users', $this->generator($c)->generate('users.index', [], []));
    }

    // ── Error cases ──

    #[Test]
    public function unknownRouteNameThrows(): void
    {
        $c = $this->collection();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Route 'nonexistent' not found");

        $this->generator($c)->generate('nonexistent');
    }

    #[Test]
    public function missingRequiredParamThrows(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null);
        $route->name('users.show');
        $c->add($route);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required parameter 'id'");

        $this->generator($c)->generate('users.show');
    }

    // ── has() ──

    #[Test]
    public function hasReturnsTrueForExistingRoute(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users', ['users'], fn() => null);
        $route->name('users.index');
        $c->add($route);

        $gen = $this->generator($c);

        self::assertTrue($gen->has('users.index'));
        self::assertFalse($gen->has('nonexistent'));
    }

    // ── path() alias ──

    #[Test]
    public function pathIsAliasForGenerateWithoutQuery(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null);
        $route->name('users.show');
        $c->add($route);

        self::assertSame('/users/42', $this->generator($c)->path('users.show', ['id' => 42]));
    }

    // ── Router integration ──

    #[Test]
    public function routerManagerUrlShortcut(): void
    {
        $container = new StubContainer();
        $factory = new Psr17Factory();
        $router = new Router($container, $factory);

        $router->get('/users/{id:int}', fn() => null)->name('users.show');
        $router->compile();

        self::assertSame('/users/42', $router->url('users.show', ['id' => 42]));
        self::assertSame('/users/42?tab=posts', $router->url('users.show', ['id' => 42], ['tab' => 'posts']));
    }
}
