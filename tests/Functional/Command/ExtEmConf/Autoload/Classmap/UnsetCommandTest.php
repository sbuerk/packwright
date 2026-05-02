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

final class UnsetCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:autoload:classmap:unset'));
    }

    #[Test]
    public function emptyPathExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 6));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:autoload:classmap:unset'));

        $exitCode = $tester->execute([
            'path' => '   ',
            'paths' => [dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function removesExistingPathFromAutoload(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcu_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, <<<'PHP'
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Classmap Fixture',
    'description' => 'Classmap unset coverage.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-13.4.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'classmap' => [
            'Resources/PHP/Polyfills/',
            'Resources/PHP/Compat/',
        ],
    ],
];
PHP);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:unset'));

            $exitCode = $tester->execute([
                'path' => 'Resources/PHP/Polyfills/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('written', $tester->getDisplay());

            $reread = (new ExtEmConfReader())->read($tempFile);
            self::assertNotNull($reread->extEmConf);
            self::assertFalse($reread->extEmConf->autoload->classmap->has('Resources/PHP/Polyfills/'));
            self::assertTrue($reread->extEmConf->autoload->classmap->has('Resources/PHP/Compat/'));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function unchangedWhenPathAbsent(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcu_');
        self::assertNotFalse($tempFile);
        $original = (string)file_get_contents(
            dirname(__DIR__, 4) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php',
        );
        file_put_contents($tempFile, $original);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:unset'));

            $exitCode = $tester->execute([
                'path' => 'NotPresent/',
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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcu_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, <<<'PHP'
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Classmap Fixture',
    'description' => 'Classmap dry-run coverage.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-13.4.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'classmap' => [
            'Resources/PHP/Polyfills/',
        ],
    ],
];
PHP);
        $original = (string)file_get_contents($tempFile);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:unset'));

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
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_alcu_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, <<<'PHP'
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Classmap Fixture',
    'description' => 'Classmap json coverage.',
    'version' => '1.0.0',
    'category' => 'plugin',
    'state' => 'stable',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-13.4.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'classmap' => [
            'Resources/PHP/Polyfills/',
        ],
    ],
];
PHP);

        try {
            $kernel = new Kernel(dirname(__DIR__, 6));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:autoload:classmap:unset'));

            $exitCode = $tester->execute([
                'path' => 'Resources/PHP/Polyfills/',
                'paths' => [$tempFile],
                '--typo3-version' => '13',
                '--dry-run' => true,
                '--json' => true,
            ]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame('extemconf:autoload:classmap:unset', $payload['command']);
            self::assertSame('autoload', $payload['target']);
            self::assertSame('Resources/PHP/Polyfills/', $payload['classmapPath']);
            self::assertSame(13, $payload['typo3Version']);
            self::assertTrue($payload['dryRun']);
            self::assertCount(1, $payload['files']);
            self::assertSame('dry-run', $payload['files'][0]['action']);
        } finally {
            @unlink($tempFile);
        }
    }
}
