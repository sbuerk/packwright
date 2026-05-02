<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Constraints\VersionValue;

use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AllVersionsTest extends TestCase
{
    #[Test]
    public function canBeInstantiated(): void
    {
        $value = new AllVersions();

        self::assertInstanceOf(AllVersions::class, $value);
    }
}
