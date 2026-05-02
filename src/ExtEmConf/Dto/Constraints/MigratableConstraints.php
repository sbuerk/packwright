<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Constraints;

/**
 * Holds entries written under the singular typo'd `constraints`
 * sub-keys `depend`, `suggest`, and `conflict`.
 *
 * Mirrors the canonical `Constraints` shape so that read → validate →
 * normalize can iterate the typo'd groups by named property without
 * ever falling back to associative-array indexing. Each property is a
 * `DependencyCollection` (empty when no entries were written under
 * that typo'd key in the source file).
 *
 * @internal not part of public API
 */
final readonly class MigratableConstraints
{
    public function __construct(
        public DependencyCollection $depend = new DependencyCollection(),
        public DependencyCollection $suggest = new DependencyCollection(),
        public DependencyCollection $conflict = new DependencyCollection(),
    ) {}

    public function isEmpty(): bool
    {
        return $this->depend->isEmpty()
            && $this->suggest->isEmpty()
            && $this->conflict->isEmpty();
    }
}
