<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto;

use Packwright\ExtEmConf\Dto\Constraints\Constraints;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\State;

/**
 * Root DTO representing a parsed `ext_emconf.php` file.
 *
 * All fields that TYPO3 has ever supported are present here so that
 * existing files can round-trip through read → write without data loss.
 * Deprecated fields are preserved by the reader and flagged by the
 * validator; the normalizer and writer omit them when they are not
 * valid for the target TYPO3 version.
 *
 * `$category` and `$state` are `…|string|null` so that unrecognised
 * raw strings survive the read without crashing. The validator flags
 * raw string values as warnings; the normalizer drops them.
 *
 * `$unknownProperties` captures every key the reader encounters that
 * does not map to a known field. The validator flags each entry; the
 * normalizer and writer ignore them entirely.
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
 *
 * @internal not part of public API
 */
final readonly class ExtEmConf
{
    /**
     * @param Category|string|null    $category
     * @param State|string|null       $state
     * @param array<string, mixed>    $unknownProperties
     */
    public function __construct(
        // --- Always-current fields (v9.5+, non-deprecated) ---
        public string $title = '',
        public string $description = '',
        public string $version = '',
        public Category|string|null $category = null,
        public State|string|null $state = null,
        public string $author = '',
        public string $authorEmail = '',
        public string $authorCompany = '',
        public Constraints $constraints = new Constraints(),
        public Autoload $autoload = new Autoload(),
        public Autoload $autoloadDev = new Autoload(),

        // --- Deprecated in TYPO3 12.1, removed in later versions ---
        public ?bool $clearCacheOnLoad = null,

        // --- Deprecated since v9.5, only present in very old extensions ---
        public ?bool $uploadfolder = null,
        public ?string $createDirs = null,

        // --- Unknown keys encountered during reading ---
        public array $unknownProperties = [],
    ) {}
}
