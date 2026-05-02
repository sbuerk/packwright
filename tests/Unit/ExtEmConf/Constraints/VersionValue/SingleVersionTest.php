<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Constraints\VersionValue;

use Packwright\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use Packwright\ExtEmConf\Dto\Version;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
