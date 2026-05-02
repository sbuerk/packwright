<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf\Constraints;

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
        $kernel = new Kernel(dirname(__DIR__, 5));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:constraints:set'));
    }

    #[Test]
    public function invalidTypeExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 5));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:constraints:set'));

        $exitCode = $tester->execute([
            'type' => 'requires',
            'extension-key' => 'news',
            'paths' => [dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function invalidExtensionKeyExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 5));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:constraints:set'));

        $exitCode = $tester->execute([
            'type' => 'depends',
            'extension-key' => 'Bad-Key',
            'paths' => [dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function invalidVersionStringExitsInvalidWithoutTouchingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cset_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:set'));

            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'extb',
                'paths' => [$tempFile],
                '--constraint' => 'totally-not-a-version',
            ]);

            self::assertSame(Command::INVALID, $exitCode);
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function emptyVersionWritesAllVersionsEntry(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cset_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:set'));

            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'extb',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());
            $rewritten = (string)file_get_contents($tempFile);
            self::assertStringContainsString("'extb' => ''", $rewritten);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function rangeVersionIsPersisted(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cset_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:set'));

            $exitCode = $tester->execute([
                'type' => 'conflicts',
                'extension-key' => 'oldext',
                'paths' => [$tempFile],
                '--constraint' => '1.0.0-2.5.99',
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $rewritten = (string)file_get_contents($tempFile);
            self::assertStringContainsString("'oldext' => '1.0.0-2.5.99'", $rewritten);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function crossKeyCollisionPerFileFailureNoWrite(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cset_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:set'));

            // `news` is in `suggests` in the v13_full fixture; trying to add to `depends` must fail.
            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'news',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('skipped', $tester->getDisplay());
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function updateExistingKeyChangesVersion(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cset_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:set'));

            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'typo3',
                'paths' => [$tempFile],
                '--constraint' => '13.4.0-13.4.99',
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $rewritten = (string)file_get_contents($tempFile);
            self::assertStringContainsString("'typo3' => '13.4.0-13.4.99'", $rewritten);
            // No duplicate entries
            self::assertSame(1, substr_count($rewritten, "'typo3' =>"));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function dryRunDoesNotTouchFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cset_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:set'));

            $exitCode = $tester->execute([
                'type' => 'suggests',
                'extension-key' => 'newhint',
                'paths' => [$tempFile],
                '--dry-run' => true,
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('dry-run', $tester->getDisplay());
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function jsonOutputShape(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cset_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:set'));

            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'extb',
                'paths' => [$tempFile],
                '--constraint' => '*',
                '--typo3-version' => '13',
                '--dry-run' => true,
                '--json' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame('extemconf:constraints:set', $payload['command']);
            self::assertSame('depends', $payload['type']);
            self::assertSame('extb', $payload['extensionKey']);
            self::assertSame('*', $payload['version']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
            self::assertStringContainsString("'extb' => ''", $payload['files'][0]['generated']);
        } finally {
            @unlink($tempFile);
        }
    }
}
