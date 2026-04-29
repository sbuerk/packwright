<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Constraints\VersionValue;

use SBReleaser\ExtEmConf\Dto\Version;

/**
 * Represents an inclusive version range constraint for a dependency.
 *
 * Corresponds to the `lowest-highest` format (e.g. `12.4.0-13.4.99`)
 * as the value in a `depends`, `conflicts`, or `suggests` entry of
 * `ext_emconf.php`. Both bounds are inclusive.
 *
 * Used as part of the union type
 * `AllVersions|SingleVersion|VersionRange` on `Dependency::$version`.
 */
final readonly class VersionRange
{
    public function __construct(
        public Version $lowest,
        public Version $highest,
    ) {}
}
