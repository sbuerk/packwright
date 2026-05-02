<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Constraints\VersionValue;

use Packwright\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use Packwright\ExtEmConf\Dto\Version;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
