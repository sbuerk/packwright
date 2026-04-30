<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Service;

use SBReleaser\ExtEmConf\Dto\Autoload;
use SBReleaser\ExtEmConf\Dto\Autoload\ClassmapCollection;
use SBReleaser\ExtEmConf\Dto\Autoload\ClassmapEntry;
use SBReleaser\ExtEmConf\Dto\Autoload\Psr4Collection;
use SBReleaser\ExtEmConf\Dto\Autoload\Psr4Entry;
use SBReleaser\ExtEmConf\Dto\Constraints\Constraints;
use SBReleaser\ExtEmConf\Dto\Constraints\Dependency;
use SBReleaser\ExtEmConf\Dto\Constraints\DependencyCollection;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionValueInterface;
use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\ConstraintVersionType;
use SBReleaser\ExtEmConf\Enum\IssueSeverity;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Result\Issue;
use SBReleaser\ExtEmConf\Result\ReadResult;

/**
 * Reads an `ext_emconf.php` file and maps its contents to an
 * `ExtEmConf` DTO. Unknown keys, invalid values, and unrecognised
 * enum strings are surfaced as `Issue` warnings in the result.
 *
 * The file is executed inside an isolated closure scope using
 * `include`, with `$_EXTKEY` set to a sentinel value so that the
 * populated `$EM_CONF[$_EXTKEY]` entry can be extracted safely.
 * Any `\Throwable` thrown during inclusion produces a FAILURE result.
 *
 * @internal not part of public API
 */
final class ExtEmConfReader
{
    private const KNOWN_KEYS = [
        'title',
        'description',
        'version',
        'category',
        'state',
        'author',
        'author_email',
        'author_company',
        'constraints',
        'autoload',
        'autoload-dev',
        'clearCacheOnLoad',
        'uploadfolder',
        'createDirs',
    ];

    public function read(string $filePath): ReadResult
    {
        $raw = $this->loadFile($filePath);

        if ($raw === null) {
            return new ReadResult(
                state: ResultState::FAILURE,
                extEmConf: null,
                issues: [
                    new Issue(
                        path: '',
                        message: sprintf('Failed to load or parse "%s".', $filePath),
                        severity: IssueSeverity::ERROR,
                    ),
                ],
            );
        }

        $issues = [];
        $extEmConf = $this->mapToDto($raw, $issues);

        $state = ResultState::SUCCESS;
        foreach ($issues as $issue) {
            if ($issue->isError()) {
                $state = ResultState::FAILURE;
                break;
            }

            $state = ResultState::WARNING;
        }

        return new ReadResult(state: $state, extEmConf: $extEmConf, issues: $issues);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFile(string $filePath): ?array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }

        try {
            $loader = static function (string $path): mixed {
                $_EXTKEY = '__sb_releaser_sentinel__';
                // Initialized via a typed helper so PHPStan knows $EM_CONF is
                // array<string, mixed>; include will populate it at runtime.
                $EM_CONF = ExtEmConfReader::makeEmConfContainer();
                include $path;

                return $EM_CONF[$_EXTKEY] ?? null;
            };

            $result = $loader($filePath);

            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns an empty EM_CONF container typed as array<string, mixed>.
     *
     * @return array<string, mixed>
     */
    private static function makeEmConfContainer(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<Issue>          $issues
     */
    private function mapToDto(array $raw, array &$issues): ExtEmConf
    {
        $unknownProperties = [];
        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::KNOWN_KEYS, true)) {
                $unknownProperties[$key] = $raw[$key];
                $issues[] = new Issue(
                    path: (string)$key,
                    message: sprintf('Unknown key "%s" in ext_emconf.php.', $key),
                    severity: IssueSeverity::WARNING,
                );
            }
        }

        return new ExtEmConf(
            title: $this->readString($raw, 'title'),
            description: $this->readString($raw, 'description'),
            version: $this->readString($raw, 'version'),
            category: $this->readCategory($raw, $issues),
            state: $this->readState($raw, $issues),
            author: $this->readString($raw, 'author'),
            authorEmail: $this->readString($raw, 'author_email'),
            authorCompany: $this->readString($raw, 'author_company'),
            constraints: $this->readConstraints($raw, $issues),
            autoload: $this->readAutoload($raw, 'autoload'),
            autoloadDev: $this->readAutoload($raw, 'autoload-dev'),
            clearCacheOnLoad: $this->readNullableBool($raw, 'clearCacheOnLoad'),
            uploadfolder: $this->readNullableBool($raw, 'uploadfolder'),
            createDirs: array_key_exists('createDirs', $raw) && is_string($raw['createDirs'])
                ? $raw['createDirs']
                : null,
            unknownProperties: $unknownProperties,
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function readString(array $raw, string $key): string
    {
        $value = $raw[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<Issue>          $issues
     */
    private function readCategory(array $raw, array &$issues): Category|string|null
    {
        if (!array_key_exists('category', $raw)) {
            return null;
        }

        $value = $raw['category'];
        if (!is_string($value) || $value === '') {
            return null;
        }

        $category = Category::tryFrom($value);
        if ($category !== null) {
            return $category;
        }

        $issues[] = new Issue(
            path: 'category',
            message: sprintf('Unrecognised category value "%s".', $value),
            severity: IssueSeverity::WARNING,
        );

        return $value;
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<Issue>          $issues
     */
    private function readState(array $raw, array &$issues): State|string|null
    {
        if (!array_key_exists('state', $raw)) {
            return null;
        }

        $value = $raw['state'];
        if (!is_string($value) || $value === '') {
            return null;
        }

        $state = State::tryFrom($value);
        if ($state !== null) {
            return $state;
        }

        $issues[] = new Issue(
            path: 'state',
            message: sprintf('Unrecognised state value "%s".', $value),
            severity: IssueSeverity::WARNING,
        );

        return $value;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function readNullableBool(array $raw, string $key): ?bool
    {
        if (!array_key_exists($key, $raw)) {
            return null;
        }

        $value = $raw[$key];
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<Issue>          $issues
     */
    private function readConstraints(array $raw, array &$issues): Constraints
    {
        $constraintsRaw = $raw['constraints'] ?? [];
        if (!is_array($constraintsRaw)) {
            return new Constraints();
        }

        return new Constraints(
            depends: $this->readDependencyCollection($constraintsRaw, 'depends', $issues),
            conflicts: $this->readDependencyCollection($constraintsRaw, 'conflicts', $issues),
            suggests: $this->readDependencyCollection($constraintsRaw, 'suggests', $issues),
        );
    }

    /**
     * @param array<string, mixed> $constraintsRaw
     * @param list<Issue>          $issues
     */
    private function readDependencyCollection(
        array $constraintsRaw,
        string $group,
        array &$issues,
    ): DependencyCollection {
        $groupRaw = $constraintsRaw[$group] ?? [];
        if (!is_array($groupRaw)) {
            return new DependencyCollection();
        }

        $dependencies = [];
        foreach ($groupRaw as $extKey => $versionStr) {
            if (!is_string($extKey) || $extKey === '') {
                continue;
            }

            $versionValue = $this->parseVersionValue(
                (string)$versionStr,
                "constraints.{$group}.{$extKey}",
                $issues,
            );

            $versionType = match (true) {
                $versionValue instanceof AllVersions => ConstraintVersionType::ALL,
                $versionValue instanceof SingleVersion => ConstraintVersionType::VERSION,
                default => ConstraintVersionType::RANGE,
            };

            $dependencies[] = new Dependency(
                extensionKey: $extKey,
                versionType: $versionType,
                version: $versionValue,
            );
        }

        return new DependencyCollection($dependencies);
    }

    /**
     * @param list<Issue> $issues
     */
    private function parseVersionValue(
        string $raw,
        string $path,
        array &$issues,
    ): VersionValueInterface {
        $trimmed = trim($raw);

        if ($trimmed === '' || $trimmed === '*') {
            return new AllVersions();
        }

        if (preg_match('/^\d+\.\d+\.\d+-\d+\.\d+\.\d+$/', $trimmed) === 1) {
            [$lowestStr, $highestStr] = explode('-', $trimmed, 2);
            try {
                return new VersionRange(
                    lowest: Version::fromString($lowestStr),
                    highest: Version::fromString($highestStr),
                );
            } catch (\UnexpectedValueException) {
                $issues[] = new Issue(
                    path: $path,
                    message: sprintf('Invalid version range "%s".', $raw),
                    severity: IssueSeverity::WARNING,
                );

                return new AllVersions();
            }
        }

        try {
            return new SingleVersion(Version::fromString($trimmed));
        } catch (\UnexpectedValueException) {
            $issues[] = new Issue(
                path: $path,
                message: sprintf('Invalid version string "%s".', $raw),
                severity: IssueSeverity::WARNING,
            );

            return new AllVersions();
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function readAutoload(array $raw, string $key): Autoload
    {
        $autoloadRaw = $raw[$key] ?? [];
        if (!is_array($autoloadRaw)) {
            return new Autoload();
        }

        $psr4Entries = [];
        $psr4Raw = $autoloadRaw['psr-4'] ?? [];
        if (is_array($psr4Raw)) {
            foreach ($psr4Raw as $namespace => $path) {
                if (is_string($namespace) && is_string($path)) {
                    $psr4Entries[] = new Psr4Entry(namespace: $namespace, path: $path);
                }
            }
        }

        $classmapEntries = [];
        $classmapRaw = $autoloadRaw['classmap'] ?? [];
        if (is_array($classmapRaw)) {
            foreach ($classmapRaw as $entry) {
                if (is_string($entry)) {
                    $classmapEntries[] = new ClassmapEntry(path: $entry);
                }
            }
        }

        return new Autoload(
            psr4: new Psr4Collection($psr4Entries),
            classmap: new ClassmapCollection($classmapEntries),
        );
    }
}
