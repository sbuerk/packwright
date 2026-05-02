<?php

declare(strict_types=1);

namespace Packwright\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dummy',
    description: 'Placeholder command for initial setup',
)]
final class DummyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Dummy command executed.');

        return Command::SUCCESS;
    }
}
