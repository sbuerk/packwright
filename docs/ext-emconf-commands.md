# ExtEmConf Console Commands

This document covers the user-facing console commands that wrap the
`Packwright\ExtEmConf\` services for batch operations on TYPO3
`ext_emconf.php` files. For the underlying object model and service
contracts, see [`ext-emconf-architecture.md`](ext-emconf-architecture.md).

Both commands share the same input semantics and TYPO3 version selector,
and both can emit a JSON document for piping into other tools.

The examples below all invoke `packwright`; the short alias `pkw` is
interchangeable on the command line.

---

## `extemconf:validate`

Reads each input file, runs `ExtEmConfValidator`, and reports the result
either as a table or as a JSON document.

### Usage

```
packwright extemconf:validate <paths...> [-t <version>] [--json] [--fail-on-warning] [-v]
```

### Arguments

- `paths` — one or more file paths, directories, or glob patterns. Required.
  - A literal file path must exist.
  - A directory is walked recursively for `ext_emconf.php` files.
  - A pattern containing `*`, `?`, `[`, or `{` is resolved via `glob()`
    with `GLOB_BRACE`. A single `*` only matches one path segment.
  - A pattern containing a `**` path segment is recursive: `**` matches
    zero or more intermediate path segments, allowing one pattern to
    span any depth. Quote the pattern in the shell to keep it from
    being expanded by the shell first. Examples:
    - `'packages/**/ext_emconf.php'` — every `ext_emconf.php` under
      `packages/`, regardless of nesting depth (including
      `packages/ext_emconf.php` itself).
    - `'packages/*/**/ext_emconf.php'` — same, but constrained to
      exactly one vendor directory under `packages/`.

### Options

- `-t`, `--typo3-version` — major version to validate against.
  Allowed values: `8`, `9`, `10`, `11`, `12`, `13`. When omitted, version-specific
  rules are skipped.
- `--json` — print a JSON document instead of a table; suppresses all other
  output so the result can be piped.
- `--fail-on-warning` — exit with code `1` when any file ends in `WARNING`
  state. Without this flag, only `FAILURE` files trigger a non-zero exit.
- `-v` — verbose mode lists every issue under the table
  (`severity  path  message`). When `-v` is omitted and at least one file
  reported issues, the table is followed by a `Tip: re-run with -v …` line
  in table mode.

### Exit codes

- `0` — no `FAILURE` (and, when `--fail-on-warning` is given, also no
  `WARNING`).
- `1` — at least one file in `FAILURE` state, **or** any file in `WARNING`
  state when `--fail-on-warning` is set.
- `2` — usage error (no files matched, missing literal path, invalid
  `--typo3-version`).

### Mandatory-field rules

Validate flags the following conditions and tells the operator what
`extemconf:normalize` would do about them:

- `title`, `description`, `version` — empty values produce an `ERROR`
  with a hint that `extemconf:normalize` will write an empty string
  until a real value is set.
- `category` — missing (`null`) or unrecognised raw string produces a
  `WARNING`. `extemconf:normalize` substitutes the
  `Category::MISC` (`"misc"`) default.
- `state` — missing (`null`) or unrecognised raw string produces an
  `ERROR`. `extemconf:normalize` substitutes the
  `State::BETA` (`"beta"`) default.
- Constraints sub-key typos — entries written under the singular
  forms `constraints.depend`, `constraints.suggest`, or
  `constraints.conflict` produce one `WARNING` per group hinting that
  `extemconf:normalize` will migrate them to the canonical plural
  key. When the same extension key already exists in the canonical
  group, an additional `WARNING` is emitted on
  `constraints.{$typoKey}.{$extKey}` indicating that the typo'd entry
  will be skipped during normalization.

### Table layout

```
+--------------------------------------+----------+---------+--------+----------+
| File                                 | TYPO3    | State   | Errors | Warnings |
+--------------------------------------+----------+---------+--------+----------+
| ./packages/my_ext/ext_emconf.php     | TYPO3 v13| SUCCESS | 0      | 0        |
+--------------------------------------+----------+---------+--------+----------+
```

The `TYPO3` column reads `any` when `--typo3-version` is omitted.

Paths beneath the current working directory render relative (with a
leading `./`); paths elsewhere render absolute. JSON output always emits
absolute paths.

### JSON shape

```json
{
  "command": "extemconf:validate",
  "typo3Version": 13,
  "files": [
    {
      "path": "/abs/path/ext_emconf.php",
      "state": "WARNING",
      "errorCount": 0,
      "warningCount": 1,
      "issues": [
        { "severity": "WARNING", "path": "uploadfolder", "message": "..." }
      ]
    }
  ],
  "summary": { "total": 1, "success": 0, "warning": 1, "failure": 0 }
}
```

`typo3Version` is `null` when `--typo3-version` was not supplied.

---

## `extemconf:get`

Reads each input file and prints selected scalar fields. Read-only:
the validator and writer are not involved.

### Usage

```
packwright extemconf:get <paths...> [-f <field>] [--json] [-t <version>]
```

### Arguments

Same semantics as `extemconf:validate` (variadic literal/file/directory/glob).

### Options

- `-f`, `--field` — repeatable; restrict output to one or more of:
  `title`, `description`, `version`, `category`, `state`, `author`,
  `author_email`, `author_company`. When omitted, all fields are
  printed in that order. Field names mirror the snake_case keys in
  `ext_emconf.php`.
- `--json` — emit a JSON document instead of one table per file.
- `-t`, `--typo3-version` — accepted for symmetry with the other
  `extemconf:*` commands but does not affect output (read-only
  command). Invalid values still produce `Command::INVALID`.

### Output

Default (one block per file):

```
./packages/my_ext/ext_emconf.php
+----------+-------------------+
| Field    | Value             |
+----------+-------------------+
| title    | My Extension      |
| version  | 1.2.3             |
| state    | stable            |
+----------+-------------------+
```

Empty strings render as `(empty)` and missing optional values (e.g.
`category` is `null`) render as `(null)`.

### JSON shape

```json
{
  "command": "extemconf:get",
  "files": [
    {
      "path": "/abs/path/ext_emconf.php",
      "fields": { "title": "My Extension", "state": "stable" }
    }
  ]
}
```

A file that fails to read is emitted with `error` instead of `fields`
and contributes to exit code `1`.

### Exit codes

- `0` — every file read successfully.
- `1` — at least one file failed to read.
- `2` — usage error (no files matched, missing literal path, invalid
  `--typo3-version`, unknown `--field`).

---

## `extemconf:normalize`

Reads each input file, runs `ExtEmConfNormalizer`, and writes the result
back through `ExtEmConfWriter`. By default the normalized content is
written to disk; pass `--dry-run` to preview only.

### Usage

```
packwright extemconf:normalize <paths...> [-t <version>] [--dry-run] [--json] [-v]
```

### Arguments

Same semantics as `extemconf:validate`: variadic literal/file/directory/glob.

### Options

- `-t`, `--typo3-version` — target version for normalization. Same allowed
  values as `validate`. Drives which deprecated fields are dropped (e.g.
  `clearCacheOnLoad` for `>= 12`, `uploadfolder`/`createDirs` for `>= 9`).
- `--dry-run` — do not write files; only report what would change.
- `--json` — print a JSON document instead of a table.
- `-v` — verbose mode lists dropped items under the table
  (`severity  path  message`). When `-v` is omitted and at least one file
  produced dropped items, the table is followed by a
  `Tip: re-run with -v …` line in table mode.

Paths beneath the current working directory render relative (with a
leading `./`); paths elsewhere render absolute. JSON output always emits
absolute paths.

### Per-file action

The `Action` column / JSON `action` field is one of:

- `written` — file was modified on disk.
- `dry-run` — `--dry-run` was set; file was left untouched.
- `unchanged` — normalization produced no changes; file was left untouched.
- `skipped` — read or normalize step failed; file was left untouched and
  the run contributes to exit code `1`.

Writes use `file_put_contents()` with `LOCK_EX`. Files without changes are
never re-written.

### Mandatory-field defaults

In addition to dropping deprecated fields, the normalizer fills in
defaults for fields that TYPO3 considers mandatory but the source file
left empty or unrecognised. Each default is recorded as a `WARNING`
entry in the JSON `droppedItems` list (and shown under `-v` in the
table view):

- `category` — `null` or unrecognised raw string ⇒ `"misc"`.
- `state` — `null` or unrecognised raw string ⇒ `"beta"`.
- `title`, `description`, `version` — an empty string is preserved
  (the writer always emits an explicit key) and recorded so the
  operator knows a placeholder was scaffolded; a real value still has
  to be set by hand.

### Constraints sub-key migration

Entries written under the singular typo'd forms `constraints.depend`,
`constraints.suggest`, and `constraints.conflict` are migrated to the
canonical plural keys (`depends`, `suggests`, `conflicts`). Each
migrated entry is recorded as a `WARNING` on
`constraints.{$canonicalKey}.{$extKey}` in `droppedItems`. When the
canonical group already contains an entry for the same extension key
the typo'd one is dropped, the canonical value is preserved, and the
collision is recorded as a `WARNING` on
`constraints.{$typoKey}.{$extKey}`. The rewritten file always emits
the canonical keys only.

### Exit codes

- `0` — every file produced `SUCCESS` or `WARNING` state.
- `1` — at least one file in `FAILURE` state (read or normalize).
- `2` — usage error (no files matched, missing literal path, invalid
  `--typo3-version`).

### JSON shape

```json
{
  "command": "extemconf:normalize",
  "typo3Version": 12,
  "dryRun": false,
  "files": [
    {
      "path": "/abs/path/ext_emconf.php",
      "state": "SUCCESS",
      "hasChanges": true,
      "droppedItems": [
        { "severity": "WARNING", "path": "uploadfolder", "message": "..." }
      ],
      "action": "written",
      "generated": "<?php\n\n$EM_CONF[$_EXTKEY] = [...];\n"
    }
  ],
  "summary": {
    "total": 1, "written": 1, "dryRun": 0, "unchanged": 0, "skipped": 0, "failure": 0
  }
}
```

`generated` carries the same string `ExtEmConfWriter::generate()` produces.
For `skipped` files it is `null`. With `--dry-run`, the `generated` value
shows what would have been written.

---

## `extemconf:set`

Reads each input file, applies one or more field overrides in memory,
runs `ExtEmConfValidator`, and (if validation does not fail) writes the
result through `ExtEmConfWriter`. Validation is **mandatory** — files
that would end up in `FAILURE` state are skipped without being touched.

### Usage

```
packwright extemconf:set <paths...> \
    [--title=...] [--description=...] [--ext-version=...] \
    [--state=...] [--category=...] \
    [--author=...] [--author-email=...] [--author-company=...] \
    [-t <version>] [--strict] [--dry-run] [--json]
```

### Arguments

Same semantics as `extemconf:validate` (variadic literal/file/directory/glob).

### Options

- One per editable scalar field. The CLI option for `version` is named
  `--ext-version` to avoid colliding with Symfony's application-level
  `--version` flag.
  | CLI option         | `ext_emconf.php` key |
  |--------------------|----------------------|
  | `--title`          | `title`              |
  | `--description`    | `description`        |
  | `--ext-version`    | `version`            |
  | `--state`          | `state`              |
  | `--category`       | `category`           |
  | `--author`         | `author`             |
  | `--author-email`   | `author_email`       |
  | `--author-company` | `author_company`     |
- `--state` is validated against the `State` enum (`alpha`, `beta`,
  `stable`, `experimental`, `test`, `obsolete`, `excludeFromUpdates`).
- `--category` is validated against the `Category` enum (`be`, `module`,
  `fe`, `plugin`, `misc`, `services`, `templates`, `example`, `doc`,
  `distribution`).
- Deprecated fields (`clearCacheOnLoad`, `uploadfolder`, `createDirs`)
  cannot be set — use `extemconf:normalize` to remove them.
- `-t`, `--typo3-version` — drives validation rules (deprecated-field
  warnings) and writer output (omits version-specific deprecated keys).
- `--strict` — require post-mutation state to be `SUCCESS` before
  writing. Without `--strict`, `WARNING` is also acceptable; only
  `FAILURE` blocks the write.
- `--dry-run` — do not write files; only report what would change.
- `--json` — emit a JSON document instead of a table.

### Per-file action

Same vocabulary as `extemconf:normalize`:

- `written` — overrides applied and persisted to disk.
- `dry-run` — `--dry-run` was set; file left untouched.
- `unchanged` — overrides produced byte-identical output; file left
  untouched.
- `skipped` — read failed, or post-mutation validation produced
  `FAILURE` (or `WARNING` under `--strict`). File is left untouched
  and the run contributes to exit code `1` only on `FAILURE`.

### Validation gate

`extemconf:set` always runs the validator on the *intended* DTO before
persisting. The write proceeds only when:

```
state === SUCCESS                      (always)
state === WARNING && !strict           (default)
```

`FAILURE` always blocks. `--strict` additionally blocks `WARNING`. This
prevents accidentally publishing changes that introduce structural
errors (e.g. setting `--ext-version="not-a-version"`).

### Exit codes

- `0` — every file produced `SUCCESS`/`WARNING`, or was already
  unchanged, or was a `--dry-run` preview.
- `1` — at least one file in `FAILURE` state (read or validation).
- `2` — usage error: no field given, no files matched, missing literal
  path, invalid `--typo3-version`, invalid `--state`/`--category`.

### JSON shape

```json
{
  "command": "extemconf:set",
  "typo3Version": 13,
  "strict": false,
  "dryRun": false,
  "files": [
    {
      "path": "/abs/path/ext_emconf.php",
      "state": "SUCCESS",
      "action": "written",
      "issues": [],
      "generated": "<?php\n\n$EM_CONF[$_EXTKEY] = [...];\n"
    }
  ],
  "summary": {
    "total": 1, "written": 1, "dryRun": 0, "unchanged": 0, "skipped": 0, "failure": 0
  }
}
```

`generated` carries what `ExtEmConfWriter::generate()` produced; `null`
for files in `skipped` state with no read result.

---

## `extemconf:constraints:set`

Adds or updates a single entry under `constraints.{depends|suggests|conflicts}`.
The mutated DTO is always validated through `ExtEmConfValidator` before
the writer is invoked, and the same write gate as `extemconf:set` applies.

### Usage

```
packwright extemconf:constraints:set <type> <extension-key> <paths...> \
    [--constraint=<spec>] [-t <version>] [--strict] [--dry-run] [--json]
```

### Arguments

- `<type>` — constraint group: `depends`, `suggests`, or `conflicts`.
- `<extension-key>` — TYPO3 extension key (lowercase letters, digits,
  and underscores; non-empty).
- `<paths>` — variadic, same semantics as `extemconf:validate`.

### Options

- `--constraint` — version constraint to write. Defaults to empty string,
  which means "any version" (`AllVersions`). `*` is also "any version".
  A single semver `1.2.3` produces `SingleVersion`; the form
  `X.Y.Z-X.Y.Z` produces `VersionRange`. Anything else exits
  `Command::INVALID` (exit 2) before any file is touched. The option
  is named `--constraint` rather than `--version` to avoid colliding
  with Symfony's application-level `--version` flag.
- `-t`, `--typo3-version` — drives validation and writer output, same as
  `extemconf:set`.
- `--strict` — require post-mutation state `SUCCESS`. Without
  `--strict`, `WARNING` is also acceptable; only `FAILURE` blocks the write.
- `--dry-run` — preview only.
- `--json` — emit a JSON document.

### Cross-key collision check

Before any mutation, the command scans the *other two* sub-keys of
`constraints` for `<extension-key>`. If found, the file is reported as
`FAILURE`/`skipped` with a message pointing the operator at
`extemconf:constraints:unset`. Other files in the same invocation are
still processed independently.

### Update vs. insert

If the key already exists under `<type>`, its version is replaced
(no duplicate entry is produced). If the key is new, it is appended.
A no-op write (existing entry already matches the new value) reports
`unchanged` and leaves the file untouched.

### Exit codes

- `0` — every file produced `SUCCESS`/`WARNING`, was unchanged, or was
  a `--dry-run` preview.
- `1` — at least one file in `FAILURE` (read failure, validation
  `FAILURE`, or cross-key collision).
- `2` — usage error (invalid type, invalid extension key, invalid
  `--constraint`, no files matched, missing literal path, invalid
  `--typo3-version`).

### JSON shape

```json
{
  "command": "extemconf:constraints:set",
  "type": "depends",
  "extensionKey": "extb",
  "version": "1.0.0-2.5.99",
  "typo3Version": 13,
  "strict": false,
  "dryRun": false,
  "files": [
    {
      "path": "/abs/path/ext_emconf.php",
      "state": "SUCCESS",
      "action": "written",
      "issues": [],
      "generated": "<?php ..."
    }
  ],
  "summary": {
    "total": 1, "written": 1, "dryRun": 0, "unchanged": 0, "skipped": 0, "failure": 0
  }
}
```

---

## `extemconf:constraints:unset`

Removes a single entry from `constraints.{depends|suggests|conflicts}`.
The mutated DTO is validated through `ExtEmConfValidator` before the
writer is invoked. The same write gate as `extemconf:set` applies.

### Usage

```
packwright extemconf:constraints:unset <type> <extension-key> <paths...> \
    [-t <version>] [--strict] [--dry-run] [--json]
```

### Arguments

Same shape as `extemconf:constraints:set`, minus `--constraint`.

### Idempotent

If the extension key is not present under `<type>`, the file is reported
as `unchanged` (no error, no write). The command does **not** look in
the other two sub-keys — to move a key from `suggests` to `depends`,
unset it under `suggests` first and then `set` it under `depends`.

### Exit codes

- `0` — every file produced `SUCCESS`/`WARNING`, was unchanged, or was
  a `--dry-run` preview.
- `1` — at least one file in `FAILURE` state (read or validation).
- `2` — usage error (invalid type, invalid extension key, no files
  matched, missing literal path, invalid `--typo3-version`).

### JSON shape

```json
{
  "command": "extemconf:constraints:unset",
  "type": "depends",
  "extensionKey": "extb",
  "typo3Version": 13,
  "strict": false,
  "dryRun": false,
  "files": [
    {
      "path": "/abs/path/ext_emconf.php",
      "state": "SUCCESS",
      "action": "written",
      "issues": [],
      "generated": "<?php ..."
    }
  ],
  "summary": {
    "total": 1, "written": 1, "dryRun": 0, "unchanged": 0, "skipped": 0, "failure": 0
  }
}
```

---

## Autoload subcommands

Eight dedicated commands manage the `autoload` and `autoload-dev`
blocks. They mirror the `constraints:*` pattern: per-target
sub-namespaces (`autoload:*`, `autoload-dev:*`) and per-type
sub-namespaces (`psr4:*`, `classmap:*`).

| Command                                   | Purpose                                                      |
|-------------------------------------------|--------------------------------------------------------------|
| `extemconf:autoload:psr4:set`             | Add or update a PSR-4 namespace mapping under `autoload`.    |
| `extemconf:autoload:psr4:unset`           | Remove a PSR-4 namespace mapping under `autoload`.           |
| `extemconf:autoload:classmap:set`         | Append a classmap path under `autoload`.                     |
| `extemconf:autoload:classmap:unset`       | Remove a classmap path under `autoload`.                     |
| `extemconf:autoload-dev:psr4:set`         | Add or update a PSR-4 namespace mapping under `autoload-dev`.|
| `extemconf:autoload-dev:psr4:unset`       | Remove a PSR-4 namespace mapping under `autoload-dev`.       |
| `extemconf:autoload-dev:classmap:set`     | Append a classmap path under `autoload-dev`.                 |
| `extemconf:autoload-dev:classmap:unset`   | Remove a classmap path under `autoload-dev`.                 |

Every command:

- Reads each input file via `ExtEmConfReader`, applies the change in
  memory, runs `ExtEmConfValidator`, and writes back through
  `ExtEmConfWriter`. The same write gate as `extemconf:set` applies
  (`FAILURE` blocks; `--strict` additionally blocks `WARNING`).
- Accepts the shared options `--strict`, `--dry-run`, `--json`,
  `-t/--typo3-version`. Behavior matches `extemconf:constraints:*`.
- Reports per-file outcome via the shared `Action` vocabulary
  (`written`, `dry-run`, `unchanged`, `skipped`).

### `psr4:set` usage

```
packwright extemconf:autoload:psr4:set     <namespace> <path> <paths...> [options]
packwright extemconf:autoload-dev:psr4:set <namespace> <path> <paths...> [options]
```

- `<namespace>` — PSR-4 namespace prefix; must end with `\`. Example:
  `Vendor\\Ext\\` (shell-quote to keep the trailing backslash).
- `<path>` — directory the namespace prefix maps to (relative to the
  extension root), e.g. `Classes/`. Empty paths exit
  `Command::INVALID`.

If the same namespace already exists under the target, its path is
**updated**. If the resulting file is byte-identical to the source,
the action reports `unchanged`.

### `psr4:unset` usage

```
packwright extemconf:autoload:psr4:unset     <namespace> <paths...> [options]
packwright extemconf:autoload-dev:psr4:unset <namespace> <paths...> [options]
```

Removes the named PSR-4 entry from the chosen target. If the
namespace is absent, the file is reported as `unchanged` and left
untouched.

### `classmap:set` usage

```
packwright extemconf:autoload:classmap:set     <path> <paths...> [options]
packwright extemconf:autoload-dev:classmap:set <path> <paths...> [options]
```

The classmap path is the entry's identifier — appending the same
path twice is a no-op (`unchanged`). Empty paths exit
`Command::INVALID`.

### `classmap:unset` usage

```
packwright extemconf:autoload:classmap:unset     <path> <paths...> [options]
packwright extemconf:autoload-dev:classmap:unset <path> <paths...> [options]
```

Removes the named classmap path from the chosen target. Idempotent.

### Cross-target collision (set commands only)

When the namespace (psr4) or path (classmap) is already present in
the *other* target — `autoload-dev` while running an `autoload:*`
command, or `autoload` while running an `autoload-dev:*` command —
the command stops to ask the operator:

```
Namespace 'Vendor\Ext\' already exists in `autoload-dev` (path 'Tests/Helper/').
Move it to `autoload` with path 'Classes/'? [y/N]
```

- **`y`** → the entry is removed from the source target and the new
  value is written into the chosen target. Action: `written`.
- **`n`** (or empty / `N`) → no file change. Action: `skipped`,
  state: `FAILURE`, with a `path: autoload-dev.psr-4.<namespace>`
  issue (and the symmetric path for the dev → prod direction).

In **non-interactive mode** — `--no-interaction` set, no TTY, or
`--json` set (which forces non-interactive) — the prompt is skipped
and the file is reported as `FAILURE`/`skipped` with the same
collision issue. This keeps automated pipelines safe from
unattended moves.

`--strict` does **not** alter collision handling; collisions are
always errors regardless of the strict gate.

### Exit codes

- `0` — every file produced `SUCCESS`/`WARNING`, was `unchanged`,
  or was a `--dry-run` preview.
- `1` — at least one file in `FAILURE` (read failure, validation
  `FAILURE`, declined or non-interactive cross-target collision).
- `2` — usage error (invalid namespace, empty path, no files
  matched, missing literal path, invalid `--typo3-version`).

### JSON shape (set commands)

```json
{
  "command": "extemconf:autoload:psr4:set",
  "target": "autoload",
  "namespace": "Vendor\\Ext\\",
  "path": "Classes/",
  "typo3Version": 13,
  "strict": false,
  "dryRun": false,
  "files": [
    {
      "path": "/abs/path/ext_emconf.php",
      "state": "SUCCESS",
      "action": "written",
      "issues": [],
      "generated": "<?php ..."
    }
  ],
  "summary": {
    "total": 1, "written": 1, "dryRun": 0, "unchanged": 0, "skipped": 0, "failure": 0
  }
}
```

`classmap:*` commands replace `"namespace"`/`"path"` with a single
`"classmapPath"` field; the `"target"` field is `"autoload"` or
`"autoload-dev"` depending on the command tree. Unset commands
drop the value-side field (`path`, `classmapPath`-as-value) since
the identifier alone uniquely names the entry to remove.

---

## Examples

### Validate every fixture under a directory

```
packwright extemconf:validate packages/
```

### Validate against TYPO3 v13 and emit JSON

```
packwright extemconf:validate packages/*/ext_emconf.php -t 13 --json | jq .summary
```

### Validate every `ext_emconf.php` at any depth via recursive glob

```
packwright extemconf:validate 'packages/**/ext_emconf.php' -t 13 --json | jq .summary
```

### Preview normalization without writing

```
packwright extemconf:normalize packages/my_ext/ext_emconf.php -t 12 --dry-run
```

### Normalize in place

```
packwright extemconf:normalize packages/my_ext/ext_emconf.php -t 12
```

### Pipe normalized source into another tool

```
packwright extemconf:normalize packages/my_ext/ext_emconf.php -t 13 --dry-run --json \
  | jq -r '.files[0].generated' \
  | tee /tmp/normalized.php
```

### Add a PSR-4 mapping under `autoload`

```
packwright extemconf:autoload:psr4:set "Vendor\\Ext\\" Classes/ \
    packages/my_ext/ext_emconf.php
```

### Move a namespace from `autoload-dev` to `autoload` (non-interactive fails)

```
# Refuses with a FAILURE/skipped issue; pipeline-safe.
packwright extemconf:autoload:psr4:set "Vendor\\Ext\\Tests\\" Classes/Tests/ \
    --no-interaction packages/my_ext/ext_emconf.php
```

### Move it interactively (operator is asked to confirm)

```
packwright extemconf:autoload:psr4:set "Vendor\\Ext\\Tests\\" Classes/Tests/ \
    packages/my_ext/ext_emconf.php
# → prompt: Move it to `autoload` with path 'Classes/Tests/'? [y/N]
```

### Append a classmap path under `autoload-dev`

```
packwright extemconf:autoload-dev:classmap:set Tests/Fixtures/Stub/ \
    packages/my_ext/ext_emconf.php
```

### Remove a PSR-4 mapping from `autoload-dev`

```
packwright extemconf:autoload-dev:psr4:unset "Vendor\\Ext\\Tests\\" \
    packages/my_ext/ext_emconf.php
```

---

## Implementation notes

- All commands depend on `Packwright\ExtEmConf\Service\PathResolver` to
  resolve their arguments. The resolver returns a sorted, deduplicated
  list of absolute paths. Single-`*` patterns are expanded via native
  `glob()` with `GLOB_BRACE`; patterns containing a `**` path segment
  are walked with `Symfony\Component\Finder\Finder` and matched by a
  regex compiled from the pattern (`**` → zero or more segments,
  `*` → one segment, `?`, `[...]`, and `{a,b}` keep their familiar
  meanings).
- Read failures short-circuit per file: validate reports the read
  issues with `state=FAILURE`; normalize reports `action=skipped` and
  leaves the file alone.
- `--typo3-version` is parsed via `Typo3Version::tryFrom()`; values outside
  the enum produce `Command::INVALID` (exit `2`).
- The display label `TYPO3 vN` comes from `Typo3Version::label()`.
