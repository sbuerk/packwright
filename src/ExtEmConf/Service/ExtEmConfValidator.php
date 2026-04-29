<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Service;

use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\IssueSeverity;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Result\Issue;
use SBReleaser\ExtEmConf\Result\ValidationResult;

/**
 * Validates an `ExtEmConf` DTO and returns a `ValidationResult`.
 *
 * The validator never modifies the DTO. When `$targetTypo3Version` is
 * supplied, additional warnings are emitted for fields that are
 * deprecated or removed in that TYPO3 version. Without a target
 * version only general structural rules are applied.
 */
final class ExtEmConfValidator
{
    public function validate(ExtEmConf $extEmConf, ?Version $targetTypo3Version = null): ValidationResult
    {
        $issues = [];

        $this->validateRequiredFields($extEmConf, $issues);
        $this->validateVersionFormat($extEmConf, $issues);
        $this->validateAuthorEmail($extEmConf, $issues);
        $this->validateCategory($extEmConf, $issues);
        $this->validateState($extEmConf, $issues);
        $this->validateUnknownProperties($extEmConf, $issues);

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

        return new ValidationResult(state: $state, issues: $issues);
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateRequiredFields(ExtEmConf $extEmConf, array &$issues): void
    {
        if ($extEmConf->title === '') {
            $issues[] = new Issue(
                path: 'title',
                message: 'Field "title" is required and must not be empty.',
                severity: IssueSeverity::ERROR,
            );
        }

        if ($extEmConf->description === '') {
            $issues[] = new Issue(
                path: 'description',
                message: 'Field "description" is required and must not be empty.',
                severity: IssueSeverity::ERROR,
            );
        }

        if ($extEmConf->version === '') {
            $issues[] = new Issue(
                path: 'version',
                message: 'Field "version" is required and must not be empty.',
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
        if (!is_string($extEmConf->category)) {
            return;
        }

        $issues[] = new Issue(
            path: 'category',
            message: sprintf('Unrecognised category value "%s".', $extEmConf->category),
            severity: IssueSeverity::WARNING,
        );
    }

    /**
     * @param list<Issue> $issues
     */
    private function validateState(ExtEmConf $extEmConf, array &$issues): void
    {
        if (!is_string($extEmConf->state)) {
            return;
        }

        $issues[] = new Issue(
            path: 'state',
            message: sprintf('Unrecognised state value "%s".', $extEmConf->state),
            severity: IssueSeverity::WARNING,
        );
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
    private function validateDeprecatedFields(
        ExtEmConf $extEmConf,
        Version $targetTypo3Version,
        array &$issues,
    ): void {
        if ($extEmConf->uploadfolder !== null && $targetTypo3Version->major >= 9) {
            $issues[] = new Issue(
                path: 'uploadfolder',
                message: 'Field "uploadfolder" is deprecated since TYPO3 9.5 and should be removed.',
                severity: IssueSeverity::WARNING,
            );
        }

        if ($extEmConf->createDirs !== null && $targetTypo3Version->major >= 9) {
            $issues[] = new Issue(
                path: 'createDirs',
                message: 'Field "createDirs" is deprecated since TYPO3 9.5 and should be removed.',
                severity: IssueSeverity::WARNING,
            );
        }

        if ($extEmConf->clearCacheOnLoad !== null && $this->isAtLeast($targetTypo3Version, 12, 1)) {
            $issues[] = new Issue(
                path: 'clearCacheOnLoad',
                message: 'Field "clearCacheOnLoad" is deprecated since TYPO3 12.1 and should be removed.',
                severity: IssueSeverity::WARNING,
            );
        }
    }

    private function isAtLeast(Version $version, int $major, int $minor): bool
    {
        if ($version->major > $major) {
            return true;
        }

        return $version->major === $major && $version->minor >= $minor;
    }
}
