<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Constraints\VersionValue;

use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\VersionFormat;

/**
 * Represents an inclusive version range constraint for a dependency.
 *
 * Corresponds to the `lowest-highest` format (e.g. `12.4.0-13.4.99`)
 * as the value in a `depends`, `conflicts`, or `suggests` entry of
 * `ext_emconf.php`. Both bounds are inclusive.
 *
 * Used as part of the union type
 * `AllVersions|SingleVersion|VersionRange` on `Dependency::$version`.
 *
 * @internal not part of public API
 */
final readonly class VersionRange implements VersionValueInterface
{
    public function __construct(
        public Version $lowest,
        public Version $highest,
    ) {}

    public function toVersionString(): string
    {
        return $this->lowest->format(VersionFormat::XYZ) . '-' . $this->highest->format(VersionFormat::XYZ);
    }
}
