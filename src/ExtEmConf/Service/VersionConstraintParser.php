<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\VersionValueInterface;
use Packwright\ExtEmConf\Dto\Version;

/**
 * Parses a single version-constraint string from a `constraints.depends`,
 * `.conflicts`, or `.suggests` entry into a typed `VersionValueInterface`.
 *
 * - Empty string and `*` produce `AllVersions`.
 * - `X.Y.Z-X.Y.Z` ranges produce `VersionRange` (both bounds parsed via
 *   `Version::fromString()`).
 * - Anything else is attempted as a single semver via `Version::fromString()`
 *   and returned as `SingleVersion`.
 *
 * Unparseable input throws `\InvalidArgumentException`. Callers that need
 * resilient behaviour (the reader, which has to round-trip arbitrary
 * existing files) catch the exception and fall back to a default —
 * callers that need hard-fail behaviour (mutation commands) let it
 * propagate.
 *
 * @internal not part of public API
 */
final class VersionConstraintParser
{
    public function parse(string $raw): VersionValueInterface
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || $trimmed === '*') {
            return new AllVersions();
        }

        if (preg_match('/^\d+\.\d+\.\d+-\d+\.\d+\.\d+$/', $trimmed) === 1) {
            [$lowestStr, $highestStr] = explode('-', $trimmed, 2);
            try {
                return new VersionRange(
                    lowest: Version::fromString($lowestStr),
                    highest: Version::fromString($highestStr),
                );
            } catch (\UnexpectedValueException $exception) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid version range "%s": %s', $raw, $exception->getMessage()),
                    previous: $exception,
                );
            }
        }

        try {
            return new SingleVersion(Version::fromString($trimmed));
        } catch (\UnexpectedValueException $exception) {
            throw new \InvalidArgumentException(
                sprintf('Invalid version string "%s": %s', $raw, $exception->getMessage()),
                previous: $exception,
            );
        }
    }
}
