<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\ExtEmConf\Service;

use Packwright\ExtEmConf\Service\PathResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        // 10 flat fixture extensions plus 7 files under Packages/ (5 at the
        // package-root depth, 2 at deeper Tests/.../Fixtures depth).
        self::assertCount(17, $resolved);
    }

    #[Test]
    public function resolveTwoStarGlobIsBoundedToOneSegmentEach(): void
    {
        $pattern = __DIR__ . '/../Fixtures/Packages/*/*/ext_emconf.php';

        $resolved = (new PathResolver())->resolve([$pattern]);

        $expected = [
            realpath(__DIR__ . '/../Fixtures/Packages/one/package-1/ext_emconf.php'),
            realpath(__DIR__ . '/../Fixtures/Packages/one/package-2/ext_emconf.php'),
            realpath(__DIR__ . '/../Fixtures/Packages/three/package-4/ext_emconf.php'),
            realpath(__DIR__ . '/../Fixtures/Packages/two/package-2/ext_emconf.php'),
            realpath(__DIR__ . '/../Fixtures/Packages/two/package-3/ext_emconf.php'),
        ];
        self::assertSame($expected, $resolved);
        foreach ($resolved as $path) {
            self::assertStringNotContainsString('/Tests/', $path);
        }
    }

    #[Test]
    public function resolveGlobExpandsMatchingFiles(): void
    {
        $pattern = __DIR__ . '/../Fixtures/*/ext_emconf.php';

        $resolved = (new PathResolver())->resolve([$pattern]);

        self::assertCount(10, $resolved);
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
