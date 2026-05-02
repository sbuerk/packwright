<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto;

use Packwright\ExtEmConf\Dto\Autoload\ClassmapCollection;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Collection;

/**
 * Holds autoloading configuration from the `autoload` or `autoload-dev`
 * field of `ext_emconf.php`.
 *
 * The reader maps both top-level keys to separate `Autoload` instances
 * stored on `ExtEmConf::$autoload` and `ExtEmConf::$autoloadDev`.
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
 *
 * @internal not part of public API
 */
final readonly class Autoload
{
    public function __construct(
        public Psr4Collection $psr4 = new Psr4Collection(),
        public ClassmapCollection $classmap = new ClassmapCollection(),
    ) {}
}
