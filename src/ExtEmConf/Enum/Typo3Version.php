<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Enum;

/**
 * Represents a supported TYPO3 major release for use with the
 * `ExtEmConf` validation, normalization, and writer services.
 *
 * The integer backing value equals the major version number, enabling
 * simple `>=` comparisons inside the services.
 *
 * Note: the `clearCacheOnLoad` field was deprecated in TYPO3 12.1.
 * This enum uses only major versions, so `V12` is treated as the
 * deprecation boundary (12.0 is included in the deprecated range).
 *
 * @internal not part of public API
 */
enum Typo3Version: int
{
    case V8  = 8;
    case V9  = 9;
    case V10 = 10;
    case V11 = 11;
    case V12 = 12;
    case V13 = 13;

    public function label(): string
    {
        return 'TYPO3 v' . $this->value;
    }
}
