# OpenSpec Workflow

This project plans larger changes with [OpenSpec](https://openspec.dev), so
that the people and coding agents working on a change agree on what it should do
before code is written. This guide is for both audiences:

- **Developers:** start with [Getting started](#getting-started), then read
  [The change lifecycle](#the-change-lifecycle).
- **Coding agents:** additionally follow
  [Rules for coding agents](#rules-for-coding-agents).

The plan is a set of Markdown files that are committed and reviewed like code.
You can drive it through a coding agent or write it by hand with the CLI.

---

## When to use it

| Use OpenSpec for | Skip it for |
|---|---|
| New commands, options, or subsystems | Bug fixes that restore specified behaviour |
| Changes to command output, exit codes, or JSON shapes | Refactorings without a behaviour change |
| Breaking changes | Dependency updates and CI maintenance |
| Work with open design questions | Documentation fixes |

If you are unsure, use [`explore`](#the-workflows) to think it through first.

## Getting started

Agents run the `openspec` CLI, so every contributor who uses the workflows needs
it installed. The standalone binary does not need Node.js:

```bash
curl -fsSL https://openspec.dev/cli/install.sh | sh
openspec --version
```

See the [installation guide](https://openspec.dev/docs/installation) for other
platforms and package managers. The project setup is already committed, so do
**not** run `openspec init` after cloning the repository.

OpenSpec collects anonymous usage statistics by default. To opt out, set
`OPENSPEC_TELEMETRY=0` or run `openspec config set telemetry.enabled false`.

## Project layout

```
openspec/
├── config.yaml              # Schema, plus optional project context and rules
├── specs/                   # Source of truth: what the system does today
│   └── <capability>/spec.md
└── changes/
    ├── <change-name>/       # Active change: what should be different
    │   ├── .openspec.yaml   # Change metadata
    │   ├── proposal.md      # Why, and what changes
    │   ├── specs/           # Spec deltas, not full specs
    │   │   └── <capability>/spec.md
    │   ├── design.md        # How, only when needed
    │   └── tasks.md         # Implementation checklist
    └── archive/
        └── YYYY-MM-DD-<change-name>/   # Completed changes
```

The generated skills and commands live in `.claude/`, `.agents/` (Codex),
`.cursor/`, and `.github/prompts/` plus `.github/skills/`. Do not edit them by
hand; see [Maintaining the setup](#maintaining-the-setup).

`openspec/specs/` and `docs/` do not replace each other. Specs state the
required behaviour as testable requirements. The
[architecture](ext-emconf-architecture.md) and
[command](ext-emconf-commands.md) references explain the design and how to use
the tool. A change can touch both.

## The workflows

| Workflow | Claude Code | Cursor, GitHub Copilot | Codex | Purpose |
|---|---|---|---|---|
| Explore | `/opsx:explore` | `/opsx-explore` | `$openspec-explore` | Think through a problem without implementing it |
| Propose | `/opsx:propose` | `/opsx-propose` | `$openspec-propose` | Create a change and all of its planning artifacts |
| Apply | `/opsx:apply` | `/opsx-apply` | `$openspec-apply-change` | Implement the tasks and tick them off |
| Update | `/opsx:update` | `/opsx-update` | `$openspec-update-change` | Revise planning artifacts, never code |
| Sync | `/opsx:sync` | `/opsx-sync` | `$openspec-sync-specs` | Merge spec deltas into the main specs without archiving |
| Archive | `/opsx:archive` | `/opsx-archive` | `$openspec-archive-change` | Finish a change: sync its specs and move it to the archive |

Restart your editor or agent after pulling new or updated workflow files.

## The change lifecycle

```
explore (optional) → propose → review → apply ⇄ update → archive → pull request
```

The examples use Claude Code spelling and a hypothetical `--format` option.

### 1. Explore (optional)

Use this when the problem or approach is still open. It reads code and discusses
options, but does not implement anything.

```
/opsx:explore How should extemconf:get support other output formats?
```

### 2. Propose

Create a working branch, then describe the change. The agent derives a kebab-case
name and writes the artifacts, then **stops without implementing anything**.

```
/opsx:propose Add a --format option to extemconf:get
```

| Artifact | Answers | Notes |
|---|---|---|
| `proposal.md` | Why, and what changes? | Names the new and modified capabilities |
| `specs/<capability>/spec.md` | What must the system do? | Deltas with testable scenarios |
| `design.md` | How will it be built? | Only for cross-cutting, risky, or ambiguous changes |
| `tasks.md` | What are the steps? | Checkboxes that track implementation |

Specs are deltas, grouped by what they do to the main spec:

```markdown
## ADDED Requirements

### Requirement: Output format selection
The `extemconf:get` command SHALL accept a `--format` option.

#### Scenario: Unknown format
- **WHEN** the operator passes an unsupported format
- **THEN** the command exits with code `2`
```

The other sections are `## MODIFIED Requirements`, `## REMOVED Requirements`,
and `## RENAMED Requirements`. A `MODIFIED` block carries the complete updated
requirement. A delta for a new capability also starts with a `## Purpose` section
of at least 50 characters, which becomes the purpose of the main spec.

### 3. Review

Read the artifacts before any code exists. This is the cheapest point to correct
the scope. Edit the files directly or ask for changes with `/opsx:update`.

```bash
openspec validate add-format-option --strict
```

### 4. Apply

Starting implementation is a separate, explicit step.

```
/opsx:apply add-format-option
```

The agent works through `tasks.md` and ticks off each task once it is fully
implemented. It pauses on errors, ambiguity, or work beyond the spec. All project
rules still apply, in particular the [local gates](../AGENTS.md#local-gates) and
[test conventions](../AGENTS.md#test-conventions).

### 5. Update when reality differs

If implementation shows that the plan is wrong, fix the plan rather than letting
the code drift from it. Run `/opsx:update`, then `/opsx:apply` to carry the
revision into the code. If the *intent* of the change has changed, not just its
details, start a new change instead.

### 6. Archive

When all tasks are complete and the local gates pass, archive the change:

```
/opsx:archive add-format-option
```

This merges the deltas into `openspec/specs/` and moves the change to
`openspec/changes/archive/YYYY-MM-DD-add-format-option/`. Then check the main
specs:

```bash
openspec validate --all --strict
```

### 7. Open the pull request

Keep the artifacts, the implementation, and the archive step on one branch and
in one pull request, with the archive step as the last commit. This lets
reviewers compare the plan with the code, and leaves no stale active change on
`main`. To get feedback on the plan first, open a draft pull request after step
3. All commits follow the [commit message rules](../CONTRIBUTING.md#commit-messages).

## Working without an agent

Every artifact is a plain Markdown file, so the workflow also works by hand:

```bash
openspec new change add-format-option                 # Scaffold the change
openspec status --change add-format-option            # Show artifact progress
openspec instructions proposal --change add-format-option  # Template and guidance
openspec validate add-format-option --strict          # Check the artifacts
openspec archive add-format-option                    # Update main specs, archive
```

Use `openspec archive --skip-specs` for a change that does not affect any spec.
`openspec list` shows active changes, `openspec list --specs` shows capabilities,
`openspec show <name>` prints an item, and `openspec view` opens a dashboard.
Always create changes with `openspec new change`, because it writes the required
metadata.

## Rules for coding agents

The generated workflow files define the detailed steps. In addition:

1. **Keep planning and implementation apart.** `explore`, `propose`, and `update`
   never edit project code. Start `apply` only on an explicit request, even if
   the original request asked to build something.
2. **Use OpenSpec only when it is requested or set out in this guide.** Do not
   turn a small fix into a change on your own initiative; see
   [When to use it](#when-to-use-it).
3. **Read the plan from disk.** Artifacts may have been edited since you last saw
   them. Use the paths reported by `openspec status --json` and
   `openspec instructions`.
4. **Never silently narrow the scope.** Tick a task only when its specified
   behaviour is fully implemented and tested. Surface additional scope and pause.
5. **Do not edit `openspec/specs/` during a change.** Main specs change through
   sync or archive. The exception is replacing a placeholder purpose; see
   [Troubleshooting](#troubleshooting).
6. **Run the [local gates](../AGENTS.md#local-gates) before archiving.** Keep the
   `docs/` references in step with the code as `AGENTS.md` requires.
7. **Keep archive confirmations with the user.** Do not bypass a warning about
   incomplete tasks or a blocked sync.

`/opsx:update` revises the artifacts of a change. `openspec update` is a different
command that regenerates the workflow files.

## Project configuration

`openspec/config.yaml` selects the `spec-driven` schema. It can also hold `context`
and per-artifact `rules`, which guide planning but are not copied into artifacts.
Add only constraints that an agent cannot infer from the code, and keep general
guidance in this repository's documentation. See the
[project configuration guide](https://openspec.dev/docs/project-config).

## Maintaining the setup

Update the CLI as `openspec version --check` advises, then regenerate and commit
the workflow files in their own commit:

```bash
openspec update --dry-run   # Preview the changes
openspec update
```

To support another tool, run `openspec init --tools <ids>` with the complete list
of tools, currently `claude,codex,cursor,github-copilot`. Pass
`--no-copilot-cloud` because the Copilot cloud agent setup is disabled.

`.gitignore` tracks only the OpenSpec files below `.claude/` and `.agents/`, so a
new tool that writes to an ignored path needs its own exception. Check the result
with `git status` before committing.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `openspec: command not found` | The install folder is not on `PATH`. The standalone installer defaults to `~/.local/bin`. |
| `"root": null` from `openspec list --json` | You are outside the repository. Change into it; do not run `openspec init`. |
| New workflows do not appear in your tool | Restart your editor or agent. |
| `Purpose section is still a placeholder` from `openspec validate --strict` | The delta of a new capability had no `## Purpose`. Edit the main spec directly and write the purpose there. |
| An artifact shows as blocked in `openspec status` | A dependency does not exist yet. Create the one listed under `blocked by`. |

## Further reading

- [Quickstart](https://openspec.dev/docs/quickstart)
- [Skills reference](https://openspec.dev/docs/skills)
- [CLI reference](https://openspec.dev/docs/cli)
- [`spec-driven` schema](https://openspec.dev/docs/schemas/spec-driven)
- [Profiles](https://openspec.dev/docs/profiles), for the additional workflows
  this project does not install
