<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit\Compiler;

use PHPdot\Routing\Compiler\PatternRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PatternRegistryTest extends TestCase
{
    private PatternRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new PatternRegistry();
    }

    #[Test]
    public function builtInPatternsExist(): void
    {
        self::assertTrue($this->registry->has('int'));
        self::assertTrue($this->registry->has('slug'));
        self::assertTrue($this->registry->has('uuid4'));
        self::assertTrue($this->registry->has('mongo_id'));
        self::assertTrue($this->registry->has('any'));
        self::assertTrue($this->registry->has('string'));
        self::assertTrue($this->registry->has('alpha'));
        self::assertTrue($this->registry->has('uuid'));
    }

    #[Test]
    public function customPatternCanBeRegistered(): void
    {
        $this->registry->add('short_id', '[a-zA-Z0-9]{8}');

        self::assertTrue($this->registry->has('short_id'));
        self::assertSame('[a-zA-Z0-9]{8}', $this->registry->get('short_id'));
    }

    #[Test]
    public function unknownPatternReturnsNull(): void
    {
        self::assertNull($this->registry->get('nonexistent'));
        self::assertFalse($this->registry->has('nonexistent'));
    }

    #[Test]
    public function parsesStaticSegmentAsNull(): void
    {
        self::assertNull($this->registry->parseSegment('users'));
        self::assertNull($this->registry->parseSegment('api'));
        self::assertNull($this->registry->parseSegment('v1'));
    }

    #[Test]
    public function parsesSimpleDynamicSegment(): void
    {
        $result = $this->registry->parseSegment('{name}');

        self::assertNotNull($result);
        self::assertSame('name', $result['name']);
        self::assertSame('', $result['type']);
        self::assertSame('.+', $result['regex']);
        self::assertFalse($result['optional']);
        self::assertFalse($result['wildcard']);
    }

    #[Test]
    public function parsesTypedDynamicSegment(): void
    {
        $result = $this->registry->parseSegment('{id:int}');

        self::assertNotNull($result);
        self::assertSame('id', $result['name']);
        self::assertSame('int', $result['type']);
        self::assertSame('[0-9]+', $result['regex']);
        self::assertFalse($result['optional']);
        self::assertFalse($result['wildcard']);
    }

    #[Test]
    public function parsesOptionalSegment(): void
    {
        $result = $this->registry->parseSegment('{page:int?}');

        self::assertNotNull($result);
        self::assertSame('page', $result['name']);
        self::assertTrue($result['optional']);
        self::assertFalse($result['wildcard']);
    }

    #[Test]
    public function parsesWildcardSegment(): void
    {
        $result = $this->registry->parseSegment('{path:*}');

        self::assertNotNull($result);
        self::assertSame('path', $result['name']);
        self::assertSame('*', $result['type']);
        self::assertTrue($result['wildcard']);
        self::assertFalse($result['optional']);
    }

    #[Test]
    public function whereOverridesInlineType(): void
    {
        $result = $this->registry->parseSegment('{id}', ['id' => 'int']);

        self::assertNotNull($result);
        self::assertSame('int', $result['type']);
        self::assertSame('[0-9]+', $result['regex']);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function patternMatchProvider(): array
    {
        return [
            'int matches digits'        => ['int', '42', true],
            'int rejects alpha'         => ['int', 'abc', false],
            'slug matches slug'         => ['slug', 'hello-world', true],
            'slug rejects uppercase'    => ['slug', 'Hello', false],
            'uuid4 matches valid'       => ['uuid4', '550e8400-e29b-41d4-a716-446655440000', true],
            'uuid4 rejects short'       => ['uuid4', '550e8400', false],
            'mongo_id matches 24 hex'   => ['mongo_id', '507f1f77bcf86cd799439011', true],
            'mongo_id rejects short'    => ['mongo_id', '507f1f77', false],
            'any matches alnum'         => ['any', 'hello123', true],
            'any rejects special'       => ['any', 'hello-world', false],
        ];
    }

    #[Test]
    #[DataProvider('patternMatchProvider')]
    public function builtInPatternMatchesCorrectly(string $type, string $value, bool $expected): void
    {
        $regex = $this->registry->get($type);
        self::assertNotNull($regex);

        $matches = preg_match('/^' . $regex . '$/', $value) === 1;
        self::assertSame($expected, $matches);
    }
}
