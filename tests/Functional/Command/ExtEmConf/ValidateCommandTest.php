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

final class ValidateCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:validate'));
    }

    #[Test]
    public function executeReturnsSuccessForMinimalFixture(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('SUCCESS', $tester->getDisplay());
    }

    #[Test]
    public function executeReturnsWarningExitCodeZeroForUnknownKeysFixture(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('WARNING', $tester->getDisplay());
    }

    #[Test]
    public function executeReturnsFailureExitCodeOneForUnreadableFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_validate_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:validate'));

            $exitCode = $tester->execute(['paths' => [$tempFile]]);

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('FAILURE', $tester->getDisplay());
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function executeReturnsInvalidExitCodeForMissingPath(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute(['paths' => ['/no/such/file/ext_emconf.php']]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function executeReturnsInvalidExitCodeForUnknownTypo3Version(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
            '--typo3-version' => '7',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function executeReturnsInvalidExitCodeForGlobWithoutMatches(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/no_match_*/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function jsonOutputProducesParseablePayload(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php'],
            '--typo3-version' => '13',
            '--json' => true,
        ]);

        $display = trim($tester->getDisplay());
        self::assertStringNotContainsString("\n", $display, 'JSON output must be compact (single line)');
        $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('extemconf:validate', $payload['command']);
        self::assertSame(13, $payload['typo3Version']);
        self::assertCount(1, $payload['files']);
        self::assertSame('WARNING', $payload['files'][0]['state']);
        self::assertGreaterThan(0, $payload['files'][0]['warningCount']);
        self::assertSame(['total' => 1, 'success' => 0, 'warning' => 1, 'failure' => 0], $payload['summary']);
    }

    #[Test]
    public function verboseOutputListsIssuePaths(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute(
            input: ['paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php']],
            options: ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        self::assertStringContainsString('WARNING', $tester->getDisplay());
        self::assertStringContainsString('unknown_custom_key', $tester->getDisplay());
    }

    #[Test]
    public function tableShowsRelativePathForFixtureBelowCwd(): void
    {
        $cwd = (string)getcwd();
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php';
        self::assertStringStartsWith($cwd . '/', $fixture);
        $expectedRelative = './' . substr($fixture, strlen($cwd) + 1);

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute(['paths' => [$fixture]]);

        self::assertStringContainsString($expectedRelative, $tester->getDisplay());
    }

    #[Test]
    public function tableShowsAbsolutePathForTempFileOutsideCwd(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_validate_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:validate'));

            $tester->execute(['paths' => [$tempFile]]);

            self::assertStringContainsString($tempFile, $tester->getDisplay());
            self::assertStringNotContainsString('./' . $tempFile, $tester->getDisplay());
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function jsonOutputEmitsAbsolutePath(): void
    {
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute([
            'paths' => [$fixture],
            '--json' => true,
        ]);

        $display = trim($tester->getDisplay());
        self::assertStringNotContainsString("\n", $display, 'JSON output must be compact (single line)');
        $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $expected = realpath($fixture);
        self::assertNotFalse($expected);
        self::assertSame($expected, $payload['files'][0]['path']);
        self::assertStringStartsWith('/', $expected);
    }

    #[Test]
    public function failOnWarningExitsOneForWarningFixture(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php'],
            '--fail-on-warning' => true,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('WARNING', $tester->getDisplay());
    }

    #[Test]
    public function failOnWarningExitsZeroForCleanFixture(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
            '--fail-on-warning' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function failOnWarningStillExitsOneForFailure(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_validate_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:validate'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--fail-on-warning' => true,
            ]);

            self::assertSame(Command::FAILURE, $exitCode);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function withoutFailOnWarningWarningStillExitsZero(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function hintIsShownWhenIssuesExistAndNotVerbose(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php'],
        ]);

        self::assertStringContainsString('re-run with -v', $tester->getDisplay());
    }

    #[Test]
    public function hintIsHiddenInVerboseMode(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute(
            input: ['paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php']],
            options: ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        self::assertStringNotContainsString('re-run with -v', $tester->getDisplay());
    }

    #[Test]
    public function hintIsHiddenWhenNoIssues(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
        ]);

        self::assertStringNotContainsString('re-run with -v', $tester->getDisplay());
    }

    #[Test]
    public function hintIsHiddenInJsonMode(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:validate'));

        $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/invalid_keys/ext_emconf.php'],
            '--json' => true,
        ]);

        self::assertStringNotContainsString('re-run with -v', $tester->getDisplay());
        $display = trim($tester->getDisplay());
        self::assertStringNotContainsString("\n", $display, 'JSON output must be compact (single line)');
        $payload = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
    }
}
