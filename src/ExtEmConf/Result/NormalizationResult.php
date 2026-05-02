<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Result;

use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Enum\ResultState;

/**
 * The outcome of `ExtEmConfNormalizer::normalize()`.
 *
 * Always contains a new `ExtEmConf` instance (even when nothing
 * changed). `$hasChanges` is `true` when at least one field was
 * dropped or altered to satisfy the target TYPO3 version constraints.
 * `$droppedItems` lists each removed field as an informational `Issue`
 * with `IssueSeverity::WARNING`.
 *
 * @internal not part of public API
 */
final readonly class NormalizationResult
{
    public function __construct(
        public ResultState $state,
        public ExtEmConf $extEmConf,
        public bool $hasChanges,
        public IssueCollection $droppedItems = new IssueCollection(),
    ) {}

    public function isSuccess(): bool
    {
        return $this->state === ResultState::SUCCESS;
    }
}
