<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Enum;

/**
 * Represents the overall outcome state of a service operation.
 *
 * Carried by all result objects returned by the ExtEmConf service layer.
 * The state is derived from the highest severity issue collected during
 * the operation: no issues → SUCCESS, only warnings → WARNING, any
 * error → FAILURE.
 *
 * @internal not part of public API
 */
enum ResultState
{
    case SUCCESS;
    case WARNING;
    case FAILURE;
}
