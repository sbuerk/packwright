<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Enum;

/**
 * Represents the allowed values for the `category` field in `ext_emconf.php`.
 *
 * Used as a typed union with `string|null` on the root DTO to allow
 * round-tripping files that contain unknown category strings.
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
 */
enum Category: string
{
    case BE = 'be';
    case MODULE = 'module';
    case FE = 'fe';
    case PLUGIN = 'plugin';
    case MISC = 'misc';
    case SERVICES = 'services';
    case TEMPLATES = 'templates';
    case EXAMPLE = 'example';
    case DOC = 'doc';
    case DISTRIBUTION = 'distribution';
}
