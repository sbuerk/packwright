<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Result;

use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Enum\ResultState;

/**
 * The outcome of `ExtEmConfReader::read()`.
 *
 * `$state` is `SUCCESS` when the file loaded and was mapped without
 * fatal errors; `WARNING` when it loaded but issues were found (e.g.
 * deprecated or unknown keys); `FAILURE` when the file could not be
 * read or parsed at all.
 *
 * `$extEmConf` is `null` on `FAILURE`.
 *
 * @param list<Issue> $issues
 */
final readonly class ReadResult
{
    /**
     * @param list<Issue> $issues
     */
    public function __construct(
        public ResultState $state,
        public ?ExtEmConf $extEmConf,
        public array $issues = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->state === ResultState::SUCCESS;
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }
}
