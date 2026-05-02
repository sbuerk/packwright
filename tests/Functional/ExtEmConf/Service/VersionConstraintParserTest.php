<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use Packwright\ExtEmConf\Service\VersionConstraintParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VersionConstraintParserTest extends TestCase
{
    #[Test]
    public function emptyStringYieldsAllVersions(): void
    {
        $parser = new VersionConstraintParser();

        self::assertInstanceOf(AllVersions::class, $parser->parse(''));
    }

    #[Test]
    public function whitespaceOnlyYieldsAllVersions(): void
    {
        $parser = new VersionConstraintParser();

        self::assertInstanceOf(AllVersions::class, $parser->parse("   \t  "));
    }

    #[Test]
    public function asteriskYieldsAllVersions(): void
    {
        $parser = new VersionConstraintParser();

        self::assertInstanceOf(AllVersions::class, $parser->parse('*'));
    }

    #[Test]
    public function singleSemverYieldsSingleVersion(): void
    {
        $parser = new VersionConstraintParser();
        $result = $parser->parse('1.2.3');

        self::assertInstanceOf(SingleVersion::class, $result);
        self::assertSame('1.2.3', $result->toVersionString());
    }

    #[Test]
    public function rangeYieldsVersionRange(): void
    {
        $parser = new VersionConstraintParser();
        $result = $parser->parse('1.0.0-2.5.99');

        self::assertInstanceOf(VersionRange::class, $result);
        self::assertSame('1.0.0-2.5.99', $result->toVersionString());
    }

    #[Test]
    public function invalidStringThrowsInvalidArgumentException(): void
    {
        $parser = new VersionConstraintParser();

        $this->expectException(\InvalidArgumentException::class);
        $parser->parse('not-a-version');
    }

    #[Test]
    public function invalidRangeBoundThrowsInvalidArgumentException(): void
    {
        $parser = new VersionConstraintParser();

        $this->expectException(\InvalidArgumentException::class);
        $parser->parse('1.2.3-foo.bar.baz');
    }
}
