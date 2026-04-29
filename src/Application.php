<?php

declare(strict_types=1);

namespace SBReleaser;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[Autoconfigure(public: true)]
final class Application extends ConsoleApplication
{
    /**
     * @param iterable<Command> $commands
     */
    public function __construct(
        #[TaggedIterator('console.command')] iterable $commands,
    ) {
        parent::__construct('sb-releaser', '0.1.0');
        foreach ($commands as $command) {
            $this->add($command);
        }
    }
}
