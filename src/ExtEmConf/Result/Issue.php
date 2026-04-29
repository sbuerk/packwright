<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Result;

use SBReleaser\ExtEmConf\Enum\IssueSeverity;

/**
 * Represents a single diagnostic issue found during read, validation,
 * or normalization of an `ext_emconf.php` file.
 *
 * `$path` is a dot-separated key path into the ext_emconf structure
 * (e.g. `constraints.depends.typo3`), or an empty string when the
 * issue applies to the file as a whole.
 *
 * @internal not part of public API
 */
final readonly class Issue
{
    public function __construct(
        public string $path,
        public string $message,
        public IssueSeverity $severity,
    ) {}

    public function isError(): bool
    {
        return $this->severity === IssueSeverity::ERROR;
    }

    public function isWarning(): bool
    {
        return $this->severity === IssueSeverity::WARNING;
    }
}
