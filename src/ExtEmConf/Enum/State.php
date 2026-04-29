<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Enum;

/**
 * Represents the allowed values for the `state` field in `ext_emconf.php`.
 *
 * Used as a typed union with `string|null` on the root DTO to allow
 * round-tripping files that contain unknown state strings.
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
 */
enum State: string
{
    case ALPHA = 'alpha';
    case BETA = 'beta';
    case STABLE = 'stable';
    case EXPERIMENTAL = 'experimental';
    case TEST = 'test';
    case OBSOLETE = 'obsolete';
    case EXCLUDE_FROM_UPDATES = 'excludeFromUpdates';
}
