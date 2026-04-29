<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Unit\ExtEmConf\Constraints\VersionValue;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;

final class AllVersionsTest extends TestCase
{
    #[Test]
    public function canBeInstantiated(): void
    {
        $value = new AllVersions();

        self::assertInstanceOf(AllVersions::class, $value);
    }
}
