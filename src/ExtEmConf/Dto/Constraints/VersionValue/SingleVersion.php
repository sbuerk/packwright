<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Constraints\VersionValue;

use Packwright\ExtEmConf\Dto\Version;
use Packwright\ExtEmConf\Enum\VersionFormat;

/**
 * Represents a dependency version constraint pinned to a single version.
 *
 * Corresponds to a plain `x.y.z` string as the value in a `depends`,
 * `conflicts`, or `suggests` entry of `ext_emconf.php`.
 *
 * Used as part of the union type
 * `AllVersions|SingleVersion|VersionRange` on `Dependency::$version`.
 *
 * @internal not part of public API
 */
final readonly class SingleVersion implements VersionValueInterface
{
    public function __construct(
        public Version $version,
    ) {}

    public function toVersionString(): string
    {
        return $this->version->format(VersionFormat::XYZ);
    }
}
