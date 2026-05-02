<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\Console;

use Packwright\Console\DisplayPathFormatter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DisplayPathFormatterTest extends TestCase
{
    #[Test]
    public function formatsPathBelowCwdAsRelative(): void
    {
        $formatter = new DisplayPathFormatter();

        self::assertSame(
            './sub/file.php',
            $formatter->format('/proj/sub/file.php', '/proj'),
        );
    }

    #[Test]
    public function formatsPathOutsideCwdAsAbsolute(): void
    {
        $formatter = new DisplayPathFormatter();

        self::assertSame(
            '/tmp/file.php',
            $formatter->format('/tmp/file.php', '/proj'),
        );
    }

    #[Test]
    public function formatsPathEqualToCwdAsDot(): void
    {
        $formatter = new DisplayPathFormatter();

        self::assertSame(
            './',
            $formatter->format('/proj', '/proj'),
        );
    }

    #[Test]
    public function cwdPrefixWithoutBoundaryIsNotMatched(): void
    {
        $formatter = new DisplayPathFormatter();

        self::assertSame(
            '/proj-sibling/file.php',
            $formatter->format('/proj-sibling/file.php', '/proj'),
        );
    }

    #[Test]
    public function trailingSlashInCwdIsTolerated(): void
    {
        $formatter = new DisplayPathFormatter();

        self::assertSame(
            './x',
            $formatter->format('/proj/x', '/proj/'),
        );
    }

    #[Test]
    public function emptyCwdOverrideReturnsInputUnchanged(): void
    {
        $formatter = new DisplayPathFormatter();

        self::assertSame(
            '/proj/x',
            $formatter->format('/proj/x', ''),
        );
    }
}
