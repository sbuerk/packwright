# ExtEmConf Subsystem — Architecture Reference

This document describes the design of the `SBReleaser\ExtEmConf\` subsystem,
which handles reading, validating, normalizing, and writing TYPO3
`ext_emconf.php` files. It serves as both a human-readable design guide and
a living lookup reference for future changes.

> Update this document whenever a class is added, removed, or has its contract
> changed within the `ExtEmConf` subsystem.

---

## Purpose

TYPO3 extensions carry a PHP file called `ext_emconf.php` that declares
metadata: title, description, version, category, state, author information,
dependency constraints, and autoloading configuration. The format has evolved
across TYPO3 versions (8.7 through 13.4) and was deprecated entirely in
TYPO3 14.2, though it remains required for Classic-mode installations and
TER uploads.

This subsystem provides a typed, version-aware object model for the file and
four stateless services to work with it.

TYPO3 documentation references:
- v13.4: https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
- v12.4: https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
- v11.5: https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ExtensionArchitecture/FileStructure/ExtEmconf.html
- v10.4: https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ExtensionArchitecture/DeclarationFile/Index.html
- v9.5:  https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ExtensionArchitecture/DeclarationFile/Index.html
- v8.7:  https://docs.typo3.org/m/typo3/reference-coreapi/8.7/en-us/ExtensionArchitecture/DeclarationFile/Index.html

---

## Namespace Layout

```
SBReleaser\ExtEmConf\
  Dto\                         Data Transfer Objects (immutable, final readonly)
    ExtEmConf                  Root DTO — one instance per file
    Version                    Semver-aware version (Stringable)
    Autoload                   Autoload / autoload-dev configuration
    Constraints\
      Constraints              Wraps the three dependency collections
      DependencyCollection     Ordered list of Dependency items
      Dependency               Extension key + version constraint value
      VersionValue\
        AllVersions            Represents '' or '*' (any version)
        SingleVersion          Represents a single 'x.y.z' version
        VersionRange           Represents a 'lowest-highest' range
  Enum\
    Category                   String-backed enum for the category field
    State                      String-backed enum for the state field
    ConstraintVersionType      Pure enum: ALL | VERSION | RANGE
    ResultState                Pure enum: SUCCESS | WARNING | FAILURE
    IssueSeverity              Pure enum: WARNING | ERROR
  Result\
    Issue                      Single diagnostic item (path + message + severity)
    ReadResult                 Outcome of ExtEmConfReader::read()
    ValidationResult           Outcome of ExtEmConfValidator::validate()
    NormalizationResult        Outcome of ExtEmConfNormalizer::normalize()
  Service\
    ExtEmConfReader            Reads an ext_emconf.php file → ReadResult
    ExtEmConfValidator         Validates an ExtEmConf DTO → ValidationResult
    ExtEmConfNormalizer        Strips fields invalid for a TYPO3 version → NormalizationResult
    ExtEmConfWriter            Renders an ExtEmConf DTO as PHP file content
```

---

## Field Matrix

The table below lists every known `ext_emconf.php` key, its availability per
TYPO3 version, and notes for the implementation. `✓` = active, `dep` =
deprecated in that version, `—` = not present / long gone.

| Field | 8.7 | 9.5 | 10.4 | 11.5 | 12.4 | 13.4 | Type | Notes |
|---|---|---|---|---|---|---|---|---|
| `title` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | Required |
| `description` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | Required |
| `version` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | `x.y.z` format |
| `category` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | See Category enum |
| `state` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | See State enum |
| `author` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | |
| `author_email` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | Validated as email |
| `author_company` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | string | |
| `constraints` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | array | depends/conflicts/suggests |
| `autoload` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | array | psr-4, classmap |
| `autoload-dev` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | array | psr-4, classmap |
| `clearCacheOnLoad` | ✓ | ✓ | ✓ | ✓ | dep 12.1 | dep | bool | |
| `uploadfolder` | ✓ | dep | dep | dep | dep | dep | bool | |
| `createDirs` | ✓ | dep | dep | dep | dep | dep | string | Comma-separated paths |
| `dependencies` | dep | — | — | — | — | — | array | Superseded by constraints |
| `conflicts` (top-level) | dep | — | — | — | — | — | array | Superseded by constraints |
| `suggests` (top-level) | dep | — | — | — | — | — | array | Superseded by constraints |
| `docPath` | dep | — | — | — | — | — | string | |
| `CGLcompliance` | dep | — | — | — | — | — | mixed | |
| `CGLcompliance_note` | dep | — | — | — | — | — | string | |
| `private` | dep | — | — | — | — | — | bool | |
| `download_password` | dep | — | — | — | — | — | string | |
| `shy` | dep | — | — | — | — | — | bool | |
| `loadOrder` | dep | — | — | — | — | — | string | |
| `priority` | dep | — | — | — | — | — | string | |
| `internal` | dep | — | — | — | — | — | bool | |
| `modify_tables` | dep | — | — | — | — | — | string | |
| `module` | dep | — | — | — | — | — | string | |
| `lockType` | dep | — | — | — | — | — | string | |
| `TYPO3_version` | dep | — | — | — | — | — | string | Superseded by constraints |
| `PHP_version` | dep | — | — | — | — | — | string | Superseded by constraints |

### Category enum values

`be`, `module`, `fe`, `plugin`, `misc`, `services`, `templates`, `example`,
`doc`, `distribution`

### State enum values

`alpha`, `beta`, `stable`, `experimental`, `test`, `obsolete`,
`excludeFromUpdates`

---

## Design Principles

### Immutability

All DTOs are `final readonly` classes. PHP readonly classes make all promoted
constructor properties implicitly readonly — do **not** repeat the `readonly`
keyword on individual properties inside a readonly class.

### Nullable-by-default for DTO fields

Every DTO property is nullable or has a sensible empty default. The DTO
represents *what was present in the file*, not *what a valid file must
contain*. Validation is a separate concern performed by `ExtEmConfValidator`.

### Nullable typed enums

`Category` and `State` use union types in the root DTO:

```php
public Category|string|null $category = null,
public State|string|null $state = null,
```

The reader tries `Category::tryFrom($value)` and falls back to the raw
string. A raw string is flagged as `WARNING` by the validator. The normalizer
drops raw strings that cannot be resolved to an enum case.

### Unknown properties

`ExtEmConf` carries:

```php
/** @var array<string, mixed> */
public array $unknownProperties = [],
```

The reader places every unrecognised key here. The validator flags each
entry as `WARNING`. The normalizer and writer silently ignore
`$unknownProperties`. Tests assert that nothing in `$unknownProperties` ever
appears in writer output.

### Deprecated fields

All deprecated fields are present as nullable properties on `ExtEmConf` so
that existing files round-trip correctly. The writer emits them with an
inline `// @deprecated` comment and only when the target TYPO3 version still
supported that field. The validator flags deprecated fields as `WARNING` for
TYPO3 versions where they are deprecated.

---

## DTO Details

### Version

`Version` is a `final readonly` class implementing `Stringable`.
`__toString()` returns `x.y.z`. A `format(string $pattern): string` method
supports the patterns `x.y.z`, `x.y.z.d`, and `x.y.z-stability`.

The static factory `Version::fromString(string $raw): self` uses
`Composer\Semver\VersionParser` (v3.4.4) internally to normalize and
validate the string. The parser is **not** exposed in the public API; it is
an implementation detail. `composer/semver` does not provide a version
value-object — we create our own.

### Constraint version values

Three concrete classes represent the three possible forms of a dependency
version string. No shared interface is needed — the union type is the
contract:

| Class | Represents | Example |
|---|---|---|
| `AllVersions` | Any version (`''` or `'*'`) | `'typo3' => ''` |
| `SingleVersion` | One exact version | `'news' => '11.3.0'` |
| `VersionRange` | Inclusive range `lowest-highest` | `'typo3' => '12.4.0-13.4.99'` |

Detection in the reader: the pattern `/^\d+\.\d+\.\d+-\d+\.\d+\.\d+$/`
identifies a range; an empty string or `'*'` maps to `AllVersions`;
anything else is attempted as `SingleVersion` via `Version::fromString()`.

`Dependency` holds both the typed version value and the corresponding
`ConstraintVersionType` enum case so callers can switch on type without
`instanceof` checks:

```php
public ConstraintVersionType $versionType,
public AllVersions|SingleVersion|VersionRange $version,
```

### Autoload

```php
final readonly class Autoload {
    /**
     * @param array<string, string> $psr4   namespace prefix → path
     * @param list<string>          $classmap  directory/file paths
     */
    public function __construct(
        public array $psr4 = [],
        public array $classmap = [],
    ) {}
}
```

The `autoload-dev` top-level key maps to `ExtEmConf::$autoloadDev`
(camelCase, no dash).

---

## Service Contracts

### ExtEmConfReader

**Input:** `string $filePath`

**Strategy:** includes the file inside an isolated closure scope with
`$_EXTKEY` pre-set to a sentinel value, then reads `$EM_CONF[$_EXTKEY]`.
The entire operation is wrapped in a try/catch — any `Throwable` produces
a `FAILURE` result with no DTO and a single `ERROR` issue.

**Output:** `ReadResult`
- `ExtEmConf|null $extEmConf` — null on FAILURE
- `ResultState $state`
- `list<Issue> $issues` — warnings for unknown keys, deprecated fields,
  invalid email format, unresolvable enum values

### ExtEmConfValidator

**Input:** `ExtEmConf $dto`, `Version|null $minimumTypo3Version = null`

**Contract:** pure read — never mutates the DTO.

When `$minimumTypo3Version` is supplied, any field that was already
deprecated in that version is flagged as `WARNING`. Fields that never
existed in that version are flagged as `ERROR`.

**Output:** `ValidationResult`
- `bool $valid` — true when no ERROR issues are present
- `ResultState $state`
- `list<Issue> $issues`

### ExtEmConfNormalizer

**Input:** `ExtEmConf $dto`, `Version $targetTypo3Version`

**Contract:** produces a **new** `ExtEmConf` instance. The original is
never modified.

Fields not valid for `>= targetTypo3Version` are omitted from the new
object. Each omitted field is recorded in `$droppedItems` as an `Issue`
with severity `WARNING` (not ERROR — omissions are expected, not failures).
`$unknownProperties` is reset to `[]` in the normalized object.

**Output:** `NormalizationResult`
- `ExtEmConf $normalizedExtEmConf`
- `bool $hasChanges` — diff flag, true when anything was dropped
- `ResultState $state`
- `list<Issue> $droppedItems`

### ExtEmConfWriter

**Input:** `ExtEmConf $dto`, `Version|null $targetTypo3Version = null`

**Contract:** returns the PHP source code for a valid `ext_emconf.php` as a
string (no file I/O). The caller decides where to write it.

When `$targetTypo3Version` is supplied, the writer omits fields not
supported in that version. Deprecated fields that are still supported are
included with an inline `// @deprecated` comment. Fields in
`$unknownProperties` are never written regardless of configuration.

**Output:** `string` (PHP source)

---

## Result Infrastructure

```
ResultState   (enum)     SUCCESS | WARNING | FAILURE   — overall operation state
IssueSeverity (enum)     WARNING | ERROR               — per-issue severity
Issue         (DTO)      path (dot notation) + message + IssueSeverity
ReadResult    (DTO)      ExtEmConf|null + ResultState + list<Issue>
ValidationResult (DTO)   bool $valid + ResultState + list<Issue>
NormalizationResult (DTO) ExtEmConf + bool $hasChanges + ResultState + list<Issue>
```

Issue paths use dot notation mirroring the array key path in the source
file, e.g. `constraints.depends.typo3` or `author_email`.

---

## Dependency

`composer/semver` ^3.0 (currently v3.4.4) is a `require` dependency (not
`require-dev`) because `Version::fromString()` is used at runtime by the
reader service.

---

## Testing Strategy

### Unit tests (`tests/Unit/ExtEmConf/`)

Cover DTO construction, `Version::fromString()` parsing, `format()` output,
constraint version detection, and enum fallback behaviour.

### Functional tests (`tests/Functional/ExtEmConf/`)

Use real `ext_emconf.php` fixture files. Each fixture is a complete,
self-contained file that `ExtEmConfReader` can include. Fixtures:

| Fixture | Purpose |
|---|---|
| `v8_full/` | All v8.7 fields including long-deprecated ones |
| `v9_full/` | Active v9.5 fields + `uploadfolder`/`createDirs` deprecated |
| `v12_full/` | Active v12.4 fields + `clearCacheOnLoad` deprecated |
| `v13_full/` | Current active fields only |
| `minimal/` | Only `title` and `description` |
| `invalid_keys/` | Contains unknown keys alongside valid ones |
| `deprecated_keys/` | Contains only deprecated keys to test warning collection |

Tests cover the full pipeline: read → validate → normalize → write.
A dedicated assertion confirms that keys from `$unknownProperties` are
absent in writer output.
