<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Service;

use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\IssueSeverity;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Result\Issue;
use SBReleaser\ExtEmConf\Result\NormalizationResult;

/**
 * Produces a new `ExtEmConf` containing only fields that are valid for
 * the given target TYPO3 version. The original DTO is never mutated.
 *
 * Fields dropped during normalization are listed in
 * `NormalizationResult::$droppedItems` as `WARNING` issues.
 * `NormalizationResult::$hasChanges` is `true` when at least one
 * field was removed or changed.
 *
 * Without a target version only structurally invalid values are
 * removed (raw-string category/state, unknown properties).
 */
final class ExtEmConfNormalizer
{
    public function normalize(ExtEmConf $extEmConf, ?Version $targetTypo3Version = null): NormalizationResult
    {
        $droppedItems = [];

        $category = $this->normalizeCategory($extEmConf, $droppedItems);
        $extEmConfState = $this->normalizeState($extEmConf, $droppedItems);
        $clearCacheOnLoad = $this->normalizeClearCacheOnLoad($extEmConf, $targetTypo3Version, $droppedItems);
        $uploadfolder = $this->normalizeUploadfolder($extEmConf, $targetTypo3Version, $droppedItems);
        $createDirs = $this->normalizeCreateDirs($extEmConf, $targetTypo3Version, $droppedItems);

        foreach (array_keys($extEmConf->unknownProperties) as $key) {
            $droppedItems[] = new Issue(
                path: $key,
                message: sprintf('Dropped unknown property "%s".', $key),
                severity: IssueSeverity::WARNING,
            );
        }

        $normalized = new ExtEmConf(
            title: $extEmConf->title,
            description: $extEmConf->description,
            version: $extEmConf->version,
            category: $category,
            state: $extEmConfState,
            author: $extEmConf->author,
            authorEmail: $extEmConf->authorEmail,
            authorCompany: $extEmConf->authorCompany,
            constraints: $extEmConf->constraints,
            autoload: $extEmConf->autoload,
            autoloadDev: $extEmConf->autoloadDev,
            clearCacheOnLoad: $clearCacheOnLoad,
            uploadfolder: $uploadfolder,
            createDirs: $createDirs,
            unknownProperties: [],
        );

        return new NormalizationResult(
            state: ResultState::SUCCESS,
            extEmConf: $normalized,
            hasChanges: $droppedItems !== [],
            droppedItems: $droppedItems,
        );
    }

    /**
     * @param list<Issue> $droppedItems
     */
    private function normalizeCategory(ExtEmConf $extEmConf, array &$droppedItems): ?Category
    {
        if (!is_string($extEmConf->category)) {
            return $extEmConf->category;
        }

        $droppedItems[] = new Issue(
            path: 'category',
            message: sprintf('Dropped unrecognised category value "%s".', $extEmConf->category),
            severity: IssueSeverity::WARNING,
        );

        return null;
    }

    /**
     * @param list<Issue> $droppedItems
     */
    private function normalizeState(ExtEmConf $extEmConf, array &$droppedItems): ?State
    {
        if (!is_string($extEmConf->state)) {
            return $extEmConf->state;
        }

        $droppedItems[] = new Issue(
            path: 'state',
            message: sprintf('Dropped unrecognised state value "%s".', $extEmConf->state),
            severity: IssueSeverity::WARNING,
        );

        return null;
    }

    /**
     * @param list<Issue> $droppedItems
     */
    private function normalizeClearCacheOnLoad(
        ExtEmConf $extEmConf,
        ?Version $targetTypo3Version,
        array &$droppedItems,
    ): ?bool {
        if ($extEmConf->clearCacheOnLoad === null) {
            return null;
        }

        if ($targetTypo3Version !== null && $this->isAtLeast($targetTypo3Version, 12, 1)) {
            $droppedItems[] = new Issue(
                path: 'clearCacheOnLoad',
                message: 'Dropped deprecated field "clearCacheOnLoad" (deprecated since TYPO3 12.1).',
                severity: IssueSeverity::WARNING,
            );

            return null;
        }

        return $extEmConf->clearCacheOnLoad;
    }

    /**
     * @param list<Issue> $droppedItems
     */
    private function normalizeUploadfolder(
        ExtEmConf $extEmConf,
        ?Version $targetTypo3Version,
        array &$droppedItems,
    ): ?bool {
        if ($extEmConf->uploadfolder === null) {
            return null;
        }

        if ($targetTypo3Version !== null && $targetTypo3Version->major >= 9) {
            $droppedItems[] = new Issue(
                path: 'uploadfolder',
                message: 'Dropped deprecated field "uploadfolder" (deprecated since TYPO3 9.5).',
                severity: IssueSeverity::WARNING,
            );

            return null;
        }

        return $extEmConf->uploadfolder;
    }

    /**
     * @param list<Issue> $droppedItems
     */
    private function normalizeCreateDirs(
        ExtEmConf $extEmConf,
        ?Version $targetTypo3Version,
        array &$droppedItems,
    ): ?string {
        if ($extEmConf->createDirs === null) {
            return null;
        }

        if ($targetTypo3Version !== null && $targetTypo3Version->major >= 9) {
            $droppedItems[] = new Issue(
                path: 'createDirs',
                message: 'Dropped deprecated field "createDirs" (deprecated since TYPO3 9.5).',
                severity: IssueSeverity::WARNING,
            );

            return null;
        }

        return $extEmConf->createDirs;
    }

    private function isAtLeast(Version $version, int $major, int $minor): bool
    {
        if ($version->major > $major) {
            return true;
        }

        return $version->major === $major && $version->minor >= $minor;
    }
}
