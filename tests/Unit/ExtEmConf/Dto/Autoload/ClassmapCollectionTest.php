<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Dto\Autoload;

use Packwright\ExtEmConf\Dto\Autoload\ClassmapCollection;
use Packwright\ExtEmConf\Dto\Autoload\ClassmapEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassmapCollectionTest extends TestCase
{
    #[Test]
    public function hasAndGetByPathReturnIdentity(): void
    {
        $first = new ClassmapEntry(path: 'Classes/Legacy/');
        $second = new ClassmapEntry(path: 'Classes/Vendor/');

        $collection = new ClassmapCollection([$first, $second]);

        self::assertTrue($collection->has('Classes/Legacy/'));
        self::assertSame($first, $collection->get('Classes/Legacy/'));
        self::assertSame($second, $collection->get('Classes/Vendor/'));
        self::assertFalse($collection->has('Classes/Missing/'));
        self::assertNull($collection->get('Classes/Missing/'));
    }
}
