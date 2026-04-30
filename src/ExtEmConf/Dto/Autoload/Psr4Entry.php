<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Autoload;

/**
 * A single PSR-4 autoload entry mapping a namespace prefix to a source path.
 *
 * @internal not part of public API
 */
final readonly class Psr4Entry
{
    public function __construct(
        public string $namespace,
        public string $path,
    ) {}
}
