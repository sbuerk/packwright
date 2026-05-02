<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf;

use Packwright\Application;
use Packwright\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class NormalizeCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:normalize'));
    }

    #[Test]
    public function dryRunReportsChangesWithoutWritingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('dry-run', $tester->getDisplay());
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function writeOverridesFileForDeprecatedKeysFixture(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());

            $rewritten = (string)file_get_contents($tempFile);
            self::assertNotSame($original, $rewritten);
            self::assertStringNotContainsString('clearCacheOnLoad', $rewritten);
            self::assertStringNotContainsString('uploadfolder', $rewritten);
            self::assertStringNotContainsString('createDirs', $rewritten);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function unchangedActionForFixtureWithoutDeprecatedFields(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('unchanged', $tester->getDisplay());
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function skippedActionAndExitOneForMalformedInput(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');
        $beforeContent = (string)file_get_contents($tempFile);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $exitCode = $tester->execute(['paths' => [$tempFile]]);

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('skipped', $tester->getDisplay());
            self::assertSame($beforeContent, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function jsonOutputContainsGeneratedSourceForWrittenFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
                '--json' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $display = trim($tester->getDisplay());
            self::assertStringNotContainsString("\n", $display, 'JSON output must be compact (single line)');
            $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame('extemconf:normalize', $payload['command']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertTrue($payload['files'][0]['hasChanges']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
            self::assertIsString($payload['files'][0]['generated']);
            self::assertStringStartsWith('<?php', $payload['files'][0]['generated']);
            self::assertStringNotContainsString('clearCacheOnLoad', $payload['files'][0]['generated']);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function tableShowsRelativePathForFixtureBelowCwd(): void
    {
        $cwd = (string)getcwd();
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php';
        self::assertStringStartsWith($cwd . '/', $fixture);
        $expectedRelative = './' . substr($fixture, strlen($cwd) + 1);

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:normalize'));

        $tester->execute([
            'paths' => [$fixture],
            '--typo3-version' => '13',
            '--dry-run' => true,
        ]);

        self::assertStringContainsString($expectedRelative, $tester->getDisplay());
    }

    #[Test]
    public function tableShowsAbsolutePathForTempFileOutsideCwd(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
            ]);

            self::assertStringContainsString($tempFile, $tester->getDisplay());
            self::assertStringNotContainsString('./' . $tempFile, $tester->getDisplay());
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function jsonOutputEmitsAbsolutePath(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
                '--json' => true,
            ]);

            $display = trim($tester->getDisplay());
            self::assertStringNotContainsString("\n", $display, 'JSON output must be compact (single line)');
            $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            $expected = realpath($tempFile);
            self::assertNotFalse($expected);
            self::assertSame($expected, $payload['files'][0]['path']);
            self::assertStringStartsWith('/', $expected);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function hintIsShownWhenDroppedItemsExistAndNotVerbose(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
            ]);

            self::assertStringContainsString('re-run with -v', $tester->getDisplay());
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function hintIsHiddenInVerboseMode(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $tester->execute(
                input: [
                    'paths' => [$tempFile],
                    '--typo3-version' => '13',
                    '--dry-run' => true,
                ],
                options: ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
            );

            self::assertStringNotContainsString('re-run with -v', $tester->getDisplay());
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function hintIsHiddenWhenNoDroppedItems(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_normalize_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:normalize'));

            $tester->execute([
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
            ]);

            self::assertStringNotContainsString('re-run with -v', $tester->getDisplay());
        } finally {
            @unlink($tempFile);
        }
    }
}
