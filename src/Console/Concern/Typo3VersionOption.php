<?php

declare(strict_types=1);

namespace Packwright\Console\Concern;

use Packwright\ExtEmConf\Enum\Typo3Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Adds the shared `-t/--typo3-version` option to a `Command` and provides
 * a single parsing routine that converts the raw input into a typed
 * `Typo3Version`.
 *
 * Designed to be mixed into command classes that already extend
 * `Symfony\Component\Console\Command\Command`. Returns:
 *
 * - `null`           when the option was not supplied.
 * - `Typo3Version`   when the option matches a known major version.
 * - `false`          when the option was supplied but cannot be parsed.
 *
 * @internal not part of public API
 *
 * @phpstan-require-extends Command
 */
trait Typo3VersionOption
{
    private function configureTypo3VersionOption(string $verb): void
    {
        $this->addOption(
            name: 'typo3-version',
            shortcut: 't',
            mode: InputOption::VALUE_REQUIRED,
            description: sprintf(
                'TYPO3 major version to %s against (8, 9, 10, 11, 12, 13).',
                $verb,
            ),
        );
    }

    private function parseTypo3VersionOption(InputInterface $input): false|Typo3Version|null
    {
        $raw = $input->getOption('typo3-version');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_string($raw) && !is_int($raw)) {
            return false;
        }
        $stringValue = (string)$raw;
        if (!ctype_digit($stringValue)) {
            return false;
        }

        return Typo3Version::tryFrom((int)$stringValue) ?? false;
    }
}
