<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Composer;

/**
 * Snapshot of the four fields extracted from a `composer.json` file
 * sitting next to an `ext_emconf.php`. All properties default to an
 * empty string so callers always receive a usable object even when
 * the sibling `composer.json` is missing, malformed, or only
 * partially populated.
 *
 * @internal not part of public API
 */
final readonly class SiblingComposerInfo
{
    public function __construct(
        public string $extensionKey = '',
        public string $packageName = '',
        public string $packageType = '',
        public string $packageDescription = '',
    ) {}
}
