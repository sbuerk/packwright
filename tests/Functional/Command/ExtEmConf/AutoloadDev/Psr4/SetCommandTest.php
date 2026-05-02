<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf\AutoloadDev\Psr4;

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

        self::assertTrue($application->has('extemconf:autoload-dev:psr4:set'));
    }

    #[Test]
    public function invalidNamespaceExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));

        $exitCode = $tester->execute([
            'namespace' => 'NoTrailingSlash',
            'path' => 'Tests/',
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
        $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));

        $exitCode = $tester->execute([
            'namespace' => 'Vendor\\Ext\\Tests\\',
            'path' => '   ',
            'paths' => [dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function addsNewNamespaceAndRoundTrips(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_advs4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'AcmeVendor\\NewTests\\',
                'path' => 'Tests/Unit/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());

            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            $entry = $reread->extEmConf->autoloadDev->psr4->get('AcmeVendor\\NewTests\\');
            self::assertNotNull($entry);
            self::assertSame('Tests/Unit/', $entry->path);
            // autoload untouched
            self::assertTrue($reread->extEmConf->autoload->psr4->has('MyVendor\\MyExtV13\\'));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function updatesExistingNamespaceInTarget(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_advs4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\Tests\\',
                'path' => 'Tests/Functional/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            $entries = $reread->extEmConf->autoloadDev->psr4->all();
            self::assertCount(1, $entries);
            self::assertSame('MyVendor\\MyExtV13\\Tests\\', $entries[0]->namespace);
            self::assertSame('Tests/Functional/', $entries[0]->path);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function crossTargetCollisionInNonInteractiveModeFails(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_advs4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));

            // `MyVendor\MyExtV13\` is in autoload in the v13_full fixture.
            $exitCode = $tester->execute(
                [
                    'namespace' => 'MyVendor\\MyExtV13\\',
                    'path' => 'Tests/',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_advs4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));
            $tester->setInputs(['y']);

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\',
                'path' => 'Tests/Stub/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            $movedEntry = $reread->extEmConf->autoloadDev->psr4->get('MyVendor\\MyExtV13\\');
            self::assertNotNull($movedEntry);
            self::assertSame('Tests/Stub/', $movedEntry->path);
            self::assertFalse(
                $reread->extEmConf->autoload->psr4->has('MyVendor\\MyExtV13\\'),
                'Namespace should be removed from autoload after move.',
            );
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function crossTargetCollisionInteractiveDeclinedSkips(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_advs4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));
            $tester->setInputs(['n']);

            $exitCode = $tester->execute([
                'namespace' => 'MyVendor\\MyExtV13\\',
                'path' => 'Tests/Stub/',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_advs4_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'AcmeVendor\\NewTests\\',
                'path' => 'Tests/Unit/',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_advs4_');
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
            $tester = new CommandTester($application->find('extemconf:autoload-dev:psr4:set'));

            $exitCode = $tester->execute([
                'namespace' => 'AcmeVendor\\NewTests\\',
                'path' => 'Tests/Unit/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
                '--json' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame('extemconf:autoload-dev:psr4:set', $payload['command']);
            self::assertSame('autoload-dev', $payload['target']);
            self::assertSame('AcmeVendor\\NewTests\\', $payload['namespace']);
            self::assertSame('Tests/Unit/', $payload['path']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
            self::assertStringContainsString("'AcmeVendor\\\\NewTests\\\\' => 'Tests/Unit/'", $payload['files'][0]['generated']);
        } finally {
            @unlink($tempFile);
        }
    }
}
