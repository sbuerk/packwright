# packwright

> The wright that crafts your package releases.

`packwright` is a PHP command-line tool for managing Composer package
releases. It is designed to cover:

- version updates across `composer.json` sub-keys and any other files
  that need to stay in sync,
- release publishing with pre-release, release, and post-release
  hooks,
- monorepository workflows where sub-packages are split out at
  release time,
- semver bump suggestions (patch / minor / major) that you can accept
  or override,
- manual and development releases, and
- TYPO3-specific `ext_emconf.php` files (optional, for projects that
  need it).

For everyday use the binary also installs as **`pkw`** — same tool,
fewer keystrokes (`P`ac`K` `W`right). Both names are interchangeable.

## About the name

A *wright* is an old English word for a craftsman or maker, preserved
today in compound nouns like *shipwright*, *playwright*, and
*wheelwright*. `packwright` is the wright that crafts your *pack*age
releases: it knows how to read, validate, and update the files that
describe a package, so a release goes out the way you intended.

The 3-character alias `pkw` is the initials of the full name and is
designed to stay out of the way of common Linux/PHP CLI binaries — it
does not collide with `pkg`, `pw`, `pwn`, or the PipeWire `pw-*`
family.

## Status

Early development. The following `ext_emconf.php` commands are
functional today; the broader Composer release workflow is on the
roadmap.

- `extemconf:validate` — validate one or more `ext_emconf.php` files.
- `extemconf:normalize` — normalize and rewrite files (drops
  deprecated keys, fills mandatory defaults).
- `extemconf:get` — read selected scalar fields (table or JSON).
- `extemconf:set` — set one or more scalar fields with up-front
  validation.
- `extemconf:constraints:set` — add or update an entry under
  `constraints.{depends|suggests|conflicts}` with cross-key collision
  detection. Pass `--update-only` to skip files where the key is not
  already present (idempotent batch updates).
- `extemconf:constraints:unset` — remove an entry from
  `constraints.{depends|suggests|conflicts}` (idempotent).

See [`docs/ext-emconf-commands.md`](docs/ext-emconf-commands.md) for
the full command reference, including JSON output shapes and exit
code semantics.

## Requirements

- PHP 8.2+

## Installation

### Standalone PHAR

Download a `packwright` (or `pkw`) PHAR build, make it executable,
and place it on your `PATH`:

```bash
chmod +x packwright
mv packwright /usr/local/bin/   # or wherever your local bin lives
```

Both binaries are shipped from the same build (byte-identical
contents); pick the name you prefer, or install both.

### Composer dependency

```bash
composer require --dev sbuerk/packwright
```

After install, both `vendor/bin/packwright` and `vendor/bin/pkw` are
available.

## Usage

List all available commands:

```bash
packwright list
# or, identically:
pkw list
```

Validate, normalize, inspect, or edit a TYPO3 extension's
`ext_emconf.php`:

```bash
packwright extemconf:validate path/to/ext_emconf.php
packwright extemconf:normalize path/to/ext_emconf.php
packwright extemconf:get path/to/ext_emconf.php --field=version
packwright extemconf:set path/to/ext_emconf.php --ext-version=2.0.0 --state=stable
packwright extemconf:constraints:set depends typo3 path/to/ext_emconf.php \
    --constraint='13.4.0-13.4.99'
packwright extemconf:constraints:unset depends php path/to/ext_emconf.php
```

Use `--fail-on-warning` with `extemconf:validate` to also fail on
`WARNING`-state files (useful in CI). All mutating commands accept
`--dry-run` and `--json`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, the test
suite, and build instructions.
