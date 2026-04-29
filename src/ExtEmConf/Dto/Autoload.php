<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto;

/**
 * Holds autoloading configuration from the `autoload` or `autoload-dev`
 * field of `ext_emconf.php`.
 *
 * The reader maps both top-level keys to separate `Autoload` instances
 * stored on `ExtEmConf::$autoload` and `ExtEmConf::$autoloadDev`.
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
 */
final readonly class Autoload
{
    /**
     * @param array<string, string> $psr4     PSR-4 namespace prefix to path mapping
     * @param list<string>          $classmap  List of directory or file paths for classmap loading
     */
    public function __construct(
        public array $psr4 = [],
        public array $classmap = [],
    ) {}
}
