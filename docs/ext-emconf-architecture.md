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
    Version                    Semver-aware version value object (Stringable)
    Autoload                   Autoload / autoload-dev configuration
    Autoload\
      Psr4Entry                Single PSR-4 namespace-prefix → path entry
      Psr4Collection           Ordered collection of Psr4Entry (Countable, IteratorAggregate)
      ClassmapEntry            Single classmap path entry
      ClassmapCollection       Ordered collection of ClassmapEntry (Countable, IteratorAggregate)
    Constraints\
      Constraints              Wraps the three dependency collections
      DependencyCollection     Ordered list of Dependency items
      Dependency               Extension key + ConstraintVersionType + VersionValueInterface
      VersionValue\
        VersionValueInterface  Common interface: toVersionString(): string
        AllVersions            Represents '' or '*' (any version)
        SingleVersion          Represents a single 'x.y.z' version
        VersionRange           Represents a 'lowest-highest' range
  Enum\
    Category                   String-backed enum for the category field
    State                      String-backed enum for the state field
    ConstraintVersionType      Pure enum: ALL | VERSION | RANGE
    VersionFormat              Pure enum: XYZ | XYZ_D | XYZ_STABILITY
    Typo3Version               Int-backed enum: V8 | V9 | V10 | V11 | V12 | V13
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

All classes, enums, and interfaces carry `@internal not part of public API`.

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
supports that field. The validator flags deprecated fields as `WARNING` for
TYPO3 versions where they are deprecated.

---

## DTO Details

### Version

`Version` is a `final readonly` class implementing `Stringable` and used
exclusively for extension version numbers on `ExtEmConf` and within
constraint value objects.

`__toString()` returns the `XYZ` format. The `format(VersionFormat $format)`
method supports three formats:

| `VersionFormat` case | Output example |
|---|---|
| `XYZ` (default) | `1.2.3` |
| `XYZ_D` | `1.2.3.0` |
| `XYZ_STABILITY` | `1.2.3-alpha` (falls back to `XYZ` when no stability) |

The static factory `Version::fromString(string $raw): self` uses
`Composer\Semver\VersionParser` (v3.4.4) internally. The parser is **not**
exposed in the public API. `composer/semver` does not provide a version
value-object — we create our own.

### Typo3Version

`Typo3Version` is an int-backed enum representing supported TYPO3 major
releases. It is used exclusively as the `$targetTypo3Version` parameter
in the three services. `Version` is never used for this purpose.

```php
enum Typo3Version: int {
    case V8 = 8;  case V9 = 9;   case V10 = 10;
    case V11 = 11; case V12 = 12; case V13 = 13;
}
```

The integer backing value enables simple `>=` comparisons inside the services.

**Important simplification:** the `clearCacheOnLoad` field was technically
deprecated in TYPO3 12.1. Because `Typo3Version` uses only major versions,
`V12` is treated as the deprecation boundary — all of 12.x is considered
the version where `clearCacheOnLoad` is deprecated.

### Constraint version values

Three concrete classes implement `VersionValueInterface`. The interface
provides a single dispatch method `toVersionString(): string` so callers
never need `instanceof` branching:

| Class | Represents | `toVersionString()` |
|---|---|---|
| `AllVersions` | Any version (`''` or `'*'`) | `''` |
| `SingleVersion` | One exact version | `'11.3.0'` |
| `VersionRange` | Inclusive range `lowest-highest` | `'12.4.0-13.4.99'` |

`Dependency` holds both the typed `VersionValueInterface $version` and a
`ConstraintVersionType $versionType` discriminant (for callers that prefer
a pure-enum branch over a method call):

```php
public ConstraintVersionType $versionType,  // ALL | VERSION | RANGE
public VersionValueInterface $version,
```

### Autoload

```php
final readonly class Autoload {
    public function __construct(
        public Psr4Collection $psr4 = new Psr4Collection(),
        public ClassmapCollection $classmap = new ClassmapCollection(),
    ) {}
}
```

`Psr4Collection` iterates as `string → Psr4Entry` (keyed by namespace).
`ClassmapCollection` iterates as `int → ClassmapEntry`.

Each entry type is a final readonly class:

```php
final readonly class Psr4Entry    { public string $namespace, $path; }
final readonly class ClassmapEntry { public string $path; }
```

Both collections implement `Countable` and `IteratorAggregate` and provide
`all(): list<...>` and `isEmpty(): bool` methods, following the same pattern
as `DependencyCollection`.

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

**Input:** `ExtEmConf $dto`, `?Typo3Version $targetTypo3Version = null`

**Contract:** pure read — never mutates the DTO.

When `$targetTypo3Version` is supplied, any field deprecated in that version
is flagged as `WARNING`. Without a target version only general structural
rules are applied.

**Output:** `ValidationResult`
- `bool isValid()` — true when no ERROR issues are present
- `ResultState $state`
- `list<Issue> $issues`

### ExtEmConfNormalizer

**Input:** `ExtEmConf $dto`, `?Typo3Version $targetTypo3Version = null`

**Contract:** produces a **new** `ExtEmConf` instance. The original is
never modified.

Fields not valid for the target version are omitted from the new object.
Each omitted field is recorded in `$droppedItems` as an `Issue` with
severity `WARNING`. `$unknownProperties` is always reset to `[]`.

**Output:** `NormalizationResult`
- `ExtEmConf $extEmConf` — new, clean instance
- `bool $hasChanges` — true when anything was dropped
- `ResultState $state`
- `list<Issue> $droppedItems`

### ExtEmConfWriter

**Input:** `ExtEmConf $dto`, `?Typo3Version $targetTypo3Version = null`

**Contract:** returns the PHP source code for a valid `ext_emconf.php` as a
string (no file I/O). The caller decides where to write it.

When `$targetTypo3Version` is supplied, the writer omits fields not
supported in that version. Deprecated fields that are still supported are
included with an inline `// @deprecated` comment. Fields in
`$unknownProperties` are never written regardless of configuration.

**Typo3Version deprecation thresholds in the writer:**
- `$targetTypo3Version->value >= 9` — omit `uploadfolder`, `createDirs`
- `$targetTypo3Version->value >= 12` — omit `clearCacheOnLoad`

**Output:** `string` (PHP source)

---

## Result Infrastructure

```
ResultState   (enum)        SUCCESS | WARNING | FAILURE   — overall state
IssueSeverity (enum)        WARNING | ERROR               — per-issue severity
Issue         (DTO)         path (dot notation) + message + IssueSeverity
ReadResult    (DTO)         ExtEmConf|null + ResultState + list<Issue>
ValidationResult (DTO)      bool isValid() + ResultState + list<Issue>
NormalizationResult (DTO)   ExtEmConf + bool $hasChanges + ResultState + list<Issue>
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

Cover DTO construction, `Version::fromString()` parsing, `VersionFormat`
output, constraint version detection, and enum fallback behaviour.

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

### Test conventions

1. **No `setUp()` / class properties for subjects.** Instantiate services
   and DTOs directly inside each test method.
2. **No private helper methods** (`fixtureFile()`, `validDto()`, etc.).
   Inline all construction directly in each test method.
3. **Named arguments follow constructor definition order.** Skipping optional
   parameters is fine; reordering them is not.
