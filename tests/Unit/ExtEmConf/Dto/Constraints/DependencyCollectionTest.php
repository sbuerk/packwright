<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Dto\Constraints;

use Packwright\ExtEmConf\Dto\Constraints\Dependency;
use Packwright\ExtEmConf\Dto\Constraints\DependencyCollection;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Enum\ConstraintVersionType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DependencyCollectionTest extends TestCase
{
    #[Test]
    public function hasReturnsFalseOnEmptyCollection(): void
    {
        $collection = new DependencyCollection();

        self::assertFalse($collection->has('typo3'));
        self::assertNull($collection->get('typo3'));
    }

    #[Test]
    public function hasAndGetByExtensionKeyReturnIdentity(): void
    {
        $php = new Dependency('php', ConstraintVersionType::ALL, new AllVersions());
        $typo3 = new Dependency('typo3', ConstraintVersionType::ALL, new AllVersions());

        $collection = new DependencyCollection([$php, $typo3]);

        self::assertTrue($collection->has('php'));
        self::assertSame($php, $collection->get('php'));
        self::assertSame($typo3, $collection->get('typo3'));
        self::assertFalse($collection->has('news'));
        self::assertNull($collection->get('news'));
    }
}
