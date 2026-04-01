<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit\Compiler;

use PHPdot\Routing\Compiler\PatternRegistry;
use PHPdot\Routing\Compiler\RouteCompiler;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouteCompilerTest extends TestCase
{
    private PatternRegistry $patterns;
    private RouteCompiler $compiler;

    protected function setUp(): void
    {
        $this->patterns = new PatternRegistry();
        $this->compiler = new RouteCompiler($this->patterns);
    }

    #[Test]
    public function compilesStaticRouteIntoStaticChildren(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'users', ['users'], fn() => null));

        $root = $this->compiler->compile($collection);

        self::assertArrayHasKey('users', $root->staticChildren);
        self::assertArrayHasKey('GET', $root->staticChildren['users']->leaves);
    }

    #[Test]
    public function compilesNestedStaticRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'api/v1/health', ['api', 'v1', 'health'], fn() => null));

        $root = $this->compiler->compile($collection);

        $api = $root->staticChildren['api'] ?? null;
        self::assertNotNull($api);

        $v1 = $api->staticChildren['v1'] ?? null;
        self::assertNotNull($v1);

        $health = $v1->staticChildren['health'] ?? null;
        self::assertNotNull($health);

        self::assertArrayHasKey('GET', $health->leaves);
    }

    #[Test]
    public function compilesDynamicRouteIntoDynamicChildren(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null));

        $root = $this->compiler->compile($collection);

        $users = $root->staticChildren['users'] ?? null;
        self::assertNotNull($users);
        self::assertCount(1, $users->dynamicChildren);
        self::assertSame('id', $users->dynamicChildren[0]['name']);
        self::assertSame('[0-9]+', $users->dynamicChildren[0]['regex']);
    }

    #[Test]
    public function compilesOptionalSegmentIntoTwoPaths(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'posts/{page:int?}', ['posts', '{page:int?}'], fn() => null));

        $root = $this->compiler->compile($collection);

        // Path without optional param: /posts
        $posts = $root->staticChildren['posts'] ?? null;
        self::assertNotNull($posts);
        self::assertArrayHasKey('GET', $posts->leaves);

        // Path with optional param: /posts/{page}
        self::assertCount(1, $posts->dynamicChildren);
    }

    #[Test]
    public function compilesWildcardRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'docs/{path:*}', ['docs', '{path:*}'], fn() => null));

        $root = $this->compiler->compile($collection);

        $docs = $root->staticChildren['docs'] ?? null;
        self::assertNotNull($docs);
        self::assertNotNull($docs->wildcard);
        self::assertSame('path', $docs->wildcard['name']);
    }

    #[Test]
    public function compilesMultipleMethodsOnSamePath(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $collection->add(new Route(['POST'], 'users', ['users'], fn() => null));

        $root = $this->compiler->compile($collection);

        $users = $root->staticChildren['users'] ?? null;
        self::assertNotNull($users);
        self::assertArrayHasKey('GET', $users->leaves);
        self::assertArrayHasKey('POST', $users->leaves);
        self::assertContains('GET', $users->allowedMethods);
        self::assertContains('POST', $users->allowedMethods);
    }

    #[Test]
    public function sharesTrieNodesForCommonPrefixes(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'api/users', ['api', 'users'], fn() => null));
        $collection->add(new Route(['GET'], 'api/posts', ['api', 'posts'], fn() => null));

        $root = $this->compiler->compile($collection);

        $api = $root->staticChildren['api'] ?? null;
        self::assertNotNull($api);
        self::assertArrayHasKey('users', $api->staticChildren);
        self::assertArrayHasKey('posts', $api->staticChildren);
    }
}
