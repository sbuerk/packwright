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
packwright extemconf:validate <paths...> [-t <version>] [--json] [-v]
```

### Arguments

- `paths` — one or more file paths, directories, or glob patterns. Required.
  - A literal file path must exist.
  - A directory is walked recursively for `ext_emconf.php` files.
  - A pattern containing `*`, `?`, or `[` is resolved via `glob()` with
    `GLOB_BRACE`.
  - `**` is **not** supported by the resolver. Pass a directory or rely
    on shell expansion when recursive matching is needed.

### Options

- `-t`, `--typo3-version` — major version to validate against.
  Allowed values: `8`, `9`, `10`, `11`, `12`, `13`. When omitted, version-specific
  rules are skipped.
- `--json` — print a JSON document instead of a table; suppresses all other
  output so the result can be piped.
- `-v` — verbose mode lists every issue under the table
  (`severity  path  message`).

### Exit codes

- `0` — all files in `SUCCESS` or `WARNING` state.
- `1` — at least one file in `FAILURE` state.
- `2` — usage error (no files matched, missing literal path, invalid
  `--typo3-version`).

### Table layout

```
+-----------+----------+---------+--------+----------+
| File      | TYPO3    | State   | Errors | Warnings |
+-----------+----------+---------+--------+----------+
| /abs/...  | TYPO3 v13| SUCCESS | 0      | 0        |
+-----------+----------+---------+--------+----------+
```

The `TYPO3` column reads `any` when `--typo3-version` is omitted.

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
  (`severity  path  message`).

### Per-file action

The `Action` column / JSON `action` field is one of:

- `written` — file was modified on disk.
- `dry-run` — `--dry-run` was set; file was left untouched.
- `unchanged` — normalization produced no changes; file was left untouched.
- `skipped` — read or normalize step failed; file was left untouched and
  the run contributes to exit code `1`.

Writes use `file_put_contents()` with `LOCK_EX`. Files without changes are
never re-written.

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

## Examples

### Validate every fixture under a directory

```
packwright extemconf:validate packages/
```

### Validate against TYPO3 v13 and emit JSON

```
packwright extemconf:validate packages/*/ext_emconf.php -t 13 --json | jq .summary
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

---

## Implementation notes

- Both commands depend on `Packwright\ExtEmConf\Service\PathResolver` to
  resolve their arguments. The resolver returns a sorted, deduplicated
  list of absolute paths.
- Read failures short-circuit per file: validate reports the read
  issues with `state=FAILURE`; normalize reports `action=skipped` and
  leaves the file alone.
- `--typo3-version` is parsed via `Typo3Version::tryFrom()`; values outside
  the enum produce `Command::INVALID` (exit `2`).
- The display label `TYPO3 vN` comes from `Typo3Version::label()`.
