<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Service;

use SBReleaser\ExtEmConf\Dto\Autoload;
use SBReleaser\ExtEmConf\Dto\Constraints\Constraints;
use SBReleaser\ExtEmConf\Dto\Constraints\DependencyCollection;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionValueInterface;
use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Enum\Typo3Version;

/**
 * Generates `ext_emconf.php` file content from an `ExtEmConf` DTO.
 *
 * When `$targetTypo3Version` is supplied, deprecated fields not
 * supported by that version are silently omitted. Unknown properties
 * are always omitted regardless of version. The returned string is
 * ready to be written directly to disk.
 *
 * @internal not part of public API
 */
final class ExtEmConfWriter
{
    public function generate(ExtEmConf $extEmConf, ?Typo3Version $targetTypo3Version = null): string
    {
        $body = '';

        $body .= $this->renderField('title', $extEmConf->title);
        $body .= $this->renderField('description', $extEmConf->description);
        $body .= $this->renderField('version', $extEmConf->version);

        if ($extEmConf->category !== null) {
            $body .= $this->renderField('category', $this->categoryValue($extEmConf->category));
        }

        if ($extEmConf->state !== null) {
            $body .= $this->renderField('state', $this->stateValue($extEmConf->state));
        }

        $body .= $this->renderField('author', $extEmConf->author);
        $body .= $this->renderField('author_email', $extEmConf->authorEmail);
        $body .= $this->renderField('author_company', $extEmConf->authorCompany);
        $body .= $this->renderField('constraints', $this->constraintsToArray($extEmConf->constraints));

        $autoloadArray = $this->autoloadToArray($extEmConf->autoload);
        if ($autoloadArray !== []) {
            $body .= $this->renderField('autoload', $autoloadArray);
        }

        $autoloadDevArray = $this->autoloadToArray($extEmConf->autoloadDev);
        if ($autoloadDevArray !== []) {
            $body .= $this->renderField('autoload-dev', $autoloadDevArray);
        }

        if ($extEmConf->clearCacheOnLoad !== null && $this->shouldOutputClearCacheOnLoad($targetTypo3Version)) {
            $body .= $this->renderField('clearCacheOnLoad', $extEmConf->clearCacheOnLoad, true);
        }

        if ($extEmConf->uploadfolder !== null && $this->shouldOutputV8OnlyFields($targetTypo3Version)) {
            $body .= $this->renderField('uploadfolder', $extEmConf->uploadfolder, true);
        }

        if ($extEmConf->createDirs !== null && $this->shouldOutputV8OnlyFields($targetTypo3Version)) {
            $body .= $this->renderField('createDirs', $extEmConf->createDirs, true);
        }

        return "<?php\n\n\$EM_CONF[\$_EXTKEY] = [\n{$body}];\n";
    }

    private function renderField(string $key, mixed $value, bool $deprecated = false): string
    {
        $comment = $deprecated ? ' // @deprecated' : '';

        return "    '" . addcslashes($key, "'\\") . "' => "
            . $this->renderValue($value, 1) . ',' . $comment . "\n";
    }

    private function renderValue(mixed $value, int $depth): string
    {
        if (is_string($value)) {
            return "'" . addcslashes($value, "'\\") . "'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $innerIndent = str_repeat('    ', $depth + 1);
            $closingIndent = str_repeat('    ', $depth);
            $lines = [];

            foreach ($value as $key => $val) {
                $keyPart = is_string($key)
                    ? "'" . addcslashes($key, "'\\") . "' => "
                    : '';
                $lines[] = $innerIndent . $keyPart . $this->renderValue($val, $depth + 1) . ',';
            }

            return "[\n" . implode("\n", $lines) . "\n" . $closingIndent . ']';
        }

        return 'null';
    }

    private function categoryValue(Category|string $category): string
    {
        return $category instanceof Category ? $category->value : $category;
    }

    private function stateValue(State|string $state): string
    {
        return $state instanceof State ? $state->value : $state;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function constraintsToArray(Constraints $constraints): array
    {
        return [
            'depends' => $this->dependencyCollectionToArray($constraints->depends),
            'conflicts' => $this->dependencyCollectionToArray($constraints->conflicts),
            'suggests' => $this->dependencyCollectionToArray($constraints->suggests),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dependencyCollectionToArray(DependencyCollection $collection): array
    {
        $result = [];
        foreach ($collection as $extKey => $dependency) {
            $result[$extKey] = $this->versionValueToString($dependency->version);
        }

        return $result;
    }

    private function versionValueToString(VersionValueInterface $version): string
    {
        return $version->toVersionString();
    }

    /**
     * @return array<string, mixed>
     */
    private function autoloadToArray(Autoload $autoload): array
    {
        $result = [];

        if ($autoload->psr4 !== []) {
            $result['psr-4'] = $autoload->psr4;
        }

        if ($autoload->classmap !== []) {
            $result['classmap'] = $autoload->classmap;
        }

        return $result;
    }

    private function shouldOutputClearCacheOnLoad(?Typo3Version $targetTypo3Version): bool
    {
        return $targetTypo3Version === null || $targetTypo3Version->value < 12;
    }

    private function shouldOutputV8OnlyFields(?Typo3Version $targetTypo3Version): bool
    {
        return $targetTypo3Version === null || $targetTypo3Version->value < 9;
    }
}
