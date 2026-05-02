<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Service;

use Packwright\ExtEmConf\Service\SiblingComposerReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SiblingComposerReaderTest extends TestCase
{
    #[Test]
    public function extractsAllFourFieldsWhenComposerJsonIsWellFormed(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, json_encode([
            'name' => 'vendor/my-ext',
            'type' => 'typo3-cms-extension',
            'description' => 'An example extension.',
            'extra' => ['typo3/cms' => ['extension-key' => 'my_ext']],
        ], JSON_THROW_ON_ERROR));

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('my_ext', $info->extensionKey);
            self::assertSame('vendor/my-ext', $info->packageName);
            self::assertSame('typo3-cms-extension', $info->packageType);
            self::assertSame('An example extension.', $info->packageDescription);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function returnsAllEmptyWhenComposerJsonIsMissing(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        file_put_contents($extEmConf, "<?php\n");

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
            self::assertSame('', $info->packageName);
            self::assertSame('', $info->packageType);
            self::assertSame('', $info->packageDescription);
        } finally {
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function returnsAllEmptyWhenComposerJsonIsMalformed(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, '{ "name": ');

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
            self::assertSame('', $info->packageName);
            self::assertSame('', $info->packageType);
            self::assertSame('', $info->packageDescription);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function returnsAllEmptyWhenComposerJsonDecodesToNonArray(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, '"hello"');

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
            self::assertSame('', $info->packageName);
            self::assertSame('', $info->packageType);
            self::assertSame('', $info->packageDescription);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function returnsOnlyPackageNameWhenOtherFieldsAreMissing(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, json_encode(['name' => 'vendor/only-name'], JSON_THROW_ON_ERROR));

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
            self::assertSame('vendor/only-name', $info->packageName);
            self::assertSame('', $info->packageType);
            self::assertSame('', $info->packageDescription);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function ignoresNonStringValuesAndReturnsEmptyForThem(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, json_encode([
            'name' => 42,
            'type' => 'library',
            'description' => ['nested' => 'array'],
        ], JSON_THROW_ON_ERROR));

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
            self::assertSame('', $info->packageName);
            self::assertSame('library', $info->packageType);
            self::assertSame('', $info->packageDescription);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function returnsEmptyExtensionKeyWhenExtraIsAbsent(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, json_encode([
            'name' => 'vendor/no-extra',
            'type' => 'typo3-cms-extension',
            'description' => 'No extra block.',
        ], JSON_THROW_ON_ERROR));

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
            self::assertSame('vendor/no-extra', $info->packageName);
            self::assertSame('typo3-cms-extension', $info->packageType);
            self::assertSame('No extra block.', $info->packageDescription);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function returnsEmptyExtensionKeyWhenTypo3CmsBranchIsAbsent(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, json_encode([
            'extra' => ['some-vendor' => ['key' => 'value']],
        ], JSON_THROW_ON_ERROR));

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function returnsEmptyExtensionKeyWhenExtensionKeyIsNonString(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, json_encode([
            'extra' => ['typo3/cms' => ['extension-key' => 123]],
        ], JSON_THROW_ON_ERROR));

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function doesNotCrashWhenExtraIsNotAnArray(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_sibling_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n");
        file_put_contents($composer, json_encode([
            'name' => 'vendor/scalar-extra',
            'extra' => 'not-an-object',
        ], JSON_THROW_ON_ERROR));

        try {
            $info = (new SiblingComposerReader())->read($extEmConf);

            self::assertSame('', $info->extensionKey);
            self::assertSame('vendor/scalar-extra', $info->packageName);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }
}
