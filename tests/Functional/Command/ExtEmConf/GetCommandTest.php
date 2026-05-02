<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf;

use Packwright\Application;
use Packwright\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GetCommandTest extends TestCase
{
    #[Test]
    public function commandIsRegistered(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        self::assertTrue($application->has('extemconf:get'));
    }

    #[Test]
    public function defaultPrintsAllSimpleFieldsForMinimalFixture(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:get'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('title', $display);
        self::assertStringContainsString('Minimal Extension', $display);
        self::assertStringContainsString('version', $display);
        self::assertStringContainsString('0.1.0', $display);
        self::assertStringContainsString('category', $display);
        self::assertStringContainsString('misc', $display);
        self::assertStringContainsString('state', $display);
        self::assertStringContainsString('beta', $display);
        self::assertStringContainsString('author_email', $display);
    }

    #[Test]
    public function fieldFilterRestrictsOutput(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:get'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
            '--field' => ['version', 'state'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('version', $display);
        self::assertStringContainsString('0.1.0', $display);
        self::assertStringContainsString('state', $display);
        self::assertStringContainsString('beta', $display);
        self::assertStringNotContainsString('Minimal Extension', $display);
    }

    #[Test]
    public function unknownFieldExitsInvalidWithoutReadingFiles(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:get'));

        $exitCode = $tester->execute(
            input: [
                'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
                '--field' => ['title', 'nope'],
            ],
            options: ['capture_stderr_separately' => true],
        );

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Unknown field(s): nope', $tester->getErrorOutput());
    }

    #[Test]
    public function jsonOutputShape(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:get'));

        $exitCode = $tester->execute([
            'paths' => [dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php'],
            '--field' => ['title', 'state'],
            '--json' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('extemconf:get', $payload['command']);
        self::assertCount(1, $payload['files']);
        self::assertArrayHasKey('path', $payload['files'][0]);
        self::assertSame('Minimal Extension', $payload['files'][0]['fields']['title']);
        self::assertSame('beta', $payload['files'][0]['fields']['state']);
        self::assertArrayNotHasKey('version', $payload['files'][0]['fields']);
    }

    #[Test]
    public function multiFileEachAppearsAsSeparateBlock(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:get'));

        $fixtureA = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php';
        $fixtureB = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php';

        $exitCode = $tester->execute([
            'paths' => [$fixtureA, $fixtureB],
            '--field' => ['title'],
            '--json' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertCount(2, $payload['files']);
    }

    #[Test]
    public function missingPathExitsInvalid(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);
        $tester = new CommandTester($application->find('extemconf:get'));

        $exitCode = $tester->execute([
            'paths' => ['/nonexistent/path/ext_emconf.php'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    #[Test]
    public function malformedFileReturnsFailureExitWithErrorRow(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_get_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, '<?php this is not valid PHP');

        try {
            $kernel = new Kernel(dirname(__DIR__, 4));
            /** @var Application $application */
            $application = $kernel->createContainer()->get(Application::class);
            $tester = new CommandTester($application->find('extemconf:get'));

            $exitCode = $tester->execute([
                'paths' => [$tempFile],
                '--json' => true,
            ]);

            self::assertSame(Command::FAILURE, $exitCode);
            $payload = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertArrayHasKey('error', $payload['files'][0]);
            self::assertArrayNotHasKey('fields', $payload['files'][0]);
        } finally {
            @unlink($tempFile);
        }
    }
}
