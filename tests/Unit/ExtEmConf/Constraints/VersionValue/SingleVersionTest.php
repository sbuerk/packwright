<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Unit\ExtEmConf\Constraints\VersionValue;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use SBReleaser\ExtEmConf\Dto\Version;

final class SingleVersionTest extends TestCase
{
    #[Test]
    public function holdsProvidedVersion(): void
    {
        $inner = new Version(major: 12, minor: 4, patch: 0);
        $value = new SingleVersion($inner);

        self::assertSame($inner, $value->version);
    }
}
