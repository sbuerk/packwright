<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Constraints\VersionValue;

use SBReleaser\ExtEmConf\Dto\Version;

/**
 * Represents a dependency version constraint pinned to a single version.
 *
 * Corresponds to a plain `x.y.z` string as the value in a `depends`,
 * `conflicts`, or `suggests` entry of `ext_emconf.php`.
 *
 * Used as part of the union type
 * `AllVersions|SingleVersion|VersionRange` on `Dependency::$version`.
 */
final readonly class SingleVersion
{
    public function __construct(
        public Version $version,
    ) {}
}
