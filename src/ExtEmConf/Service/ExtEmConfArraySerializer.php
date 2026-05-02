<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Autoload;
use Packwright\ExtEmConf\Dto\Autoload\ClassmapCollection;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Collection;
use Packwright\ExtEmConf\Dto\Constraints\Constraints;
use Packwright\ExtEmConf\Dto\Constraints\DependencyCollection;
use Packwright\ExtEmConf\Dto\Constraints\MigratableConstraints;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Dto\Unknown\UnknownPropertyCollection;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\State;

/**
 * Serializes an `ExtEmConf` DTO graph into a JSON-friendly associative
 * array.
 *
 * The output mirrors the canonical `ext_emconf.php` key shape (snake_case
 * scalars, `psr-4` / `autoload-dev` hyphenated keys, dependencies keyed by
 * extension name with their canonical version-string representation).
 * Enum values are emitted as their backing string; unrecognised raw
 * strings on `category` / `state` are passed through unchanged so that
 * `--json` consumers see what is actually in the file.
 *
 * Centralizing serialization here keeps the DTOs pure data holders and
 * makes the JSON contract testable in isolation. Consumers (currently
 * `extemconf:list`) call `serialize()` to obtain the `data` payload.
 *
 * @internal not part of public API
 */
final readonly class ExtEmConfArraySerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(ExtEmConf $extEmConf): array
    {
        return [
            'title' => $extEmConf->title,
            'description' => $extEmConf->description,
            'version' => $extEmConf->version,
            'category' => $this->serializeEnumOrString($extEmConf->category),
            'state' => $this->serializeEnumOrString($extEmConf->state),
            'author' => $extEmConf->author,
            'author_email' => $extEmConf->authorEmail,
            'author_company' => $extEmConf->authorCompany,
            'constraints' => $this->serializeConstraints($extEmConf->constraints),
            'autoload' => $this->serializeAutoload($extEmConf->autoload),
            'autoload-dev' => $this->serializeAutoload($extEmConf->autoloadDev),
            'clearCacheOnLoad' => $extEmConf->clearCacheOnLoad,
            'uploadfolder' => $extEmConf->uploadfolder,
            'createDirs' => $extEmConf->createDirs,
            'unknownProperties' => $this->serializeUnknownProperties($extEmConf->unknownProperties),
        ];
    }

    private function serializeEnumOrString(Category|State|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \BackedEnum) {
            return (string)$value->value;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConstraints(Constraints $constraints): array
    {
        return [
            'depends' => $this->serializeDependencyCollection($constraints->depends),
            'conflicts' => $this->serializeDependencyCollection($constraints->conflicts),
            'suggests' => $this->serializeDependencyCollection($constraints->suggests),
            'migratable' => $this->serializeMigratable($constraints->migratable),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function serializeDependencyCollection(DependencyCollection $collection): array
    {
        $out = [];
        foreach ($collection->all() as $dependency) {
            $out[$dependency->extensionKey] = $dependency->version->toVersionString();
        }

        return $out;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function serializeMigratable(MigratableConstraints $migratable): array
    {
        return [
            'depend' => $this->serializeDependencyCollection($migratable->depend),
            'suggest' => $this->serializeDependencyCollection($migratable->suggest),
            'conflict' => $this->serializeDependencyCollection($migratable->conflict),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAutoload(Autoload $autoload): array
    {
        return [
            'psr-4' => $this->serializePsr4($autoload->psr4),
            'classmap' => $this->serializeClassmap($autoload->classmap),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function serializePsr4(Psr4Collection $psr4): array
    {
        $out = [];
        foreach ($psr4->all() as $entry) {
            $out[$entry->namespace] = $entry->path;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function serializeClassmap(ClassmapCollection $classmap): array
    {
        $out = [];
        foreach ($classmap->all() as $entry) {
            $out[] = $entry->path;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUnknownProperties(UnknownPropertyCollection $unknown): array
    {
        $out = [];
        foreach ($unknown->all() as $property) {
            if (!array_key_exists($property->key, $out)) {
                $out[$property->key] = $property->value;
            }
        }

        return $out;
    }
}
