---
work_package_id: WP23
title: 'Hard-cut: remove legacy + drop dep'
dependencies:
- WP06
- WP07
- WP08
- WP09
- WP10
- WP11
- WP12
- WP13
- WP14
- WP15
- WP16
- WP17
- WP18
- WP19
- WP20
- WP21
- WP22
requirement_refs:
- FR-006
- FR-009
- FR-011
- FR-012
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T102
- T103
- T104
- T105
- T106
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "1015982"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: packages/cli/
execution_mode: code_change
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- packages/foundation/src/ServiceProvider/Capability/HasCommandsInterface.php
- packages/cli/src/WaaseyaaApplication.php
- packages/cli/src/CliCommandRegistry.php
- packages/cli/composer.json
- composer.lock
tags: []
---

# WP23 — Hard-cut: remove legacy + drop dep

## Branch Strategy

`main` → `main` per lanes.json. **Depends on every port WP** (06–22) being merged first.

## Objective

Make the cut. Remove the legacy capability interface, the Symfony Application subclass, the legacy registry, the `Compat/` dual-boot adapter, and the runtime composer require. After this WP, `composer why symfony/console` shows no first-party (waaseyaa/*) runtime chain.

## Subtasks

### T102 — Delete `HasCommandsInterface.php`

`rm packages/foundation/src/ServiceProvider/Capability/HasCommandsInterface.php`. Verify no remaining usage anywhere:
```bash
grep -rn 'HasCommandsInterface' packages/ bin/ docs/ --include='*.php' --include='*.md'
# expected: empty
```

If any reference remains, it's a port WP that didn't fully migrate — file a bug, do not silently leave the interface in place.

### T103 — Delete `WaaseyaaApplication.php` and `CliCommandRegistry.php`

```bash
rm packages/cli/src/WaaseyaaApplication.php packages/cli/src/CliCommandRegistry.php
```

These are the Symfony Application subclass and the Symfony-Application-backed registry. Both are obsolete after WP04+WP05 land.

### T104 — Remove dual-boot adapter and rewire `bin/waaseyaa`

```bash
rm -rf packages/cli/src/Compat/
```

Edit `bin/waaseyaa` to remove the legacy-providers branch added in WP05/T023. After the edit, `bin/waaseyaa` only invokes `LegacySymfonyCommandRegistrar` zero times — the call is gone.

Verify:
```bash
grep -rn 'LegacySymfonyCommandRegistrar\|LegacySymfonyCommandAdapter' packages/ bin/
# expected: empty
```

### T105 — Drop `symfony/console` from `packages/cli/composer.json`

Open `packages/cli/composer.json`. In the `require` block, delete the `"symfony/console": "..."` line. Run `composer update --lock` so `composer.lock` reflects the change.

Run `bin/check-composer-policy` to confirm:
- No `@dev` (CP002 still satisfied).
- `self.version` only in root (CP006 still satisfied).
- No wildcard internal constraints (CP003 still satisfied).
- `config.sort-packages: true` still set (CP001).

### T106 — Verify no first-party runtime chain depends on `symfony/console`

Run from repo root:

```bash
composer why symfony/console
```

Acceptable output: only `--dev` or transitive non-waaseyaa chains. Reject if any chain like `waaseyaa/cli -> symfony/console` appears at runtime.

If a chain appears via `waaseyaa/northcloud`, that's a WP22 bug (the provider should not have re-introduced the dep). Re-open WP22 and fix.

## Definition of Done

- [ ] `git ls-files packages/foundation/src/ServiceProvider/Capability/HasCommandsInterface.php` returns nothing.
- [ ] `git ls-files packages/cli/src/{WaaseyaaApplication.php,CliCommandRegistry.php}` returns nothing.
- [ ] `git ls-files packages/cli/src/Compat/` returns nothing.
- [ ] `grep -rn 'Symfony.Component.Console' packages/cli/src bin/waaseyaa` returns no first-party hits.
- [ ] `grep -rn 'HasCommandsInterface' packages/ bin/ docs/ --include='*.php' --include='*.md'` returns nothing.
- [ ] `composer why symfony/console` shows no `waaseyaa/*` runtime chain.
- [ ] `composer cs-check`, `composer phpstan`, `bin/check-package-layers`, `bin/check-composer-policy` clean.
- [ ] `vendor/bin/phpunit` full suite green.
- [ ] `composer.lock` regenerated and committed.

## Risks

- **Hidden caller of `HasCommandsInterface`.** Mitigation: T102's grep gate catches it; do not skip.
- **`symfony/console` re-entering transitively.** Mitigation: T106's `composer why` gate.
- **Application breakage.** Mitigation: full suite must pass; any failure is a port WP regression — fix the port WP, do not patch around in WP23.

## Reviewer guidance

- Read the diff carefully — this WP only DELETES code (and edits one composer.json + one bin script).
- Confirm DoD greps are clean.
- Confirm `composer.lock` diff shows `symfony/console` removed (or moved to dev-only chain).

## Implementation command

```bash
spec-kitty agent action implement WP23 --agent <name>
```

## Activity Log

- 2026-05-08T17:06:21Z – claude:sonnet:implementer:implementer – shell_pid=1007657 – Started implementation via action command
- 2026-05-08T17:34:15Z – claude:sonnet:implementer:implementer – shell_pid=1007657 – Hard-cut complete: deleted Compat/, WaaseyaaApplication, CliCommandRegistry, HasCommandsInterface, DualBootTest; removed symfony/console from all waaseyaa/* runtime requires; wired native CliKernel end-to-end via KernelHandlerContainer; all 7496 tests pass, cs/stan/layers/policy green.
- 2026-05-08T17:35:03Z – claude:opus-4-7:reviewer:reviewer – shell_pid=1015982 – Started review via action command
- 2026-05-08T17:38:13Z – claude:opus-4-7:reviewer:reviewer – shell_pid=1015982 – Hard-cut complete: symfony/console gone from runtime, all 71 mission snapshot tests byte-parity green, 7496/7496 phpunit pass, phpstan/cs/layers/policy gates green. Only dev-only chain via friendsofphp/php-cs-fixer remains (allowed). 4 baseline-fixture diffs (list/help/completion/command) are Symfony framework built-ins, not shipped commands; native CLI provides equivalent listing via no-arg invocation. Fixture diff is purely additive (queue:*, telescope:validate) plus benign stderr noise-blanking.
- 2026-05-08T18:06:48Z – claude:opus-4-7:reviewer:reviewer – shell_pid=1015982 – Done override: Mission merged to main (cc36dfcd2)
