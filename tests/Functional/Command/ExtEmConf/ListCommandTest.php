<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf;

use Packwright\Application;
use Packwright\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:list'));
    }

    #[Test]
    public function tableContainsRelativePathTitleAndVersionForFixtureBelowCwd(): void
    {
        $cwd = (string)getcwd();
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php';
        self::assertStringStartsWith($cwd . '/', $fixture);
        $expectedRelative = './' . substr($fixture, strlen($cwd) + 1);

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(['paths' => [$fixture]]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString($expectedRelative, $display);
        self::assertStringContainsString('V13 Full Extension', $display);
        self::assertStringContainsString('1.0.0', $display);
        self::assertStringContainsString('SUCCESS', $display);
    }

    #[Test]
    public function recursiveGlobMatchesEveryNestedFixture(): void
    {
        $pattern = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/Packages/**/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $tester->execute(['paths' => [$pattern], '--json' => true]);

        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertGreaterThanOrEqual(5, count($payload));
        foreach (array_keys($payload) as $absolute) {
            self::assertStringStartsWith('/', (string)$absolute);
            self::assertStringEndsWith('/ext_emconf.php', (string)$absolute);
        }
    }

    #[Test]
    public function tableShowsAbsolutePathForFileOutsideCwd(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_list_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, "<?php\n\n\$EM_CONF[\$_EXTKEY] = [\n"
            . "    'title' => 'Outside Cwd',\n"
            . "    'description' => 'desc',\n"
            . "    'version' => '0.1.0',\n"
            . "    'category' => 'misc',\n"
            . "    'state' => 'beta',\n"
            . "];\n");

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:list'));

            $tester->execute(['paths' => [$tempFile]]);

            $display = $tester->getDisplay();
            self::assertStringContainsString($tempFile, $display);
            self::assertStringNotContainsString('./' . $tempFile, $display);
            self::assertStringContainsString('Outside Cwd', $display);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function jsonOutputIsKeyedByAbsolutePathWithDataAndValidation(): void
    {
        $fixture = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $tester->execute(['paths' => [$fixture], '--json' => true]);

        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $expectedKey = realpath($fixture);
        self::assertNotFalse($expectedKey);
        self::assertSame([$expectedKey], array_keys($payload));

        $entry = $payload[$expectedKey];
        self::assertIsArray($entry);
        self::assertArrayHasKey('data', $entry);
        self::assertArrayHasKey('validation', $entry);
        self::assertSame('V13 Full Extension', $entry['data']['title']);
        self::assertSame('1.0.0', $entry['data']['version']);
        self::assertSame('plugin', $entry['data']['category']);
        self::assertSame('stable', $entry['data']['state']);
        self::assertSame('13.4.0-13.4.99', $entry['data']['constraints']['depends']['typo3']);
        self::assertSame('SUCCESS', $entry['validation']['state']);
        self::assertTrue($entry['validation']['isValid']);
        self::assertSame([], $entry['validation']['issues']);
    }

    #[Test]
    public function jsonEntryForUnparseableFileHasNullDataAndFailureState(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_list_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:list'));

            $exitCode = $tester->execute(['paths' => [$tempFile], '--json' => true]);

            self::assertSame(Command::SUCCESS, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertArrayHasKey($tempFile, $payload);
            self::assertNull($payload[$tempFile]['data']);
            self::assertSame('FAILURE', $payload[$tempFile]['validation']['state']);
            self::assertFalse($payload[$tempFile]['validation']['isValid']);
            self::assertNotEmpty($payload[$tempFile]['validation']['issues']);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function strictExitsOneForFailingFixtureWhileDefaultExitsZero(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_list_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);

            $defaultTester = new CommandTester($application->find('extemconf:list'));
            $defaultExit = $defaultTester->execute(['paths' => [$tempFile]]);
            self::assertSame(Command::SUCCESS, $defaultExit);

            $strictTester = new CommandTester($application->find('extemconf:list'));
            $strictExit = $strictTester->execute(['paths' => [$tempFile], '--strict' => true]);
            self::assertSame(Command::FAILURE, $strictExit);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function noMatchingFilesProducesEmptyJsonObjectAndExitsZero(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(
            input: [
                'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/no_match_*/ext_emconf.php'],
                '--json' => true,
            ],
            options: ['capture_stderr_separately' => true],
        );

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('{}', trim($tester->getDisplay()));
        self::assertStringContainsString('No files matched', $tester->getErrorOutput());
    }

    #[Test]
    public function noMatchingFilesInDefaultModeWritesStderrMessageAndExitsZero(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(
            input: ['paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/no_match_*/ext_emconf.php']],
            options: ['capture_stderr_separately' => true],
        );

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('', trim($tester->getDisplay()));
        self::assertStringContainsString('No files matched', $tester->getErrorOutput());
    }

    #[Test]
    public function missingLiteralPathExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute(['paths' => ['/no/such/file/ext_emconf.php']]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function invalidTypo3VersionExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:list'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
            '--typo3-version' => '7',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }
}
