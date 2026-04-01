<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit;

use PHPdot\Routing\Utils\Path;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    #[Test]
    public function segmentsSplitsPath(): void
    {
        self::assertSame(['users', '42', 'posts'], Path::segments('/users/42/posts'));
    }

    #[Test]
    public function segmentsHandlesRoot(): void
    {
        self::assertSame([], Path::segments('/'));
        self::assertSame([], Path::segments(''));
    }

    #[Test]
    public function segmentsStripsTrailingSlash(): void
    {
        self::assertSame(['users'], Path::segments('/users/'));
    }

    #[Test]
    public function segmentsHandlesMultipleSlashes(): void
    {
        self::assertSame(['a', 'b'], Path::segments('///a///b///'));
    }

    #[Test]
    public function buildCreatesPath(): void
    {
        self::assertSame('/users/42/posts', Path::build(['users', '42', 'posts']));
    }

    #[Test]
    public function buildEmptySegments(): void
    {
        self::assertSame('/', Path::build([]));
    }

    #[Test]
    public function firstReturnsFirstSegment(): void
    {
        self::assertSame('en', Path::first('/en/users'));
        self::assertSame('users', Path::first('/users'));
        self::assertNull(Path::first('/'));
    }

    #[Test]
    public function shiftRemovesFirstSegment(): void
    {
        self::assertSame('/users/42', Path::shift('/en/users/42'));
        self::assertSame('/42', Path::shift('/users/42'));
        self::assertSame('/', Path::shift('/users'));
        self::assertSame('/', Path::shift('/'));
    }
}
