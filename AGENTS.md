# AGENTS.md

This file is read by any KI agents at the start of every session. Follow all
rules here exactly — they override default behaviour.

## Project context

Load these documents at session start. They are imported below; agents that do
not resolve `@` imports must read the files directly before starting work.

- @CONTRIBUTING.md
- @docs/ext-emconf-architecture.md
- @docs/ext-emconf-commands.md
- @docs/openspec-workflow.md

### Where to look up what

| Topic | Section |
|---|---|
| Coding guidelines, enum naming | [CONTRIBUTING: Coding Guidelines](CONTRIBUTING.md#coding-guidelines) |
| PHPStan level and baseline handling | [CONTRIBUTING: Static Analysis](CONTRIBUTING.md#static-analysis) |
| Test suites and commands | [CONTRIBUTING: Testing](CONTRIBUTING.md#testing) |
| Container dump and PHAR build | [CONTRIBUTING: Build](CONTRIBUTING.md#build) |
| Commit message format | [CONTRIBUTING: Commit Messages](CONTRIBUTING.md#commit-messages) |
| Where a class belongs | [Architecture: Namespace Layout](docs/ext-emconf-architecture.md#namespace-layout) |
| DTO design rules | [Architecture: Design Principles](docs/ext-emconf-architecture.md#design-principles) |
| Reader, validator, normalizer, writer | [Architecture: Service Contracts](docs/ext-emconf-architecture.md#service-contracts) |
| Result objects, issues, severities | [Architecture: Result Infrastructure](docs/ext-emconf-architecture.md#result-infrastructure) |
| `array` properties, Collection shapes | [Architecture: Collections and DTOs](docs/ext-emconf-architecture.md#collections-and-dtos) |
| Unit/functional scope, fixtures | [Architecture: Testing Strategy](docs/ext-emconf-architecture.md#testing-strategy) |
| Display paths, subcommand tree | [Architecture: Console output](docs/ext-emconf-architecture.md#console-output) |
| Path resolving, read failures, version option | [Commands: Implementation notes](docs/ext-emconf-commands.md#implementation-notes) |
| Mutating command write gate | [Commands: Validation gate](docs/ext-emconf-commands.md#validation-gate) |
| Autoload subcommand contract | [Commands: Autoload subcommands](docs/ext-emconf-commands.md#autoload-subcommands) |
| CLI usage examples | [Commands: Examples](docs/ext-emconf-commands.md#examples) |
| Planning a change, when to use OpenSpec | [OpenSpec: When to use it](docs/openspec-workflow.md#when-to-use-it) |
| Propose, apply, and archive steps | [OpenSpec: The change lifecycle](docs/openspec-workflow.md#the-change-lifecycle) |
| Agent rules for OpenSpec changes | [OpenSpec: Rules for coding agents](docs/openspec-workflow.md#rules-for-coding-agents) |

## Local gates

Run all gates, in this order, before committing a change to PHP code. CI runs
the same checks, and a change is not finished while one of them fails.

| Gate | Command | Scope |
|---|---|---|
| CGL | `composer cs:fix` | Applies coding guidelines; CI runs `composer cs:check` |
| PHPStan | `composer php:analyze` | Level 8 over `src/` and `tests/` |
| Unit tests | `composer test:unit` | `tests/Unit` |
| Functional tests | `composer test:functional` | `tests/Functional` |

- `composer test` runs both test suites in one go.
- Fix PHPStan findings in the code. Regenerate `phpstan-baseline.neon` with
  `composer php:analyze:generate-baseline` only for intentional deviations, and
  commit it together with the code change.
- CI runs the tests on PHP 8.2 and 8.5. Do not use language features or APIs
  newer than PHP 8.2.

## Implementation

Check the sections linked under [Where to look up what](#where-to-look-up-what)
before changing code, and follow what they define rather than inferring it from
nearby code. In particular:

- **DTOs** are `final readonly` classes. Do not repeat `readonly` on their
  promoted properties. See
  [Immutability](docs/ext-emconf-architecture.md#immutability).
- **No plain `array` properties** on classes that are not a Collection or
  Container. Use a typed DTO or a Collection. See
  [Collections and DTOs](docs/ext-emconf-architecture.md#collections-and-dtos).
- **Validation stays in `ExtEmConfValidator`.** DTOs represent what was present
  in the file, not what a valid file must contain. See
  [Nullable-by-default for DTO fields](docs/ext-emconf-architecture.md#nullable-by-default-for-dto-fields).
- **`--json` output is a single compact line**, and command tests assert this.
  The pretty-printed shapes in the command documentation are for readability
  only. See [ExtEmConf Console Commands](docs/ext-emconf-commands.md#extemconf-console-commands).
- **Keep the documentation in step with the code.** Update
  [`docs/ext-emconf-architecture.md`](docs/ext-emconf-architecture.md) when a
  class in the `ExtEmConf` subsystem is added, removed, or changes its contract.
  Update [`docs/ext-emconf-commands.md`](docs/ext-emconf-commands.md) when
  command arguments, options, exit codes, or JSON shapes change.

## OpenSpec

Larger changes are planned with OpenSpec. Follow
[Rules for coding agents](docs/openspec-workflow.md#rules-for-coding-agents)
whenever you work on an OpenSpec change. Above all, planning workflows never edit
project code, and implementation starts only on an explicit request.

## Test conventions

1. **No `setUp()` or class properties for subjects.** Instantiate services,
   DTOs, and collaborators directly inside each test method. Never create them
   in `setUp()` and never store them as class properties.

2. **No helper methods in tests.** Do not add private or protected helper
   methods such as `fixtureFile()`, `validDto()`, `baseDto()`, or `minimalDto()`.
   Inline whatever the helper would return directly into each test method, even
   if it means repeating several lines across tests.

3. **Named arguments follow constructor definition order.** When constructing
   objects with named arguments, the arguments must appear in the same order as
   their declaration in the constructor. Skipping optional parameters is fine;
   reordering them is not.

## PHPUnit version

This project targets PHP ^8.2. PHPUnit 12+ requires PHP >=8.3. Always use
`"phpunit/phpunit": "^11.5"` — never suggest ^12.x.

## PHAR build

`box.json` must include a finder entry for `vendor/symfony/console/Resources`:

```json
{ "in": "vendor/symfony/console/Resources" }
```

`DumpCompletionCommand` calls `DirectoryIterator` on that directory at startup.
Without it, the PHAR fails with "Failed to open directory: operation failed".

`composer build` produces two byte-identical PHARs in one invocation:
`build/packwright` (from `box compile`) and `build/pkw` (copied
afterward via `cp` + `chmod 0755`). Box only emits a single PHAR per
`box.json`, so the alias is wired in as post-compile script steps in
`composer.json` rather than a second box config.

## Overrides

Read @AGENTS.local.md if it exists to allow local overrides of the above rules.
Ensure to keep the Overrides section always as last instruction in the AGENTS.md
file.
