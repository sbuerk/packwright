<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Result;

use Packwright\ExtEmConf\Enum\ResultState;

/**
 * The outcome of `ExtEmConfValidator::validate()`.
 *
 * `$state` reflects the worst-case severity across all collected
 * issues: `SUCCESS` when no issues were found, `WARNING` when only
 * warnings were produced, `FAILURE` when at least one error was found.
 *
 * The DTO is never modified by the validator.
 *
 * @internal not part of public API
 */
final readonly class ValidationResult
{
    /**
     * @param list<Issue> $issues
     */
    public function __construct(
        public ResultState $state,
        public array $issues = [],
    ) {}

    public function isValid(): bool
    {
        return $this->state !== ResultState::FAILURE;
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->isError()) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->isWarning()) {
                return true;
            }
        }

        return false;
    }
}
