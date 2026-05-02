<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Dto\Autoload;

use Packwright\ExtEmConf\Dto\Autoload\Psr4Collection;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Entry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Psr4CollectionTest extends TestCase
{
    #[Test]
    public function hasAndGetByNamespaceReturnIdentity(): void
    {
        $a = new Psr4Entry(namespace: 'Vendor\\A\\', path: 'Classes/A/');
        $b = new Psr4Entry(namespace: 'Vendor\\B\\', path: 'Classes/B/');

        $collection = new Psr4Collection([$a, $b]);

        self::assertTrue($collection->has('Vendor\\A\\'));
        self::assertSame($a, $collection->get('Vendor\\A\\'));
        self::assertSame($b, $collection->get('Vendor\\B\\'));
        self::assertFalse($collection->has('Vendor\\Missing\\'));
        self::assertNull($collection->get('Vendor\\Missing\\'));
    }
}
