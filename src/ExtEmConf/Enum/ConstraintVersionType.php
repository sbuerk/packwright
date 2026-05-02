<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Enum;

/**
 * Classifies the version constraint value carried by a `Dependency`.
 *
 * Allows callers to branch on constraint type without instanceof checks
 * against the concrete VersionValue classes.
 *
 * @internal not part of public API
 */
enum ConstraintVersionType
{
    /**
     * Any version is accepted — represented by an empty string or `*`.
     */
    case ALL;

    /**
     * A single exact version in `x.y.z` format.
     */
    case VERSION;

    /**
     * An inclusive version range in `lowest-highest` format.
     */
    case RANGE;
}
