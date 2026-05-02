<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf\Constraints;

use Packwright\Application;
use Packwright\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UnsetCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 5));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:constraints:unset'));
    }

    #[Test]
    public function invalidTypeExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 5));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:constraints:unset'));

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
        $tester = new CommandTester($application->find('extemconf:constraints:unset'));

        $exitCode = $tester->execute([
            'type' => 'depends',
            'extension-key' => 'Bad-Key',
            'paths' => [dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function removesExistingEntry(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cunset_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:unset'));

            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'php',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());
            $rewritten = (string)file_get_contents($tempFile);
            self::assertStringNotContainsString("'php' =>", $rewritten);
            self::assertStringContainsString("'typo3' =>", $rewritten);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function missingEntryIsIdempotent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cunset_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:unset'));

            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'doesnotexist',
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
    public function dryRunDoesNotTouchFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cunset_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 3) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 5));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:constraints:unset'));

            $exitCode = $tester->execute([
                'type' => 'depends',
                'extension-key' => 'php',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_cunset_');
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
            $tester = new CommandTester($application->find('extemconf:constraints:unset'));

            $exitCode = $tester->execute([
                'type' => 'suggests',
                'extension-key' => 'news',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
                '--json' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame('extemconf:constraints:unset', $payload['command']);
            self::assertSame('suggests', $payload['type']);
            self::assertSame('news', $payload['extensionKey']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
            self::assertStringNotContainsString("'news' =>", (string)$payload['files'][0]['generated']);
        } finally {
            @unlink($tempFile);
        }
    }
}
