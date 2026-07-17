<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit;

use InvalidArgumentException;
use PHPdot\Routing\Compiler\PatternRegistry;
use PHPdot\Routing\Compiler\RouteCompiler;
use PHPdot\Routing\Route\Route;
use PHPdot\Routing\Route\RouteCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecurityTest extends TestCase
{
    // ── Regex injection protection ──

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeRegexProvider(): array
    {
        return [
            'lookahead'          => ['(?=bad)'],
            'negative lookahead' => ['(?!bad)'],
            'lookbehind'         => ['(?<=good)'],
            'negative lookbehind' => ['(?<!good)'],
            'backreference'      => ['(a)\\1'],
            'recursive'          => ['(?R)'],
            'conditional'        => ['(?(1)a|b)'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeRegexProvider')]
    public function rejectsUnsafeRegex(string $pattern): void
    {
        $registry = new PatternRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsafe regex');

        $registry->add('evil', $pattern);
    }

    #[Test]
    public function rejectsInvalidRegex(): void
    {
        $registry = new PatternRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex');

        $registry->add('broken', '[invalid');
    }

    #[Test]
    public function acceptsSafeCustomPattern(): void
    {
        $registry = new PatternRegistry();
        $registry->add('hex8', '[a-f0-9]{8}');

        self::assertTrue($registry->has('hex8'));
        self::assertSame('[a-f0-9]{8}', $registry->get('hex8'));
    }

    #[Test]
    public function acceptsCharacterClassesAndQuantifiers(): void
    {
        $registry = new PatternRegistry();

        // These are all legitimate patterns
        $registry->add('phone', '[0-9]{3}-[0-9]{4}');
        $registry->add('alphanum', '[a-zA-Z0-9]+');
        $registry->add('optional_dash', '[a-z]+(?:-[a-z]+)*');

        self::assertTrue($registry->has('phone'));
        self::assertTrue($registry->has('alphanum'));
        self::assertTrue($registry->has('optional_dash'));
    }

    // ── Duplicate route detection ──

    #[Test]
    public function rejectsDuplicateStaticRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $collection->add(new Route(['GET'], 'users', ['users'], fn() => null));

        $compiler = new RouteCompiler(new PatternRegistry());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate route');

        $compiler->compile($collection);
    }

    #[Test]
    public function rejectsDuplicateDynamicRoute(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null));
        $collection->add(new Route(['GET'], 'users/{id:int}', ['users', '{id:int}'], fn() => null));

        $compiler = new RouteCompiler(new PatternRegistry());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate route');

        $compiler->compile($collection);
    }

    #[Test]
    public function allowsSamePathDifferentMethods(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'users', ['users'], fn() => null));
        $collection->add(new Route(['POST'], 'users', ['users'], fn() => null));

        $compiler = new RouteCompiler(new PatternRegistry());
        $root = $compiler->compile($collection);

        // Should not throw — different methods
        self::assertArrayHasKey('GET', $root->staticChildren['users']->leaves);
        self::assertArrayHasKey('POST', $root->staticChildren['users']->leaves);
    }

    #[Test]
    public function allowsDifferentDynamicPatternsOnSamePath(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], 'items/{id:int}', ['items', '{id:int}'], fn() => null));
        $collection->add(new Route(['GET'], 'items/{slug:slug}', ['items', '{slug:slug}'], fn() => null));

        $compiler = new RouteCompiler(new PatternRegistry());
        $root = $compiler->compile($collection);

        // Different param names/types — these are separate trie branches
        self::assertCount(2, $root->staticChildren['items']->dynamicChildren);
    }
}
