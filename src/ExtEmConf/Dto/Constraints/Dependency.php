<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Constraints;

use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use SBReleaser\ExtEmConf\Enum\ConstraintVersionType;

/**
 * Represents a single extension key / version-value pair inside a
 * `depends`, `conflicts`, or `suggests` constraint map.
 *
 * The `$versionType` discriminant is derived from the concrete type of
 * `$version` by the reader and can be used for matching without
 * `instanceof` checks:
 *
 * - `ALL`     → `AllVersions`
 * - `VERSION` → `SingleVersion`
 * - `RANGE`   → `VersionRange`
 */
final readonly class Dependency
{
    /**
     * @param non-empty-string $extensionKey
     */
    public function __construct(
        public string $extensionKey,
        public ConstraintVersionType $versionType,
        public AllVersions|SingleVersion|VersionRange $version,
    ) {}
}
