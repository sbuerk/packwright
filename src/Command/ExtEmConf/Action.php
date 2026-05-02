<?php

declare(strict_types=1);

namespace Packwright\Command\ExtEmConf;

/**
 * Per-file outcome of a mutating `extemconf:*` command.
 *
 * Used by the table renderer (lower-kebab string) and the JSON
 * payload (`action` field) to communicate what happened to each
 * input file.
 *
 * @internal not part of public API
 */
enum Action
{
    case WRITTEN;
    case DRY_RUN;
    case UNCHANGED;
    case SKIPPED;

    public function label(): string
    {
        return match ($this) {
            self::WRITTEN => 'written',
            self::DRY_RUN => 'dry-run',
            self::UNCHANGED => 'unchanged',
            self::SKIPPED => 'skipped',
        };
    }
}
