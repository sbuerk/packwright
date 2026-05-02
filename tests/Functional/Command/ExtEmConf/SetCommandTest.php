<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf;

use Packwright\Application;
use Packwright\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SetCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:set'));
    }

    #[Test]
    public function noOptionExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:set'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function setVersionAndStatePersistsToDisk(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_set_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:set'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--ext-version' => '2.0.0',
                '--state' => 'stable',
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());
            $rewritten = (string)file_get_contents($tempFile);
            self::assertStringContainsString("'version' => '2.0.0'", $rewritten);
            self::assertStringContainsString("'state' => 'stable'", $rewritten);
            self::assertStringNotContainsString("'state' => 'beta'", $rewritten);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function invalidStateExitsInvalidWithoutTouchingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_set_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:set'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--state' => 'whatever',
            ]);

            self::assertSame(Command::INVALID, $exitCode);
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function invalidCategoryExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:set'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
            '--category' => 'nope',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function validationFailureSkipsFileAndDoesNotWrite(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_set_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:set'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--ext-version' => 'not-a-version',
            ]);

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('skipped', $tester->getDisplay());
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function dryRunDoesNotTouchFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_set_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:set'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--title' => 'Dry-run Title',
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
    public function unchangedActionWhenSetterDoesNotChangeContent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_set_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:set'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--title' => 'V13 Full Extension',
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
    public function strictBlocksWriteOnWarning(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_set_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/deprecated_keys/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:set'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--title' => 'New Title',
                '--typo3-version' => '13',
                '--strict' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('skipped', $tester->getDisplay());
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function jsonOutputShape(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_set_');
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
            $tester = new CommandTester($application->find('extemconf:set'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--ext-version' => '2.5.0',
                '--typo3-version' => '13',
                '--dry-run' => true,
                '--json' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame('extemconf:set', $payload['command']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertFalse($payload['strict']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
            self::assertStringContainsString("'version' => '2.5.0'", $payload['files'][0]['generated']);
        } finally {
            @unlink($tempFile);
        }
    }
}
