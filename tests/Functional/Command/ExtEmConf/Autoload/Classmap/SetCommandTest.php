<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf\Autoload\Classmap;

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

        self::assertTrue($application->has('extemconf:autoload:classmap:set'));
    }

    #[Test]
    public function emptyPathExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:autoload:classmap:set'));

        $exitCode = $tester->execute([
            'path' => '   ',
            'paths' => [dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function addsClassmapPathAndRoundTrips(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcs_');
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
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:set'));

            $exitCode = $tester->execute([
                'path' => 'Resources/PHP/Polyfills/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());

            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            self::assertTrue($reread->extEmConf->autoload->classmap->has('Resources/PHP/Polyfills/'));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function unchangedWhenPathAlreadyPresent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcs_');
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
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:set'));

            $firstExit = $tester->execute([
                'path' => 'Resources/PHP/Polyfills/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);
            self::assertSame(Command::SUCCESS, $firstExit);
            self::assertStringContainsString('written', $tester->getDisplay());

            $afterFirstWrite = (string)file_get_contents($tempFile);

            $secondExit = $tester->execute([
                'path' => 'Resources/PHP/Polyfills/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);
            self::assertSame(Command::SUCCESS, $secondExit);
            self::assertStringContainsString('unchanged', $tester->getDisplay());
            self::assertSame($afterFirstWrite, (string)file_get_contents($tempFile));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function crossTargetCollisionInNonInteractiveModeFails(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcs_');
        self::assertNotFalse($tempFile);
        $sourceDevClassmap = <<<'PHP'
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Classmap Collision Fixture',
    'description' => 'Classmap path lives in autoload-dev, set into autoload should fail non-interactively.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-13.4.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload-dev' => [
        'classmap' => [
            'Tests/Fixtures/',
        ],
    ],
];
PHP;
        file_put_contents($tempFile, $sourceDevClassmap);
        $original = (string)file_get_contents($tempFile);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:set'));

            $exitCode = $tester->execute(
                [
                    'path' => 'Tests/Fixtures/',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcs_');
        self::assertNotFalse($tempFile);
        $sourceDevClassmap = <<<'PHP'
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Classmap Collision Fixture',
    'description' => 'Classmap path lives in autoload-dev; move accepted.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-13.4.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload-dev' => [
        'classmap' => [
            'Tests/Fixtures/',
        ],
    ],
];
PHP;
        file_put_contents($tempFile, $sourceDevClassmap);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:set'));
            $tester->setInputs(['y']);

            $exitCode = $tester->execute([
                'path' => 'Tests/Fixtures/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            self::assertTrue($reread->extEmConf->autoload->classmap->has('Tests/Fixtures/'));
            self::assertFalse($reread->extEmConf->autoloadDev->classmap->has('Tests/Fixtures/'));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function dryRunDoesNotTouchFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcs_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:set'));

            $exitCode = $tester->execute([
                'path' => 'Resources/PHP/Polyfills/',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcs_');
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
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:set'));

            $exitCode = $tester->execute([
                'path' => 'Resources/PHP/Polyfills/',
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
            self::assertSame('extemconf:autoload:classmap:set', $payload['command']);
            self::assertSame('autoload', $payload['target']);
            self::assertSame('Resources/PHP/Polyfills/', $payload['classmapPath']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
            self::assertStringContainsString("'Resources/PHP/Polyfills/'", $payload['files'][0]['generated']);
        } finally {
            @unlink($tempFile);
        }
    }
}
