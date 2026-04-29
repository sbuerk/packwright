<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Unit\ExtEmConf\Constraints\VersionValue;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use SBReleaser\ExtEmConf\Dto\Version;

final class VersionRangeTest extends TestCase
{
    #[Test]
    public function holdsLowestAndHighestBounds(): void
    {
        $lowest = new Version(major: 12, minor: 4, patch: 0);
        $highest = new Version(major: 13, minor: 4, patch: 99);
        $range = new VersionRange($lowest, $highest);

        self::assertSame($lowest, $range->lowest);
        self::assertSame($highest, $range->highest);
    }
}
