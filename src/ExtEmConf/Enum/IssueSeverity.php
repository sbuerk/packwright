<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Enum;

/**
 * Severity level for a single diagnostic issue produced by a service.
 *
 * WARNING indicates a recoverable condition (unknown key, deprecated
 * field, unresolvable enum value). ERROR indicates a condition that
 * prevents correct processing (missing required field, invalid file).
 *
 * @internal not part of public API
 */
enum IssueSeverity
{
    case WARNING;
    case ERROR;
}
