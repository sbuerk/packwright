<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Unknown;

/**
 * A single key/value pair from an `ext_emconf.php` file that does not
 * map to a known field.
 *
 * `$value` is `mixed` because the source file can put anything under
 * an unknown key — strings, scalars, or arbitrary nested arrays. This
 * is a deliberate, narrow exception to the "no plain array / typed
 * value" rule: the value is intentionally untyped because the input
 * is intentionally arbitrary.
 *
 * @internal not part of public API
 */
final readonly class UnknownProperty
{
    public function __construct(
        public string $key,
        public mixed $value,
    ) {}
}
