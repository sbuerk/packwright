<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\ExtEmConf\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Service\PathResolver;

final class PathResolverTest extends TestCase
{
    #[Test]
    public function resolveLiteralFileReturnsAbsolutePath(): void
    {
        $file = __DIR__ . '/../Fixtures/minimal/ext_emconf.php';

        $resolved = (new PathResolver())->resolve([$file]);

        self::assertSame([realpath($file)], $resolved);
    }

    #[Test]
    public function resolveDirectoryWalksRecursivelyForExtEmConfFiles(): void
    {
        $directory = __DIR__ . '/../Fixtures';

        $resolved = (new PathResolver())->resolve([$directory]);

        self::assertNotEmpty($resolved);
        foreach ($resolved as $path) {
            self::assertSame('ext_emconf.php', basename($path));
        }
        // All seven fixture directories ship one ext_emconf.php each.
        self::assertCount(7, $resolved);
    }

    #[Test]
    public function resolveGlobExpandsMatchingFiles(): void
    {
        $pattern = __DIR__ . '/../Fixtures/*/ext_emconf.php';

        $resolved = (new PathResolver())->resolve([$pattern]);

        self::assertCount(7, $resolved);
        foreach ($resolved as $path) {
            self::assertSame('ext_emconf.php', basename($path));
        }
    }

    #[Test]
    public function resolveDeduplicatesEqualRealPaths(): void
    {
        $file = __DIR__ . '/../Fixtures/minimal/ext_emconf.php';
        $aliased = __DIR__ . '/../Fixtures/minimal/../minimal/ext_emconf.php';

        $resolved = (new PathResolver())->resolve([$file, $aliased]);

        self::assertSame([realpath($file)], $resolved);
    }

    #[Test]
    public function resolveReturnsSortedOutput(): void
    {
        $pattern = __DIR__ . '/../Fixtures/*/ext_emconf.php';

        $resolved = (new PathResolver())->resolve([$pattern]);

        $sorted = $resolved;
        sort($sorted);
        self::assertSame($sorted, $resolved);
    }

    #[Test]
    public function resolveThrowsForMissingLiteralPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('/does/not/exist/ext_emconf.php');

        (new PathResolver())->resolve(['/does/not/exist/ext_emconf.php']);
    }

    #[Test]
    public function resolveReturnsEmptyForGlobWithoutMatches(): void
    {
        $pattern = __DIR__ . '/../Fixtures/no_such_dir_*/ext_emconf.php';

        self::assertSame([], (new PathResolver())->resolve([$pattern]));
    }
}
