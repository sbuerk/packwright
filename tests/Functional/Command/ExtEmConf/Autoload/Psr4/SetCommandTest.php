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

final class SetCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:autoload:psr4:set'));
    }

    #[Test]
    public function invalidNamespaceExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

        $exitCode = $tester->execute([
            'namespace' => 'NoTrailingSlash',
            'path' => 'Classes/',
            'paths' => [dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function emptyPathExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

        $exitCode = $tester->execute([
            'namespace' => 'Vendor\\Ext\\',
            'path' => '   ',
            'paths' => [dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function addsNewNamespaceAndRoundTrips(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'AcmeVendor\\NewModule\\',
                'path' => 'Classes/Module/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());

            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            $entry = $reread->extEmConf->autoload->psr4->get('AcmeVendor\\NewModule\\');
            self::assertNotNull($entry);
            self::assertSame('Classes/Module/', $entry->path);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function updatesExistingNamespaceInTarget(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\',
                'path' => 'Sources/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            $entries = $reread->extEmConf->autoload->psr4->all();
            self::assertCount(1, $entries);
            self::assertSame('MyVendor\\MyExtV13\\', $entries[0]->namespace);
            self::assertSame('Sources/', $entries[0]->path);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function unchangedWhenSameValuePresent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\',
                'path' => 'Classes/',
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
    public function crossTargetCollisionInNonInteractiveModeFails(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

            // `MyVendor\MyExtV13\Tests\` is in autoload-dev in the v13_full fixture.
            $exitCode = $tester->execute(
                [
                    'namespace' => 'MyVendor\\MyExtV13\\Tests\\',
                    'path' => 'Classes/',
                    'paths' => [$tempFile],
                    '--typo3-version' => '13',
                ],
                ['interactive' => false],
            );

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('skipped', $tester->getDisplay());
            self::assertSame($original, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function crossTargetCollisionInteractiveAcceptedMovesEntry(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));
            $tester->setInputs(['y']);

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\Tests\\',
                'path' => 'Classes/Test/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            $movedEntry = $reread->extEmConf->autoload->psr4->get('MyVendor\\MyExtV13\\Tests\\');
            self::assertNotNull($movedEntry);
            self::assertSame('Classes/Test/', $movedEntry->path);
            self::assertFalse(
                $reread->extEmConf->autoloadDev->psr4->has('MyVendor\\MyExtV13\\Tests\\'),
                'Namespace should be removed from autoload-dev after move.',
            );
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function crossTargetCollisionInteractiveDeclinedSkips(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));
            $tester->setInputs(['n']);

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\Tests\\',
                'path' => 'Classes/Test/',
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
    public function dryRunDoesNotTouchFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'AcmeVendor\\Module\\',
                'path' => 'Classes/Module/',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alps4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'AcmeVendor\\Module\\',
                'path' => 'Classes/Module/',
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
            self::assertSame('extemconf:autoload:psr4:set', $payload['command']);
            self::assertSame('autoload', $payload['target']);
            self::assertSame('AcmeVendor\\Module\\', $payload['namespace']);
            self::assertSame('Classes/Module/', $payload['path']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
            self::assertStringContainsString("'AcmeVendor\\\\Module\\\\' => 'Classes/Module/'", $payload['files'][0]['generated']);
        } finally {
            @unlink($tempFile);
        }
    }
}
