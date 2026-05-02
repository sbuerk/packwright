<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\Command;

use Packwright\Command\DummyCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DummyCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = new DummyCommand();

        self::assertSame('dummy', $command->getName());
    }

    public function testCommandDescriptionIsNotEmpty(): void
    {
        $command = new DummyCommand();

        self::assertNotEmpty($command->getDescription());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $command = new DummyCommand();
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWritesExpectedOutput(): void
    {
        $command = new DummyCommand();
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Dummy command executed.', $tester->getDisplay());
    }
}
