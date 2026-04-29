<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\Command;

use PHPUnit\Framework\TestCase;
use SBReleaser\Application;
use SBReleaser\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DummyCommandTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $kernel = new Kernel(dirname(__DIR__, 3));
        $container = $kernel->createContainer();

        /** @var Application $application */
        $application = $container->get(Application::class);
        $this->application = $application;
    }

    public function testCommandIsRegistered(): void
    {
        self::assertTrue($this->application->has('dummy'));
    }

    public function testExecuteReturnsSuccess(): void
    {
        $command = $this->application->find('dummy');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWritesExpectedOutput(): void
    {
        $command = $this->application->find('dummy');
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Dummy command executed.', $tester->getDisplay());
    }
}
