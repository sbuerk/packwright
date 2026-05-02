# ExtEmConf Subsystem — Architecture Reference

This document describes the design of the `Packwright\ExtEmConf\` subsystem,
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
Packwright\ExtEmConf\
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
      Constraints              Wraps the three dependency collections plus a MigratableConstraints DTO
      DependencyCollection     Ordered collection of Dependency items (Countable, IteratorAggregate, has/get by extensionKey)
      Dependency               Extension key + ConstraintVersionType + VersionValueInterface
      MigratableConstraints    Mirrors Constraints with three named DependencyCollection groups (depend / suggest / conflict)
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
    IssueCollection            Ordered collection of Issue items (Countable, IteratorAggregate, all/paths/withSeverity/...)
    ReadResult                 Outcome of ExtEmConfReader::read()
    ValidationResult           Outcome of ExtEmConfValidator::validate()
    NormalizationResult        Outcome of ExtEmConfNormalizer::normalize()
  Dto\Unknown\
    UnknownProperty            Single key/value pair for keys not in KNOWN_KEYS; value is mixed by design
    UnknownPropertyCollection  Keyed collection of UnknownProperty (Countable, IteratorAggregate, all/has/get/keys)
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
| `constraints` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | array | depends/conflicts/suggests; singular typos `depend`/`conflict`/`suggest` are migrated on normalization |
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
public UnknownPropertyCollection $unknownProperties = new UnknownPropertyCollection(),
```

The collection holds `UnknownProperty(string $key, mixed $value)` entries —
the value stays `mixed` because raw `ext_emconf.php` input is genuinely
arbitrary (strings, scalars, nested arrays). The reader builds one entry per
unrecognised key; the validator flags each via `unknownProperties->keys()`;
the normalizer replaces the collection with an empty one and the writer
silently ignores it. Tests assert via `unknownProperties->has($key)` that
nothing ever appears in writer output.

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
- `IssueCollection $issues` — warnings for unknown keys, deprecated fields,
  invalid email format, unresolvable enum values

### ExtEmConfValidator

**Input:** `ExtEmConf $dto`, `?Typo3Version $targetTypo3Version = null`

**Contract:** pure read — never mutates the DTO.

When `$targetTypo3Version` is supplied, any field deprecated in that version
is flagged as `WARNING`. Without a target version only general structural
rules are applied.

**Mandatory-field rules:**
- `title`, `description`, `version` — empty string ⇒ `ERROR`. The message
  hints that `extemconf:normalize` will write an empty string until a
  value is set.
- `category` — `null` (missing) or unrecognised raw string ⇒ `WARNING`.
  The message points the operator at `extemconf:normalize` and the
  `Category::MISC` (`"misc"`) default.
- `state` — `null` (missing) or unrecognised raw string ⇒ `ERROR`.
  `state` is mandatory per TYPO3 documentation; the message points the
  operator at `extemconf:normalize` and the `State::BETA` (`"beta"`)
  default.

**Output:** `ValidationResult`
- `bool isValid()` — true when no ERROR issues are present
- `ResultState $state`
- `IssueCollection $issues`

### ExtEmConfNormalizer

**Input:** `ExtEmConf $dto`, `?Typo3Version $targetTypo3Version = null`

**Contract:** produces a **new** `ExtEmConf` instance. The original is
never modified.

Fields not valid for the target version are omitted from the new object.
Each omitted field is recorded in `$droppedItems` as an `Issue` with
severity `WARNING`. `$unknownProperties` is always reset to an empty
`UnknownPropertyCollection`.

**Mandatory-field defaults:**
- `category` — when `null` or an unrecognised raw string, the normalized
  DTO carries `Category::MISC`. Both branches are recorded in
  `$droppedItems`.
- `state` — when `null` or an unrecognised raw string, the normalized
  DTO carries `State::BETA`. Both branches are recorded in
  `$droppedItems`.
- `title`, `description`, `version` — empty strings are preserved (the
  writer always emits them) but each empty value is recorded as a
  `WARNING` entry in `$droppedItems`. This keeps the validator's
  mandatory-field error and the normalizer's "wrote a placeholder"
  notice in sync, and ensures `hasChanges` flips so the file is
  rewritten with an explicit empty key when the source was missing it.

**Output:** `NormalizationResult`
- `ExtEmConf $extEmConf` — new, clean instance
- `bool $hasChanges` — true when anything was dropped or defaulted
- `ResultState $state`
- `IssueCollection $droppedItems`

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
ResultState        (enum)        SUCCESS | WARNING | FAILURE   — overall state
IssueSeverity      (enum)        WARNING | ERROR               — per-issue severity
Issue              (DTO)         path (dot notation) + message + IssueSeverity
IssueCollection    (Collection)  Countable + IteratorAggregate over Issue
ReadResult         (DTO)         ExtEmConf|null + ResultState + IssueCollection
ValidationResult   (DTO)         bool isValid() + ResultState + IssueCollection
NormalizationResult (DTO)        ExtEmConf + bool $hasChanges + ResultState + IssueCollection
```

`IssueCollection` exposes `all()`, `count()`, `isEmpty()`, `paths()`,
`withSeverity(IssueSeverity)`, `countBySeverity(IssueSeverity)`,
`hasErrors()`, `hasWarnings()`, and `merge(IssueCollection): self`.
`has`/`get` do not apply because issues are positional and the same
path can repeat.

Issue paths use dot notation mirroring the array key path in the source
file, e.g. `constraints.depends.typo3` or `author_email`. Typo'd
constraints sub-keys appear under their original (singular) form,
e.g. `constraints.depend.typo3`, until normalization rewrites them.

### Constraints typo migration

Three singular forms of the canonical constraints sub-keys are
recognised and migrated:

| Typo'd key | Canonical key |
|---|---|
| `depend` | `depends` |
| `suggest` | `suggests` |
| `conflict` | `conflicts` |

The reader captures entries written under any of these typo'd keys on
`Constraints::$migratable`, a `MigratableConstraints` DTO that mirrors
`Constraints` with three named `DependencyCollection` properties
(`$depend`, `$suggest`, `$conflict`). The `Issue` instances surfaced
along the pipeline are:

* `ExtEmConfReader` — one `WARNING` per typo group on path
  `constraints.{$typoKey}` plus the same per-entry version-string
  warnings that the canonical groups receive.
* `ExtEmConfValidator` — one `WARNING` per typo group hinting at
  `extemconf:normalize` and one `WARNING` per typo entry whose
  extension key collides with an existing canonical entry.
* `ExtEmConfNormalizer` — merges non-colliding entries into the
  canonical collection and replaces `$migratable` with a fresh empty
  `MigratableConstraints`. Each migration emits a `WARNING` on
  `constraints.{$canonicalKey}.{$extKey}`. Collisions keep the
  canonical entry, drop the typo'd one, and emit a `WARNING` on
  `constraints.{$typoKey}.{$extKey}`. Migration always flips
  `NormalizationResult::$hasChanges` to `true`.

The writer never emits the `MigratableConstraints` DTO — only the
canonical groups are rendered, so the rewritten file is free of
typo'd keys.

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
| `constraints_typo/` | Uses singular `depend`/`suggest`/`conflict` constraints sub-keys |
| `constraints_typo_collision/` | Same extension key in canonical and typo'd constraint groups |

Tests cover the full pipeline: read → validate → normalize → write.
A dedicated assertion confirms that keys held by
`$unknownProperties` (queried via `->has()`) are absent from writer
output.

---

## Collections and DTOs

The codebase enforces a project rule that **plain `array` properties
are forbidden on classes that are not themselves a Collection or
Container**. Replacements use one of these patterns:

- **Typed DTO** — for an associative array of scalars: declare each
  field as a strongly-typed property.
- **Keyed Collection** — for an associative array of objects:
  introduce a `final readonly class XCollection implements Countable,
  IteratorAggregate` with `private array $items` storage and the
  surface `all() / count() / isEmpty() / has(string) / get(string)`.
- **List Collection** — for a list of objects without a stable
  identifier: same shape minus `has`/`get`, plus domain-specific
  filters (e.g. `IssueCollection::withSeverity()` and `paths()`).

Collections in `src/`:

| Class | Item | Identifier | has/get |
|---|---|---|---|
| `IssueCollection` | `Issue` | none (positional) | no |
| `DependencyCollection` | `Dependency` | `extensionKey` | yes |
| `Psr4Collection` | `Psr4Entry` | `namespace` | yes |
| `ClassmapCollection` | `ClassmapEntry` | `path` | yes |
| `UnknownPropertyCollection` | `UnknownProperty` | `key` | yes |

`UnknownProperty::$value` is intentionally typed `mixed`: raw
`ext_emconf.php` files can put any structure under an unknown key,
and the entire pipeline only ever ignores or echoes the value, never
inspects it. This is a documented exception to the rule.

### Test conventions

1. **No `setUp()` / class properties for subjects.** Instantiate services
   and DTOs directly inside each test method.
2. **No private helper methods** (`fixtureFile()`, `validDto()`, etc.).
   Inline all construction directly in each test method.
3. **Named arguments follow constructor definition order.** Skipping optional
   parameters is fine; reordering them is not.

---

## Console output

`Packwright\ExtEmConf\Service\PathResolver` returns sorted, deduplicated
absolute paths via `realpath()` — those are the canonical form used in
JSON output and in all internal logic.

For human-readable table output, both console commands run paths through
`Packwright\Console\DisplayPathFormatter`. The formatter is purely
string-based: it compares the absolute path against the current working
directory and prepends `./` when the path is beneath it; otherwise it
returns the path unchanged. It never touches the filesystem, so a
symlinked working directory whose realpath differs from `getcwd()` simply
falls through to absolute paths — predictable and explicit. JSON output
is always absolute and never goes through the formatter.
