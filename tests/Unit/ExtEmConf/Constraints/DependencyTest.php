<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Unit\ExtEmConf\Constraints;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Constraints\Dependency;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\ConstraintVersionType;

final class DependencyTest extends TestCase
{
    #[Test]
    public function holdsExtensionKeyAndAllVersions(): void
    {
        $version = new AllVersions();
        $dep = new Dependency('typo3', ConstraintVersionType::ALL, $version);

        self::assertSame('typo3', $dep->extensionKey);
        self::assertSame(ConstraintVersionType::ALL, $dep->versionType);
        self::assertSame($version, $dep->version);
    }

    #[Test]
    public function holdsExtensionKeyAndSingleVersion(): void
    {
        $version = new SingleVersion(new Version(major: 12, minor: 4, patch: 0));
        $dep = new Dependency('fluid', ConstraintVersionType::VERSION, $version);

        self::assertSame('fluid', $dep->extensionKey);
        self::assertSame(ConstraintVersionType::VERSION, $dep->versionType);
        self::assertSame($version, $dep->version);
    }

    #[Test]
    public function holdsExtensionKeyAndVersionRange(): void
    {
        $version = new VersionRange(
            new Version(major: 12, minor: 4, patch: 0),
            new Version(major: 13, minor: 4, patch: 99),
        );
        $dep = new Dependency('typo3', ConstraintVersionType::RANGE, $version);

        self::assertSame('typo3', $dep->extensionKey);
        self::assertSame(ConstraintVersionType::RANGE, $dep->versionType);
        self::assertSame($version, $dep->version);
    }
}
