<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\Command\ExtEmConf;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\Application;
use SBReleaser\Kernel;
use Symfony\Component\Console\Command\Command;
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
        $tempFile = tempnam(sys_get_temp_dir(), 'sb_releaser_normalize_');
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
        $tempFile = tempnam(sys_get_temp_dir(), 'sb_releaser_normalize_');
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
        $tempFile = tempnam(sys_get_temp_dir(), 'sb_releaser_normalize_');
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
        $tempFile = tempnam(sys_get_temp_dir(), 'sb_releaser_normalize_');
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
        $tempFile = tempnam(sys_get_temp_dir(), 'sb_releaser_normalize_');
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
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
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
}
