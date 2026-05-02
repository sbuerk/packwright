<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Unit\ExtEmConf\Enum;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Enum\Typo3Version;

final class Typo3VersionTest extends TestCase
{
    #[Test]
    public function labelRendersMajorVersion(): void
    {
        self::assertSame('TYPO3 v8', Typo3Version::V8->label());
        self::assertSame('TYPO3 v13', Typo3Version::V13->label());
    }
}
