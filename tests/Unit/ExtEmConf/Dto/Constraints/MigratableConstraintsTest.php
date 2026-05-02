<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Dto\Constraints;

use Packwright\ExtEmConf\Dto\Constraints\Dependency;
use Packwright\ExtEmConf\Dto\Constraints\DependencyCollection;
use Packwright\ExtEmConf\Dto\Constraints\MigratableConstraints;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Enum\ConstraintVersionType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MigratableConstraintsTest extends TestCase
{
    #[Test]
    public function defaultConstructionExposesEmptyCollections(): void
    {
        $migratable = new MigratableConstraints();

        self::assertTrue($migratable->isEmpty());
        self::assertTrue($migratable->depend->isEmpty());
        self::assertTrue($migratable->suggest->isEmpty());
        self::assertTrue($migratable->conflict->isEmpty());
    }

    #[Test]
    public function isEmptyReportsFalseWhenAnyGroupHasEntries(): void
    {
        $migratable = new MigratableConstraints(
            depend: new DependencyCollection([
                new Dependency('php', ConstraintVersionType::ALL, new AllVersions()),
            ]),
        );

        self::assertFalse($migratable->isEmpty());
        self::assertFalse($migratable->depend->isEmpty());
        self::assertTrue($migratable->suggest->isEmpty());
        self::assertTrue($migratable->conflict->isEmpty());
    }

    #[Test]
    public function namedArgumentsAccessIndividualGroupsByCanonicalSingularName(): void
    {
        $depend = new DependencyCollection([
            new Dependency('php', ConstraintVersionType::ALL, new AllVersions()),
        ]);
        $suggest = new DependencyCollection([
            new Dependency('news', ConstraintVersionType::ALL, new AllVersions()),
        ]);
        $conflict = new DependencyCollection([
            new Dependency('old_ext', ConstraintVersionType::ALL, new AllVersions()),
        ]);

        $migratable = new MigratableConstraints(
            depend: $depend,
            suggest: $suggest,
            conflict: $conflict,
        );

        self::assertSame($depend, $migratable->depend);
        self::assertSame($suggest, $migratable->suggest);
        self::assertSame($conflict, $migratable->conflict);
    }
}
