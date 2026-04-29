<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Constraints;

/**
 * Holds the three constraint groups from the `constraints` key in
 * `ext_emconf.php`: `depends`, `conflicts`, and `suggests`.
 *
 * Each group is represented by a `DependencyCollection`. The reader
 * maps `array<string, string>` maps to typed `Dependency` instances
 * via `AllVersions`, `SingleVersion`, or `VersionRange`.
 */
final readonly class Constraints
{
    public function __construct(
        public DependencyCollection $depends = new DependencyCollection(),
        public DependencyCollection $conflicts = new DependencyCollection(),
        public DependencyCollection $suggests = new DependencyCollection(),
    ) {}
}
