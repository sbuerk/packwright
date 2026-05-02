<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Composer\SiblingComposerInfo;

/**
 * Reads a `composer.json` file located next to a given
 * `ext_emconf.php` path and extracts a small set of inventory
 * fields used by `extemconf:list`.
 *
 * Extracted fields:
 *
 * - `name`                          → `packageName`
 * - `type`                          → `packageType`
 * - `description`                   → `packageDescription`
 * - `extra.typo3/cms.extension-key` → `extensionKey`
 *
 * Note: `typo3/cms` is a literal JSON object key (containing a
 * slash), not two nested keys.
 *
 * Composer metadata is purely additive — a missing sibling
 * `composer.json`, unreadable file, malformed JSON, non-array root,
 * or missing/non-string fields all yield empty strings. No issues
 * are surfaced and read failures never affect command exit codes.
 *
 * @internal not part of public API
 */
final readonly class SiblingComposerReader
{
    public function read(string $extEmConfPath): SiblingComposerInfo
    {
        $composerPath = dirname($extEmConfPath) . '/composer.json';
        if (!is_file($composerPath) || !is_readable($composerPath)) {
            return new SiblingComposerInfo();
        }

        $raw = @file_get_contents($composerPath);
        if ($raw === false) {
            return new SiblingComposerInfo();
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new SiblingComposerInfo();
        }

        if (!is_array($decoded)) {
            return new SiblingComposerInfo();
        }

        return new SiblingComposerInfo(
            extensionKey: $this->readExtensionKey($decoded),
            packageName: $this->readString($decoded, 'name'),
            packageType: $this->readString($decoded, 'type'),
            packageDescription: $this->readString($decoded, 'description'),
        );
    }

    /**
     * @param array<int|string, mixed> $decoded
     */
    private function readString(array $decoded, string $key): string
    {
        $value = $decoded[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<int|string, mixed> $decoded
     */
    private function readExtensionKey(array $decoded): string
    {
        $extra = $decoded['extra'] ?? null;
        if (!is_array($extra)) {
            return '';
        }

        $typo3Cms = $extra['typo3/cms'] ?? null;
        if (!is_array($typo3Cms)) {
            return '';
        }

        $extensionKey = $typo3Cms['extension-key'] ?? null;

        return is_string($extensionKey) ? $extensionKey : '';
    }
}
