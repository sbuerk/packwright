<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Enum;

/**
 * Selects the output format for `Version::format()`.
 *
 * @internal not part of public API
 */
enum VersionFormat
{
    /**
     * `1.2.3` — major.minor.patch only.
     */
    case XYZ;

    /**
     * `1.2.3.0` — major.minor.patch.build.
     */
    case XYZ_D;

    /**
     * `1.2.3-alpha` — major.minor.patch with stability suffix.
     * Falls back to `x.y.z` when no stability label is set.
     */
    case XYZ_STABILITY;
}
