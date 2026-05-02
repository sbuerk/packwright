<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Dto\Unknown;

use Packwright\ExtEmConf\Dto\Unknown\UnknownProperty;
use Packwright\ExtEmConf\Dto\Unknown\UnknownPropertyCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnknownPropertyCollectionTest extends TestCase
{
    #[Test]
    public function defaultCollectionIsEmpty(): void
    {
        $collection = new UnknownPropertyCollection();

        self::assertTrue($collection->isEmpty());
        self::assertSame(0, $collection->count());
        self::assertSame([], $collection->all());
        self::assertSame([], $collection->keys());
        self::assertFalse($collection->has('anything'));
        self::assertNull($collection->get('anything'));
    }

    #[Test]
    public function countIterationAndKeysReturnedInInsertionOrder(): void
    {
        $a = new UnknownProperty('foo', 'bar');
        $b = new UnknownProperty('baz', 42);
        $c = new UnknownProperty('qux', null);

        $collection = new UnknownPropertyCollection([$a, $b, $c]);

        self::assertCount(3, $collection);
        self::assertSame(['foo', 'baz', 'qux'], $collection->keys());
        self::assertSame([$a, $b, $c], $collection->all());
        self::assertSame(['foo' => $a, 'baz' => $b, 'qux' => $c], iterator_to_array($collection));
    }

    #[Test]
    public function hasAndGetByKeyReturnIdentityForFirstMatch(): void
    {
        $a = new UnknownProperty('foo', 'bar');
        $b = new UnknownProperty('baz', 42);

        $collection = new UnknownPropertyCollection([$a, $b]);

        self::assertTrue($collection->has('foo'));
        self::assertSame($a, $collection->get('foo'));
        self::assertSame($b, $collection->get('baz'));
        self::assertFalse($collection->has('absent'));
        self::assertNull($collection->get('absent'));
    }

    #[Test]
    public function valueMayBeAnyMixedShapeIncludingArraysAndNull(): void
    {
        $collection = new UnknownPropertyCollection([
            new UnknownProperty('scalar', 1),
            new UnknownProperty('null', null),
            new UnknownProperty('list', [1, 2, 3]),
            new UnknownProperty('map', ['a' => 1]),
        ]);

        self::assertSame(1, $collection->get('scalar')?->value);
        self::assertNull($collection->get('null')?->value);
        self::assertSame([1, 2, 3], $collection->get('list')?->value);
        self::assertSame(['a' => 1], $collection->get('map')?->value);
    }

    #[Test]
    public function duplicateKeysFirstOccurrenceWinsForIterationAndGet(): void
    {
        $first = new UnknownProperty('dup', 'first');
        $second = new UnknownProperty('dup', 'second');

        $collection = new UnknownPropertyCollection([$first, $second]);

        self::assertSame($first, $collection->get('dup'));
        self::assertSame(['dup' => $first], iterator_to_array($collection));
        self::assertSame(['dup'], $collection->keys());
        self::assertSame([$first, $second], $collection->all(), 'all() preserves insertion order including duplicates');
    }
}
