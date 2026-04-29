<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Unit\ExtEmConf\Dto;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Version;

final class VersionTest extends TestCase
{
    #[Test]
    public function fromStringParsesStandardVersion(): void
    {
        $version = Version::fromString('1.2.3');

        self::assertSame(1, $version->major);
        self::assertSame(2, $version->minor);
        self::assertSame(3, $version->patch);
        self::assertSame(0, $version->build);
        self::assertNull($version->stability);
    }

    #[Test]
    public function fromStringParsesFourPartVersion(): void
    {
        $version = Version::fromString('12.4.99.0');

        self::assertSame(12, $version->major);
        self::assertSame(4, $version->minor);
        self::assertSame(99, $version->patch);
        self::assertSame(0, $version->build);
    }

    #[Test]
    public function fromStringParsesAlphaStability(): void
    {
        $version = Version::fromString('2.0.0-alpha');

        self::assertSame(2, $version->major);
        self::assertSame(0, $version->minor);
        self::assertSame(0, $version->patch);
        self::assertSame('alpha', $version->stability);
    }

    #[Test]
    public function fromStringParsesStableAsNullStability(): void
    {
        $version = Version::fromString('1.0.0');

        self::assertNull($version->stability);
    }

    #[Test]
    public function fromStringThrowsForInvalidVersion(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        Version::fromString('not-a-version');
    }

    #[Test]
    public function toStringReturnsXyzFormat(): void
    {
        $version = new Version(major: 3, minor: 1, patch: 4);

        self::assertSame('3.1.4', (string)$version);
    }

    /**
     * @return array<string, array{Version, string, string}>
     */
    public static function formatProvider(): array
    {
        $v = new Version(major: 1, minor: 2, patch: 3, build: 4, stability: 'beta');
        $stable = new Version(major: 1, minor: 0, patch: 0);

        return [
            'xyz' => [$v, 'x.y.z', '1.2.3'],
            'xyzd' => [$v, 'x.y.z.d', '1.2.3.4'],
            'xyz-stability with suffix' => [$v, 'x.y.z-stability', '1.2.3-beta'],
            'xyz-stability without suffix falls back' => [$stable, 'x.y.z-stability', '1.0.0'],
        ];
    }

    #[Test]
    #[DataProvider('formatProvider')]
    public function formatReturnsExpectedString(Version $version, string $pattern, string $expected): void
    {
        self::assertSame($expected, $version->format($pattern));
    }

    #[Test]
    public function formatThrowsForUnknownPattern(): void
    {
        $version = new Version(major: 1, minor: 0, patch: 0);

        $this->expectException(\InvalidArgumentException::class);

        $version->format('unknown');
    }
}
