---
work_package_id: WP12
title: Translation configuration in skeleton config/waaseyaa.php
dependencies: []
requirement_refs:
- FR-037
- FR-041
- C-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T064
- T065
- T066
history: []
authoritative_surface: config/
execution_mode: code_change
owned_files:
- config/waaseyaa.php
- tests/Integration/Config/Translation*
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "612342"
---

# WP12 — Translation configuration in skeleton config/waaseyaa.php

## Objective

Add the `translation` config block to the skeleton `config/waaseyaa.php`, wiring env-var-driven defaults for `read_active_language` and `fallback_chain` extensibility.

## Context

- **Spec:** [`../spec.md`](../spec.md) FR-037, FR-041, C-004
- **Research:** [`../research.md`](../research.md) R9, R12

## Subtasks

### T064 — Add `translation` block to `config/waaseyaa.php`

**Steps:**

1. Open `config/waaseyaa.php`. Locate the top-level returned array.
2. Add:
   ```php
   'translation' => [
       /*
        * If true, EntityRepository::find() will return the active-language
        * translation when available (falling back to default langcode if not).
        * Default: false (always returns default-langcode entity).
        */
       'read_active_language' => env('WAASEYAA_TRANSLATION_READ_ACTIVE_LANGUAGE', false),

       /*
        * Customize the language fallback chain.
        *
        * Closure: fn(string $requested, EntityInterface $entity): array<string>
        *
        * Default chain (when null): [requested, entity-default, site-default, 'en'].
        * Maximum chain length: 8 (NFR-002).
        */
       'fallback_chain' => null,
   ],
   ```

**Files:** `config/waaseyaa.php` (modify, ~25 lines).

### T065 — Environment variable wiring

**Steps:**

1. Verify `env()` helper exists in the skeleton; if not, document that the config uses `getenv()`.
2. Add documentation in `.env.example`:
   ```
   # Translation: when true, EntityRepository::find() returns the active-language
   # translation if available (HTTP context only). Default: false.
   WAASEYAA_TRANSLATION_READ_ACTIVE_LANGUAGE=false
   ```

**Files:** `config/waaseyaa.php` + `.env.example` (modify, ~5 lines).

### T066 — Tests

**Steps:**

1. Create `tests/Integration/Config/TranslationConfigTest.php`:
   - Load the config; assert `translation.read_active_language` is false by default.
   - Set `WAASEYAA_TRANSLATION_READ_ACTIVE_LANGUAGE=true` env; load; assert true.
   - Assert `translation.fallback_chain` is null by default (resolver uses built-in chain).
2. Use the framework's existing config-loading test scaffolding.

**Files:** ~80 lines of tests.

## Definition of Done

- [ ] `config/waaseyaa.php` has the `translation` block.
- [ ] `.env.example` documents `WAASEYAA_TRANSLATION_READ_ACTIVE_LANGUAGE`.
- [ ] Configuration loading tests pass.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| `config/waaseyaa.php` is in the skeleton, not the framework — depends on how the project layout is set up. | Verify path; the skeleton drives config. Document if `config/waaseyaa.php` doesn't exist at expected path. |

## Reviewer guidance

- Verify the `fallback_chain` is `null` by default (resolver picks up built-in chain) — explicit null beats omission.
- Verify env-var naming matches project convention (`WAASEYAA_` prefix).

## Implementation command

```bash
spec-kitty agent action implement WP12 --agent <name>
```

## Activity Log

- 2026-05-13T00:26:54Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=610035 – Started implementation via action command
- 2026-05-13T00:31:17Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=610035 – Translation config: fallback_chain (null=default) + read_active_language (env-driven, default false)
- 2026-05-13T00:32:07Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=612342 – Started review via action command
