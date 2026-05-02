<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\Command\ExtEmConf;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\Application;
use SBReleaser\Kernel;
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
        $tempFile = tempnam(sys_get_temp_dir(), 'sb_releaser_validate_');
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

        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
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
}
