---
work_package_id: WP02
title: Native Runtime Core
dependencies:
- WP01
requirement_refs:
- FR-002
- FR-003
- FR-004
- FR-005
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T006
- T007
- T008
- T009
- T010
- T011
agent: "claude:sonnet:implementer:implementer"
shell_pid: "875782"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/src/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/cli/src/ArgumentMode.php
- packages/cli/src/OptionMode.php
- packages/cli/src/ArgumentDefinition.php
- packages/cli/src/OptionDefinition.php
- packages/cli/src/CommandDefinition.php
- packages/cli/src/CommandRegistry.php
- packages/cli/src/Parser/**
- packages/cli/src/Exception/**
- packages/cli/tests/Unit/Kernel/ArgumentDefinitionTest.php
- packages/cli/tests/Unit/Kernel/OptionDefinitionTest.php
- packages/cli/tests/Unit/Kernel/CommandDefinitionTest.php
- packages/cli/tests/Unit/Kernel/CommandRegistryTest.php
- packages/cli/tests/Unit/Parser/**
tags: []
---

# WP02 — Native Runtime Core

## Branch Strategy

Planning/base branch: `main`. Final merge target: `main`. Worktree per `lanes.json`.

## Objective

Land the parser, type definitions, and registry that everything else builds on. After this WP the runtime types exist in source; no commands run on them yet (port WPs 06–22 do that). Existing Symfony commands continue to work unchanged.

## Context

- Data model: [`data-model.md`](../data-model.md).
- Contracts: [`contracts/command-definition.md`](../contracts/command-definition.md).
- Parser semantics: [`research.md`](../research.md) §R-02 (the supported subset).
- All new code MUST be `final class`, `readonly` where applicable, `declare(strict_types=1)`, no Symfony imports.

## Subtasks

### T006 — `ArgumentMode` and `OptionMode` enums

**Steps**:
1. Create `packages/cli/src/ArgumentMode.php` containing `enum ArgumentMode { case Required; case Optional; }`.
2. Create `packages/cli/src/OptionMode.php` containing `enum OptionMode { case None; case Required; case Optional; case Array_; case Negatable; }`.

**Files**: 2 new files, ~20 lines each.

**Validation**: `composer phpstan` passes; both enums declared `declare(strict_types=1)`.

### T007 — `ArgumentDefinition` + invariant tests

**Steps**:
1. Implement `ArgumentDefinition` per [`data-model.md`](../data-model.md). Constructor enforces:
   - `name` matches `/^[a-z][a-z0-9_]*$/` (throws `InvalidArgumentDefinitionException` otherwise).
   - If `mode === Required` and `isArray === false`, `default` MUST be `null` (throw if violated).
2. Write tests under `packages/cli/tests/Unit/Kernel/ArgumentDefinitionTest.php` covering:
   - Valid construction with each `ArgumentMode`.
   - Invalid name pattern throws.
   - `default` mismatch with `Required` throws.
   - Array argument default normalised to `[]` if null.

**Files**: `packages/cli/src/ArgumentDefinition.php` (~50 lines), test file (~120 lines).

### T008 — `OptionDefinition` + invariant tests

**Steps**:
1. Implement per [`data-model.md`](../data-model.md). Constructor enforces:
   - `name` matches `/^[a-z][a-z0-9-]*$/`, NOT in reserved list `['help', 'verbose', 'quiet', 'no-interaction', 'version']`.
   - `shortcut`, when not null, is exactly one ASCII letter, NOT in `['h', 'v', 'q']`.
   - `Negatable` mode: `name` MUST NOT start with `no-`.
   - `Array_` mode: `default` defaults to `[]` if null.
   - `None` / `Negatable` modes: `default` normalised to `false` / `null` respectively.
2. Tests: each invariant has a passing case and a throwing case.

**Files**: `packages/cli/src/OptionDefinition.php` (~80 lines), test file (~180 lines).

### T009 — `CommandDefinition` + handler normalisation

**Steps**:
1. Implement per [`data-model.md`](../data-model.md) and [`contracts/command-definition.md`](../contracts/command-definition.md).
2. Constructor accepts `\Closure | array $handler`. If array: validate it's `[ClassFqn, methodName]`, store as deferred resolution lambda that the `CliKernel` will resolve through the container at dispatch time. The lambda calls `$container->get($fqn)` then `$instance->{$method}($cliIo)`.
3. Enforce uniqueness of argument names + option long-names + option shortcuts within the command.
4. Enforce: at most one array-mode argument; if present, last; required argument may not follow optional argument.
5. Tests: invariant violations all throw `InvalidCommandDefinitionException`; happy-path normalisation produces a closure of arity 1 returning int.

**Files**: `packages/cli/src/CommandDefinition.php` (~150 lines), `packages/cli/src/Exception/InvalidCommandDefinitionException.php` (~10 lines), test file (~200 lines).

**Note**: At this WP the kernel doesn't exist yet, so the closure normalisation can be lazy — it captures the FQN+method pair and the actual `$container` is injected later by the kernel during dispatch. WP04 wires the container.

### T010 — `CommandRegistry`

**Steps**:
1. Implement per [`contracts/command-definition.md`](../contracts/command-definition.md).
2. `register(CommandDefinition)` throws `DuplicateCommandException` on existing name.
3. `get(string $name): ?CommandDefinition` returns null on miss (no fuzzy).
4. `all(): array` returns commands sorted by name ASCII-lexically.
5. `names(): array` returns sorted list of names.
6. Tests cover all three states (empty, populated, duplicate).

**Files**: `packages/cli/src/CommandRegistry.php` (~80 lines), `packages/cli/src/Exception/DuplicateCommandException.php` (~10 lines), test file (~120 lines).

### T011 — `ArgvParser` + `ParsedInput` + `ParseError` + edge-case test matrix

**Steps**:
1. Implement `ParsedInput` (readonly record per [`data-model.md`](../data-model.md)).
2. Implement `ParseError` and `ParseErrorKind` enum per [`data-model.md`](../data-model.md).
3. Implement `ArgvParser::parse(array $argv, CommandDefinition $cmd): ParsedInput`. The supported subset is exhaustively defined in [`research.md`](../research.md) §R-02.
4. Throw `ParseException` (wrapping a `ParseError`) on any deviation. Don't return errors as values — the kernel catches at the top.
5. Tests in `packages/cli/tests/Unit/Parser/ArgvParserTest.php` covering EVERY supported case AND every documented unsupported case (must throw):
   - Required positional present / missing (missing → throws `MissingRequiredArgument`).
   - Optional positional with and without default.
   - Array-mode positional collects remaining tokens.
   - `--flag` (NONE).
   - `--key=value`, `--key value` (REQUIRED).
   - `--key` bare (OPTIONAL → null), `--key=value` (OPTIONAL → 'value').
   - `--tag=a --tag=b` (ARRAY → `['a', 'b']`).
   - `--no-foo` toggling NEGATABLE `--foo`.
   - `-abc` stacked NONE flags (`-a -b -c`).
   - `-fbar` glued REQUIRED short → `ParseException` (UNSUPPORTED).
   - `--` end-of-options sentinel.
   - Unknown option → throws `UnknownOption`.
   - Unknown command name → not handled here (kernel-level lookup); ensure parser doesn't recurse into command dispatch.
   - Type coercion for option values that declare an int default: `--limit=abc` throws `TypeCoercion`; `--limit=5` returns int `5`.

**Files**: `packages/cli/src/Parser/ArgvParser.php` (~200 lines), `packages/cli/src/Parser/ParsedInput.php` (~30 lines), `packages/cli/src/Parser/ParseError.php` + enum (~40 lines), `packages/cli/src/Exception/ParseException.php` (~20 lines), test file (~400 lines covering ~30 cases).

## Definition of Done

- [ ] All files above exist; PSR-4 autoload resolves them as `Waaseyaa\Cli\…`.
- [ ] `composer cs-check` clean for new files.
- [ ] `composer phpstan` clean (level 5).
- [ ] `vendor/bin/phpunit packages/cli/tests/Unit/Kernel/ packages/cli/tests/Unit/Parser/` passes.
- [ ] `bin/check-package-layers` clean.
- [ ] No file in `packages/cli/src/` (other than legacy classes that already exist) imports `Symfony\Component\Console\…`.

## Risks

- **Parser missing a Symfony quirk.** Mitigation: snapshot tests in WP06–22 will catch divergent stdout per command; if any port WP fails, file an issue against this WP for parser augmentation.
- **`CommandDefinition` handler array shape.** Reviewer must verify the lazy normalisation does not require the container at construction time — the container is bound at dispatch.

## Reviewer guidance

- Read [`research.md`](../research.md) §R-02 first; ensure parser implements that subset and explicitly rejects the unsupported set.
- All public methods have return types; all properties are readonly.
- No `array` shapes used where a typed object is more appropriate.
- Test matrix is comprehensive (count test methods; if < 30 in `ArgvParserTest.php`, request additional cases).

## Implementation command

```bash
spec-kitty agent action implement WP02 --agent <name>
```

## Activity Log

- 2026-05-08T03:24:28Z – claude:sonnet:implementer:implementer – shell_pid=875782 – Started implementation via action command
