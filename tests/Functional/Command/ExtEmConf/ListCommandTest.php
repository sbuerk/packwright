<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf;

use Packwright\Application;
use Packwright\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:list'));
    }

    #[Test]
    public function tableContainsRelativePathTitleAndVersionForFixtureBelowCwd(): void
    {
        $cwd = (string)getcwd();
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php';
        self::assertStringStartsWith($cwd . '/', $fixture);
        $expectedRelative = './' . substr($fixture, strlen($cwd) + 1);

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(['paths' => [$fixture]]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString($expectedRelative, $display);
        self::assertStringContainsString('V13 Full Extension', $display);
        self::assertStringContainsString('1.0.0', $display);
        self::assertStringContainsString('SUCCESS', $display);
    }

    #[Test]
    public function recursiveGlobMatchesEveryNestedFixture(): void
    {
        $pattern = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/Packages/**/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $tester->execute(['paths' => [$pattern], '--json' => true]);

        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertGreaterThanOrEqual(5, count($payload));
        foreach ($payload as $folder => $entry) {
            self::assertStringStartsWith('/', (string)$folder);
            self::assertStringNotContainsString('/ext_emconf.php', (string)$folder);
            self::assertIsArray($entry);
            self::assertSame($folder . '/ext_emconf.php', $entry['file']);
        }
    }

    #[Test]
    public function tableShowsAbsolutePathForFileOutsideCwd(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_list_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, "<?php\n\n\$EM_CONF[\$_EXTKEY] = [\n"
            . "    'title' => 'Outside Cwd',\n"
            . "    'description' => 'desc',\n"
            . "    'version' => '0.1.0',\n"
            . "    'category' => 'misc',\n"
            . "    'state' => 'beta',\n"
            . "];\n");

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:list'));

            $tester->execute(['paths' => [$tempFile]]);

            $display = $tester->getDisplay();
            self::assertStringContainsString($tempFile, $display);
            self::assertStringNotContainsString('./' . $tempFile, $display);
            self::assertStringContainsString('Outside Cwd', $display);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function jsonOutputIsKeyedByFolderWithFileAndComposerFile(): void
    {
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $tester->execute(['paths' => [$fixture], '--json' => true]);

        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $resolvedFile = realpath($fixture);
        self::assertNotFalse($resolvedFile);
        $expectedKey = dirname($resolvedFile);
        self::assertSame([$expectedKey], array_keys($payload));

        $entry = $payload[$expectedKey];
        self::assertIsArray($entry);
        self::assertSame($expectedKey, $entry['path']);
        self::assertSame($resolvedFile, $entry['file']);
        self::assertSame('', $entry['composerFile']);
        self::assertArrayHasKey('data', $entry);
        self::assertArrayHasKey('composer', $entry);
        self::assertArrayHasKey('validation', $entry);
        self::assertSame('V13 Full Extension', $entry['data']['title']);
        self::assertSame('1.0.0', $entry['data']['version']);
        self::assertSame('plugin', $entry['data']['category']);
        self::assertSame('stable', $entry['data']['state']);
        self::assertSame('13.4.0-13.4.99', $entry['data']['constraints']['depends']['typo3']);
        self::assertSame('SUCCESS', $entry['validation']['state']);
        self::assertTrue($entry['validation']['isValid']);
        self::assertSame([], $entry['validation']['issues']);
    }

    #[Test]
    public function jsonEntryForUnparseableFileHasNullDataAndFailureState(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_list_unparseable_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $tempFile = $dir . '/ext_emconf.php';
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:list'));

            $exitCode = $tester->execute(['paths' => [$tempFile], '--json' => true]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertArrayHasKey($dir, $payload);
            $entry = $payload[$dir];
            self::assertSame($dir, $entry['path']);
            self::assertSame($tempFile, $entry['file']);
            self::assertSame('', $entry['composerFile']);
            self::assertNull($entry['data']);
            self::assertSame('FAILURE', $entry['validation']['state']);
            self::assertFalse($entry['validation']['isValid']);
            self::assertNotEmpty($entry['validation']['issues']);
        } finally {
            @unlink($tempFile);
            @rmdir($dir);
        }
    }

    #[Test]
    public function strictExitsOneForFailingFixtureWhileDefaultExitsZero(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_list_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);

            $defaultTester = new CommandTester($application->find('extemconf:list'));
            $defaultExit = $defaultTester->execute(['paths' => [$tempFile]]);
            self::assertSame(Command::SUCCESS, $defaultExit);

            $strictTester = new CommandTester($application->find('extemconf:list'));
            $strictExit = $strictTester->execute(['paths' => [$tempFile], '--strict' => true]);
            self::assertSame(Command::FAILURE, $strictExit);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function noMatchingFilesProducesEmptyJsonObjectAndExitsZero(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(
            input: [
                'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/no_match_*/ext_emconf.php'],
                '--json' => true,
            ],
            options: ['capture_stderr_separately' => true],
        );

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('{}', trim($tester->getDisplay()));
        self::assertStringContainsString('No files matched', $tester->getErrorOutput());
    }

    #[Test]
    public function noMatchingFilesInDefaultModeWritesStderrMessageAndExitsZero(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(
            input: ['paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/no_match_*/ext_emconf.php']],
            options: ['capture_stderr_separately' => true],
        );

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('', trim($tester->getDisplay()));
        self::assertStringContainsString('No files matched', $tester->getErrorOutput());
    }

    #[Test]
    public function missingLiteralPathExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(['paths' => ['/no/such/file/ext_emconf.php']]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function invalidTypo3VersionExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
            '--typo3-version' => '7',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function tableContainsExtractedComposerColumnsForFixtureWithSiblingComposer(): void
    {
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full_with_composer/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $tester->execute(['paths' => [$fixture]]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('v13_full_with_composer', $display);
        self::assertStringContainsString('vendor/v13-full-with-composer', $display);
        self::assertStringContainsString('typo3-cms-extension', $display);
        self::assertStringContainsString('Extension Key', $display);
        self::assertStringContainsString('Package Name', $display);
        self::assertStringContainsString('Package Type', $display);
    }

    #[Test]
    public function jsonContainsComposerBlockAlongsideDataAndValidation(): void
    {
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full_with_composer/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $tester->execute(['paths' => [$fixture], '--json' => true]);

        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $resolvedFile = realpath($fixture);
        self::assertNotFalse($resolvedFile);
        $expectedKey = dirname($resolvedFile);
        $entry = $payload[$expectedKey];
        self::assertIsArray($entry);
        self::assertSame($expectedKey, $entry['path']);
        self::assertSame($resolvedFile, $entry['file']);
        self::assertSame($expectedKey . '/composer.json', $entry['composerFile']);
        self::assertArrayHasKey('composer', $entry);
        self::assertArrayHasKey('data', $entry);
        self::assertArrayHasKey('validation', $entry);

        self::assertSame(
            [
                'extensionKey' => 'v13_full_with_composer',
                'packageName' => 'vendor/v13-full-with-composer',
                'packageType' => 'typo3-cms-extension',
                'packageDescription' => 'Full v13 fixture with sibling composer.json.',
            ],
            $entry['composer'],
        );
    }

    #[Test]
    public function jsonComposerFieldsAreEmptyStringsWhenSiblingComposerIsMissing(): void
    {
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $tester->execute(['paths' => [$fixture], '--json' => true]);

        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $resolvedFile = (string)realpath($fixture);
        $expectedKey = dirname($resolvedFile);
        $entry = $payload[$expectedKey];

        self::assertSame($expectedKey, $entry['path']);
        self::assertSame($resolvedFile, $entry['file']);
        self::assertSame('', $entry['composerFile']);
        self::assertSame(
            [
                'extensionKey' => '',
                'packageName' => '',
                'packageType' => '',
                'packageDescription' => '',
            ],
            $entry['composer'],
        );
        self::assertSame('SUCCESS', $entry['validation']['state']);
    }

    #[Test]
    public function jsonContainsComposerBlockEvenWhenExtEmConfFailsToParse(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_list_broken_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, '<?php this is not valid PHP');
        file_put_contents($composer, json_encode([
            'name' => 'vendor/broken-emconf',
            'type' => 'typo3-cms-extension',
            'description' => 'Composer side is fine; ext_emconf.php is broken.',
            'extra' => ['typo3/cms' => ['extension-key' => 'broken_emconf']],
        ], JSON_THROW_ON_ERROR));

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:list'));

            $tester->execute(['paths' => [$extEmConf], '--json' => true]);

            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            $entry = $payload[$dir];
            self::assertSame($dir, $entry['path']);
            self::assertSame($extEmConf, $entry['file']);
            self::assertSame($composer, $entry['composerFile']);
            self::assertNull($entry['data']);
            self::assertSame('FAILURE', $entry['validation']['state']);
            self::assertSame('vendor/broken-emconf', $entry['composer']['packageName']);
            self::assertSame('broken_emconf', $entry['composer']['extensionKey']);
            self::assertSame('typo3-cms-extension', $entry['composer']['packageType']);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }

    #[Test]
    public function jsonComposerFieldsAreEmptyAndValidationStaysSuccessOnMalformedComposerJson(): void
    {
        $dir = sys_get_temp_dir() . '/packwright_list_malformed_' . bin2hex(random_bytes(6));
        mkdir($dir);
        $extEmConf = $dir . '/ext_emconf.php';
        $composer = $dir . '/composer.json';
        file_put_contents($extEmConf, "<?php\n\n\$EM_CONF[\$_EXTKEY] = [\n"
            . "    'title' => 'Malformed Composer Sibling',\n"
            . "    'description' => 'Valid ext_emconf with malformed composer.json next to it.',\n"
            . "    'version' => '0.1.0',\n"
            . "    'category' => 'misc',\n"
            . "    'state' => 'beta',\n"
            . "];\n");
        file_put_contents($composer, '{ "name": ');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:list'));

            $strictExit = $tester->execute(['paths' => [$extEmConf], '--json' => true, '--strict' => true]);

            self::assertSame(Command::SUCCESS, $strictExit);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            $entry = $payload[$dir];
            self::assertSame($dir, $entry['path']);
            self::assertSame($extEmConf, $entry['file']);
            self::assertSame($composer, $entry['composerFile']);
            self::assertSame(
                [
                    'extensionKey' => '',
                    'packageName' => '',
                    'packageType' => '',
                    'packageDescription' => '',
                ],
                $entry['composer'],
            );
            self::assertSame('SUCCESS', $entry['validation']['state']);
        } finally {
            @unlink($composer);
            @unlink($extEmConf);
            @rmdir($dir);
        }
    }
}
