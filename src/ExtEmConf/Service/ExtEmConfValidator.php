<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Dto\Version;
use Packwright\ExtEmConf\Enum\IssueSeverity;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Result\Issue;
use Packwright\ExtEmConf\Result\IssueCollection;
use Packwright\ExtEmConf\Result\ValidationResult;

/**
 * Validates an `ExtEmConf` DTO and returns a `ValidationResult`.
 *
 * The validator never modifies the DTO. When `$targetTypo3Version` is
 * supplied, additional warnings are emitted for fields that are
 * deprecated or removed in that TYPO3 version. Without a target
 * version only general structural rules are applied.
 *
 * @internal not part of public API
 */
final class ExtEmConfValidator
{
    public function validate(ExtEmConf $extEmConf, ?Typo3Version $targetTypo3Version = null): ValidationResult
    {
        $issues = [];

        $this->validateRequiredFields($extEmConf, $issues);
        $this->validateVersionFormat($extEmConf, $issues);
        $this->validateAuthorEmail($extEmConf, $issues);
        $this->validateCategory($extEmConf, $issues);
        $this->validateState($extEmConf, $issues);
        $this->validateUnknownProperties($extEmConf, $issues);
        $this->validateMigratableConstraintsKeys($extEmConf, $issues);

        if ($targetTypo3Version !== null) {
            $this->validateDeprecatedFields($extEmConf, $targetTypo3Version, $issues);
        }

        $state = ResultState::SUCCESS;
        foreach ($issues as $issue) {
            if ($issue->isError()) {
                $state = ResultState::FAILURE;
                break;
            }

            $state = ResultState::WARNING;
        }

        return new ValidationResult(state: $state, issues: new IssueCollection($issues));
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateRequiredFields(ExtEmConf $extEmConf, array &$issues): void
    {
        $hint = '`extemconf:normalize` will write an empty string until you set a value.';

        if ($extEmConf->title === '') {
            $issues[] = new Issue(
                path: 'title',
                message: 'Field "title" is required and must not be empty. ' . $hint,
                severity: IssueSeverity::ERROR,
            );
        }

        if ($extEmConf->description === '') {
            $issues[] = new Issue(
                path: 'description',
                message: 'Field "description" is required and must not be empty. ' . $hint,
                severity: IssueSeverity::ERROR,
            );
        }

        if ($extEmConf->version === '') {
            $issues[] = new Issue(
                path: 'version',
                message: 'Field "version" is required and must not be empty. ' . $hint,
                severity: IssueSeverity::ERROR,
            );
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateVersionFormat(ExtEmConf $extEmConf, array &$issues): void
    {
        if ($extEmConf->version === '') {
            return;
        }

        try {
            Version::fromString($extEmConf->version);
        } catch (\UnexpectedValueException) {
            $issues[] = new Issue(
                path: 'version',
                message: sprintf('Version "%s" is not a valid semantic version string.', $extEmConf->version),
                severity: IssueSeverity::ERROR,
            );
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateAuthorEmail(ExtEmConf $extEmConf, array &$issues): void
    {
        if ($extEmConf->authorEmail === '') {
            return;
        }

        if (filter_var($extEmConf->authorEmail, FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = new Issue(
                path: 'author_email',
                message: sprintf('Author email "%s" is not a valid email address.', $extEmConf->authorEmail),
                severity: IssueSeverity::WARNING,
            );
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateCategory(ExtEmConf $extEmConf, array &$issues): void
    {
        if ($extEmConf->category === null) {
            $issues[] = new Issue(
                path: 'category',
                message: 'Field "category" is missing; `extemconf:normalize` will write the default "misc".',
                severity: IssueSeverity::WARNING,
            );

            return;
        }

        if (is_string($extEmConf->category)) {
            $issues[] = new Issue(
                path: 'category',
                message: sprintf(
                    'Unrecognised category value "%s"; `extemconf:normalize` will replace it with the default "misc".',
                    $extEmConf->category,
                ),
                severity: IssueSeverity::WARNING,
            );
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateState(ExtEmConf $extEmConf, array &$issues): void
    {
        if ($extEmConf->state === null) {
            $issues[] = new Issue(
                path: 'state',
                message: 'Field "state" is required and missing; `extemconf:normalize` would write the default "beta".',
                severity: IssueSeverity::ERROR,
            );

            return;
        }

        if (is_string($extEmConf->state)) {
            $issues[] = new Issue(
                path: 'state',
                message: sprintf(
                    'Unrecognised state value "%s"; `extemconf:normalize` would replace it with the default "beta".',
                    $extEmConf->state,
                ),
                severity: IssueSeverity::ERROR,
            );
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateUnknownProperties(ExtEmConf $extEmConf, array &$issues): void
    {
        foreach (array_keys($extEmConf->unknownProperties) as $key) {
            $issues[] = new Issue(
                path: $key,
                message: sprintf('Unknown property "%s" has no effect and will be ignored.', $key),
                severity: IssueSeverity::WARNING,
            );
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateMigratableConstraintsKeys(ExtEmConf $extEmConf, array &$issues): void
    {
        $migratable = $extEmConf->constraints->migratable;
        if ($migratable->isEmpty()) {
            return;
        }

        $groups = [
            'depend' => ['canonical' => 'depends', 'collection' => $migratable->depend, 'against' => $extEmConf->constraints->depends],
            'suggest' => ['canonical' => 'suggests', 'collection' => $migratable->suggest, 'against' => $extEmConf->constraints->suggests],
            'conflict' => ['canonical' => 'conflicts', 'collection' => $migratable->conflict, 'against' => $extEmConf->constraints->conflicts],
        ];

        foreach ($groups as $typoKey => $group) {
            if ($group['collection']->isEmpty()) {
                continue;
            }

            $issues[] = new Issue(
                path: 'constraints.' . $typoKey,
                message: sprintf(
                    'Unknown constraints key "%s"; `extemconf:normalize` will migrate it to "%s".',
                    $typoKey,
                    $group['canonical'],
                ),
                severity: IssueSeverity::WARNING,
            );

            $canonicalKeys = [];
            foreach ($group['against']->all() as $existing) {
                $canonicalKeys[$existing->extensionKey] = true;
            }

            foreach ($group['collection']->all() as $candidate) {
                if (isset($canonicalKeys[$candidate->extensionKey])) {
                    $issues[] = new Issue(
                        path: 'constraints.' . $typoKey . '.' . $candidate->extensionKey,
                        message: sprintf(
                            'Will be skipped during normalization: canonical "%s.%s" already exists.',
                            $group['canonical'],
                            $candidate->extensionKey,
                        ),
                        severity: IssueSeverity::WARNING,
                    );
                }
            }
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateDeprecatedFields(
        ExtEmConf $extEmConf,
        Typo3Version $targetTypo3Version,
        array &$issues,
    ): void {
        if ($extEmConf->uploadfolder !== null && $targetTypo3Version->value >= 9) {
            $issues[] = new Issue(
                path: 'uploadfolder',
                message: 'Field "uploadfolder" is deprecated since TYPO3 9.5 and should be removed.',
                severity: IssueSeverity::WARNING,
            );
        }

        if ($extEmConf->createDirs !== null && $targetTypo3Version->value >= 9) {
            $issues[] = new Issue(
                path: 'createDirs',
                message: 'Field "createDirs" is deprecated since TYPO3 9.5 and should be removed.',
                severity: IssueSeverity::WARNING,
            );
        }

        if ($extEmConf->clearCacheOnLoad !== null && $targetTypo3Version->value >= 12) {
            $issues[] = new Issue(
                path: 'clearCacheOnLoad',
                message: 'Field "clearCacheOnLoad" is deprecated since TYPO3 12.1 and should be removed.',
                severity: IssueSeverity::WARNING,
            );
        }
    }
}
