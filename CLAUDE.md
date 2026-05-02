# CLAUDE.md

This file is read by Claude Code at the start of every session. Follow all
rules here exactly — they override default behaviour.

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
