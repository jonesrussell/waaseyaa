---
work_package_id: WP25
title: Final perf + parity verification
dependencies:
- WP24
requirement_refs:
- FR-001
- FR-011
- FR-015
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T111
- T112
- T113
- T114
- T115
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "1021691"
history:
- date: '2026-05-08'
  note: Drafted by /spec-kitty.tasks.
authoritative_surface: kitty-specs/native-cli-kernel-01KR2NR7/
execution_mode: planning_artifact
mission_id: 01KR2NR7GYWJKD6CPSN9P2FPC2
mission_slug: native-cli-kernel-01KR2NR7
owned_files:
- kitty-specs/native-cli-kernel-01KR2NR7/plan.md
tags: []
---

# WP25 — Final perf + parity verification

## Branch Strategy

`main` → `main` per lanes.json. **Depends on WP24** (the cut + spec must already have landed).

## Objective

Prove the cut delivered. Re-run the WP01 perf harness, assert NFR-001/002 thresholds met, run all snapshot integration tests for byte-equality, run the gate stack, delete transient mission artefacts.

## Subtasks

### T111 — Re-run perf harness post-cut

```bash
kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh list 10
kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh health:check 10
```

Record the post-cut numbers in `kitty-specs/native-cli-kernel-01KR2NR7/plan.md` § Performance Baseline (the "Post-cut value" column). Compute and fill the "Pass?" column.

### T112 — Assert NFR thresholds

For each row in plan.md § Performance Baseline:
- NFR-001 (wall-time): post-cut ≤ 1.10 × pre-cut.
- NFR-002 (memory): post-cut ≤ pre-cut + 4096 KiB.

If either threshold is breached, this WP FAILS. Open a follow-up mission to investigate (do NOT relax the NFR; do NOT skip — find and fix the regression).

### T113 — Run all snapshot integration tests

```bash
vendor/bin/phpunit packages/cli/tests/Integration/Snapshot/
```

Every test must pass with byte-equality vs WP01-captured fixtures. Failures point to a port WP regression — file an issue against the relevant port WP, do not patch the snapshot.

### T114 — Run gate stack

From repo root:

```bash
composer cs-check
composer phpstan
bin/check-package-layers
bin/check-composer-policy
tools/drift-detector.sh
vendor/bin/phpunit
composer why symfony/console            # must show no waaseyaa/* runtime chain
grep -rn 'Symfony\\Component\\Console' packages/cli/src bin/waaseyaa  # must be empty
grep -rn 'HasCommandsInterface' packages/ bin/ docs/ --include='*.php' --include='*.md'  # must be empty
```

All commands must exit 0 / return empty as appropriate.

### T115 — Delete transient mission artefacts

```bash
rm -rf kitty-specs/native-cli-kernel-01KR2NR7/scripts/
git add kitty-specs/native-cli-kernel-01KR2NR7/scripts/
```

Verify with `ls kitty-specs/native-cli-kernel-01KR2NR7/scripts/ 2>&1` returning "No such file or directory". The harness scripts have served their purpose — they captured baselines in WP01 and re-asserted them here.

## Definition of Done

- [ ] `plan.md` § Performance Baseline has every cell filled, including "Pass?" = ✅ for both rows.
- [ ] All snapshot integration tests pass.
- [ ] All gate-stack commands pass.
- [ ] `composer why symfony/console` shows no waaseyaa/* runtime chain.
- [ ] No `Symfony\Component\Console` reference remains in first-party runtime code.
- [ ] No `HasCommandsInterface` reference remains anywhere.
- [ ] `kitty-specs/native-cli-kernel-01KR2NR7/scripts/` is empty / deleted.
- [ ] **Mission ready for `/spec-kitty.merge`.**

## Risks

- **Performance regression.** If NFRs breach, mission cannot ship. Investigate via opcache profiling; the hand-rolled parser may have a hot path that needs microoptimisation. Open a follow-up mission rather than relaxing thresholds.
- **Snapshot drift.** Any drift is a port WP regression. Do NOT update fixtures to match new output — the original capture is ground truth per FR-015.

## Reviewer guidance

- Verify the post-cut numbers in plan.md are pasted from the literal harness output.
- Verify "Pass?" computation is correct (not eyeballed).
- Re-run the gate stack independently in a clean checkout.

## Implementation command

```bash
spec-kitty agent action implement WP25 --agent <name>
```

## Activity Log

- 2026-05-08T17:49:00Z – unknown – Ready for review — final mission verification: NFR-001/NFR-002 PASS, byte-parity 71/71, full phpunit 7496/0/0, cs/stan/layers/policy/drift all GREEN
- 2026-05-08T17:49:28Z – claude:opus-4-7:reviewer:reviewer – shell_pid=1021691 – Started review via action command
