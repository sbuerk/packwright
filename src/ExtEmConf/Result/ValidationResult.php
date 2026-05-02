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
    public function __construct(
        public ResultState $state,
        public IssueCollection $issues = new IssueCollection(),
    ) {}

    public function isValid(): bool
    {
        return $this->state !== ResultState::FAILURE;
    }

    public function hasErrors(): bool
    {
        return $this->issues->hasErrors();
    }

    public function hasWarnings(): bool
    {
        return $this->issues->hasWarnings();
    }
}
