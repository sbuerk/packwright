<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Constraints\VersionValue;

/**
 * Represents a dependency version constraint that accepts any version.
 *
 * Corresponds to an empty string `''` or a wildcard `'*'` as the value
 * in a `depends`, `conflicts`, or `suggests` entry of `ext_emconf.php`.
 *
 * Used as part of the union type
 * `AllVersions|SingleVersion|VersionRange` on `Dependency::$version`.
 *
 * @internal not part of public API
 */
final readonly class AllVersions implements VersionValueInterface
{
    public function __construct() {}

    public function toVersionString(): string
    {
        return '';
    }
}
