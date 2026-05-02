<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf\Autoload\Psr4;

use Packwright\Application;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
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
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:autoload:psr4:unset'));
    }

    #[Test]
    public function invalidNamespaceExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:autoload:psr4:unset'));

        $exitCode = $tester->execute([
            'namespace' => 'NoTrailingSlash',
            'paths' => [dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function removesExistingNamespaceFromAutoload(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alpu4_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:unset'));

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());
            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            self::assertFalse($reread->extEmConf->autoload->psr4->has('MyVendor\\MyExtV13\\'));
            // autoload-dev untouched
            self::assertTrue(
                $reread->extEmConf->autoloadDev->psr4->has('MyVendor\\MyExtV13\\Tests\\'),
            );
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function unchangedWhenNamespaceAbsent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alpu4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:unset'));

            $exitCode = $tester->execute([
                'namespace' => 'AbsolutelyNotPresent\\Ns\\',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alpu4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:unset'));

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alpu4_');
        self::assertNotFalse($tempFile);
        file_put_contents(
            $tempFile,
            (string)file_get_contents(
                dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
            ),
        );

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:unset'));

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\',
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
            self::assertSame('extemconf:autoload:psr4:unset', $payload['command']);
            self::assertSame('autoload', $payload['target']);
            self::assertSame('MyVendor\\MyExtV13\\', $payload['namespace']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
        } finally {
            @unlink($tempFile);
        }
    }
}
