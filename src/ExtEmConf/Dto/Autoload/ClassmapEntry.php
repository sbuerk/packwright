<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Autoload;

/**
 * A single classmap autoload entry pointing to a directory or file path.
 *
 * @internal not part of public API
 */
final readonly class ClassmapEntry
{
    public function __construct(
        public string $path,
    ) {}
}
