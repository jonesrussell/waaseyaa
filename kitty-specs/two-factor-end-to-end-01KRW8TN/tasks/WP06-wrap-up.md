---
work_package_id: WP06
title: Wrap-up — spec, baseline regen, CHANGELOG
dependencies:
- WP05
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-004
- FR-005
- FR-006
- FR-007
- FR-008
- FR-009
- FR-010
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T021
- T022
- T023
- T024
history: []
authoritative_surface: docs/specs/
execution_mode: planning_artifact
owned_files:
- docs/specs/access-control.md
- docs/specs/two-factor-auth.md
- phpstan-dead-code-baseline.neon
- CHANGELOG.md
tags: []
---

# WP06 — Wrap-up

## Objective

Document the shipped 2FA contract, regenerate the dead-code baseline (verifying the 6 TwoFactorManager entries dropped), and write the CHANGELOG entry. Verify `composer verify` green.

## Branch strategy

Planning base: `main`. Final merge target: `main`. Execution worktree per `lanes.json`.

## Subtasks

### T021 — Update `docs/specs/access-control.md`

Add a new section "Two-Factor Authentication" describing:
- The four endpoints + their auth requirements.
- The `state: 2fa_required` login response shape.
- The User entity field extensions (link to `docs/specs/two-factor-auth.md`).
- Cross-link to `docs/specs/two-factor-auth.md` for the full contract.

Keep it short — full contract lives in the dedicated spec.

### T022 — Create `docs/specs/two-factor-auth.md`

Full contract doc:
- Architectural intent (paraphrase spec.md).
- Surface: `TwoFactorService` public methods + `TwoFactorSetupResult` shape.
- HTTP endpoints with request/response examples.
- Storage shape (the two User fields).
- Recovery code handling.
- Edge cases (the matrix from plan.md).
- Layer placement.

Use sibling specs like `access-control.md` as a structural template.

### T023 — Regenerate baseline + verify drop

```bash
rm -rf tmp/phpstan-dead-code
vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon --allow-empty-baseline --memory-limit=2G
grep -c 'TwoFactorManager' phpstan-dead-code-baseline.neon
# Expected: 0
```

If non-zero entries remain, identify which methods + investigate. The 6 to drop are: `generateSecret`, `verifyCode`, `getCurrentCode`, `getQrCodeUri`, `generateRecoveryCodes`, `verifyRecoveryCode`.

`getCurrentCode` may stick around if no production caller uses it directly. If so, this is the per-plan risk — add `@api` PHPDoc to the method or accept its lingering entry (baseline gate still passes for the rest).

### T024 — CHANGELOG entry

Add to `## [Unreleased]` → `### Added`:

> - **Two-Factor Authentication end-to-end (#1499).** Real TOTP + recovery-code 2FA wired from primitives (`TwoFactorManager`) through service (`TwoFactorService`), four HTTP endpoints (`/auth/2fa/{setup,enable,verify,disable}`), `LoginController` integration emitting `state: 2fa_required` for enabled users, and User entity field extensions. Closes the wire gap left when the primitives shipped in March. 6 TwoFactorManager entries dropped from `phpstan-dead-code-baseline.neon`.

### Final verification

Run from project root:
```bash
composer verify
```

Expected: all gates green including `check-dead-code`.

## Definition of Done

- `docs/specs/access-control.md` updated.
- `docs/specs/two-factor-auth.md` exists with full contract.
- `grep -c 'TwoFactorManager' phpstan-dead-code-baseline.neon` returns 0 (or only `getCurrentCode` if accepted; document why).
- `CHANGELOG.md` `[Unreleased]` has the entry.
- `composer verify` exits 0.

## Risks

- **Risk:** Baseline regen surfaces new unrelated dead-code findings. *Mitigation:* compare entry counts; only the 6 TwoFactorManager entries should drop. If other entries appear, investigate before committing.
- **Risk:** Spec doc text drifts from implementation. *Mitigation:* keep T022 content rooted in plan.md's file-by-file outline.

## Reviewer guidance

- Verify the CHANGELOG bullet uses `Closes #1499` so the issue auto-closes on merge.
- Verify the spec docs reference correct file paths.

## Implement command

```bash
spec-kitty agent action implement WP06 --agent <name>
```
