<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Constraints\VersionValue;

/**
 * Common contract for the three constraint version value types:
 * `AllVersions`, `SingleVersion`, and `VersionRange`.
 *
 * Provides a single dispatch point for rendering the version constraint
 * back to its `ext_emconf.php` string representation, removing the need
 * for `instanceof` branching in callers.
 *
 * @internal not part of public API
 */
interface VersionValueInterface
{
    public function toVersionString(): string;
}
