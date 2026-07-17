<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit\Matcher;

use PHPdot\Routing\Compiler\PatternRegistry;
use PHPdot\Routing\Compiler\RouteCompiler;
use PHPdot\Routing\Matcher\MethodNotAllowed;
use PHPdot\Routing\Matcher\RouteMatch;
use PHPdot\Routing\Matcher\TrieMatcher;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrieMatcherTest extends TestCase
{
    private function buildMatcher(RouteCollection $collection): TrieMatcher
    {
        $compiler = new RouteCompiler(new PatternRegistry());
        $root = $compiler->compile($collection);

        return new TrieMatcher($root);
    }

    private function collection(): RouteCollection
    {
        return new RouteCollection();
    }

    // ── Static routes ──

    #[Test]
    public function matchesStaticRoute(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['users']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame([], $result->getParameters());
    }

    #[Test]
    public function matchesNestedStaticRoute(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'api/v1/health', ['api', 'v1', 'health'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['api', 'v1', 'health']);

        self::assertInstanceOf(RouteMatch::class, $result);
    }

    #[Test]
    public function returnsNullForUnknownPath(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $matcher = $this->buildMatcher($c);

        self::assertNull($matcher->match('GET', ['posts']));
    }

    // ── Dynamic routes ──

    #[Test]
    public function matchesDynamicIntSegment(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null);
        $route->where('id', 'int');
        $c->add($route);
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['users', '42']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame(42, $result->getParameter('id'));
    }

    #[Test]
    public function rejectsDynamicSegmentTypeMismatch(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        self::assertNull($matcher->match('GET', ['users', 'abc']));
    }

    #[Test]
    public function matchesSlugSegment(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'posts/{slug:slug}', ['posts', '{slug:slug}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['posts', 'hello-world']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame('hello-world', $result->getParameter('slug'));
    }

    #[Test]
    public function matchesMultipleDynamicSegments(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users/{id:int}/posts/{postId:int}', ['users', '{id:int}', 'posts', '{postId:int}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['users', '5', 'posts', '99']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame(5, $result->getParameter('id'));
        self::assertSame(99, $result->getParameter('postId'));
    }

    // ── Optional segments ──

    #[Test]
    public function matchesOptionalSegmentPresent(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'posts/{page:int?}', ['posts', '{page:int?}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['posts', '3']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame(3, $result->getParameter('page'));
    }

    #[Test]
    public function matchesOptionalSegmentAbsent(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'posts/{page:int?}', ['posts', '{page:int?}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['posts']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame([], $result->getParameters());
    }

    // ── Wildcard ──

    #[Test]
    public function matchesWildcardRoute(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'docs/{path:*}', ['docs', '{path:*}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['docs', 'getting-started', 'installation']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame('getting-started/installation', $result->getParameter('path'));
    }

    #[Test]
    public function matchesWildcardSingleSegment(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'docs/{path:*}', ['docs', '{path:*}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['docs', 'readme']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame('readme', $result->getParameter('path'));
    }

    // ── Method matching ──

    #[Test]
    public function returns405ForWrongMethod(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $c->add(new Route(['POST'], 'users', ['users'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('DELETE', ['users']);

        self::assertInstanceOf(MethodNotAllowed::class, $result);
        self::assertContains('GET', $result->allowedMethods);
        self::assertContains('POST', $result->allowedMethods);
    }

    #[Test]
    public function headFallsBackToGet(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('HEAD', ['users']);

        self::assertInstanceOf(RouteMatch::class, $result);
    }

    // ── Host matching ──

    #[Test]
    public function matchesRouteWithHostConstraint(): void
    {
        $c = $this->collection();
        $route = new Route(['GET'], 'dashboard', ['dashboard'], fn() => null);
        $route->host('admin.example.com');
        $c->add($route);
        $matcher = $this->buildMatcher($c);

        self::assertInstanceOf(RouteMatch::class, $matcher->match('GET', ['dashboard'], 'admin.example.com'));
        self::assertNull($matcher->match('GET', ['dashboard'], 'example.com'));
    }

    #[Test]
    public function routeWithNoHostMatchesAnyHost(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $matcher = $this->buildMatcher($c);

        self::assertInstanceOf(RouteMatch::class, $matcher->match('GET', ['users'], 'anything.com'));
    }

    // ── Backtracking ──

    #[Test]
    public function staticRoutePreferredOverDynamic(): void
    {
        $c = $this->collection();
        $staticRoute = new Route(['GET'], 'users/me', ['users', 'me'], fn() => 'static');
        $dynamicRoute = new Route(['GET'], 'users/{id}', ['users', '{id}'], fn() => 'dynamic');
        $c->add($staticRoute);
        $c->add($dynamicRoute);
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['users', 'me']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame($staticRoute, $result->getRoute());
    }

    #[Test]
    public function backtracksFromFailedStaticToMatchDynamic(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], 'users/me', ['users', 'me'], fn() => null));
        $c->add(new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', ['users', '42']);

        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame(42, $result->getParameter('id'));
    }

    // ── Root path ──

    #[Test]
    public function matchesRootPath(): void
    {
        $c = $this->collection();
        $c->add(new Route(['GET'], '', [], fn() => null));
        $matcher = $this->buildMatcher($c);

        $result = $matcher->match('GET', []);

        self::assertInstanceOf(RouteMatch::class, $result);
    }
}
