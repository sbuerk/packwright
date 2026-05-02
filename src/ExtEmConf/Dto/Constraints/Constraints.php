<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Constraints;

/**
 * Holds the three constraint groups from the `constraints` key in
 * `ext_emconf.php`: `depends`, `conflicts`, and `suggests`.
 *
 * Each group is represented by a `DependencyCollection`. The reader
 * maps `array<string, string>` maps to typed `Dependency` instances
 * via `AllVersions`, `SingleVersion`, or `VersionRange`.
 *
 * The `$migratable` map captures entries written under the singular
 * typo'd sub-keys `depend`, `suggest`, and `conflict`. Reader keeps
 * them separate so that the validator can warn about them and the
 * normalizer can migrate them into the canonical group.
 *
 * @internal not part of public API
 */
final readonly class Constraints
{
    /**
     * @param array<string, DependencyCollection> $migratable keyed by typo'd source key (`depend`, `suggest`, `conflict`)
     */
    public function __construct(
        public DependencyCollection $depends = new DependencyCollection(),
        public DependencyCollection $conflicts = new DependencyCollection(),
        public DependencyCollection $suggests = new DependencyCollection(),
        public array $migratable = [],
    ) {}
}
