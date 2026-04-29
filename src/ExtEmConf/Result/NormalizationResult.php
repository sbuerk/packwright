<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Result;

use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Enum\ResultState;

/**
 * The outcome of `ExtEmConfNormalizer::normalize()`.
 *
 * Always contains a new `ExtEmConf` instance (even when nothing
 * changed). `$hasChanges` is `true` when at least one field was
 * dropped or altered to satisfy the target TYPO3 version constraints.
 * `$droppedItems` lists each removed field as an informational `Issue`
 * with `IssueSeverity::WARNING`.
 */
final readonly class NormalizationResult
{
    /**
     * @param list<Issue> $droppedItems
     */
    public function __construct(
        public ResultState $state,
        public ExtEmConf $extEmConf,
        public bool $hasChanges,
        public array $droppedItems = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->state === ResultState::SUCCESS;
    }
}
