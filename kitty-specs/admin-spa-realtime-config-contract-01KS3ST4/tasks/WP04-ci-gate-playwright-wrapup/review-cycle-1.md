# WP04 Review Cycle 1 — REJECTED

**Verdict:** REJECTED — critical defect in CI gate

**Reviewer:** claude:opus-4-7:reviewer:reviewer
**Mission:** admin-spa-realtime-config-contract-01KS3ST4 (M-F)
**WP:** WP04 — CI gate + Playwright regression + wrap-up

---

## Critical defect: `bin/check-admin-coercion-patterns` is a no-op gate

The CI gate **never reports violations**. It exits 0 even with synthetic violations
placed under `packages/admin/app/`. This was verified two ways:

### Reproduction

```bash
cd /home/jones/dev/waaseyaa/.worktrees/admin-spa-realtime-config-contract-01KS3ST4-lane-a
printf 'const flag = something === "1"\n' > packages/admin/app/violator.ts
bash bin/check-admin-coercion-patterns
# Output: check-admin-coercion-patterns: OK
# Exit:   0
# Expected: non-zero exit, violation reported for violator.ts:1
rm packages/admin/app/violator.ts
```

### Root cause

The script invokes:

```bash
grep -nEP "$COMBINED_PATTERN" "$file" 2>/dev/null | sed ... || true
```

When `grep` is called with both `-E` (extended regex) and `-P` (PCRE) inside the
script's execution context (and especially under `bash -c` as used by
`composer verify`), grep emits `grep: conflicting matchers specified` and exits
non-zero. The script's `2>/dev/null` swallows the error and `|| true` masks the
non-zero exit, so `match_line` is never populated, the inner loop never runs,
`found_violations` stays at 0, and the script reports OK.

Trace evidence (inside the script's own loop, reaching `violator.ts`):

```
REACHED packages/admin/app/violator.ts
const flag = something === "1"
grep test:
grep: conflicting matchers specified
grep returned 2
```

### Fix required

1. Drop `-E` from the grep invocation (use `-nP` only). The patterns in
   `PATTERNS=()` already use PCRE-compatible syntax (`String([^)]*)`), so `-P`
   alone is sufficient. Confirmed working:

   ```bash
   grep -nP "=== \"1\"" file.ts   # works
   grep -nEP "=== \"1\"" file.ts  # "conflicting matchers" inside the script
   ```

2. Stop swallowing grep errors. Replace `2>/dev/null ... || true` with explicit
   handling that distinguishes "no matches found" (exit 1) from "grep error"
   (exit 2). `set -euo pipefail` is in effect — silent failure of the only
   detection mechanism is the worst possible state for a CI gate.

3. **Add the negative test to the WP02 implementer's verification commit log**
   (or to a self-test inside `bin/check-admin-coercion-patterns --self-test`).
   A CI gate without a negative test is by definition unverified. The WP04 prompt
   explicitly required this negative test ("Test it … echo … negative test: add
   a temp file with a violation, confirm exit non-zero"). It was not actually
   performed before claiming completion, or the failure was not noticed.

---

## Other components — spot checks

- **Playwright spec (`packages/admin/e2e/schema-dedup.spec.ts`):** Structurally
  correct. 3 tests cover listing / create / warm-cache scenarios, uses
  `page.on('request')` to count network calls, asserts exactly one
  `/_surface/user/action/schema` (or legacy `/api/schema/user`) per page load.
  Path patterns look correct. Not blocking.

- **CHANGELOG / README / composer.json wiring:** Not fully re-verified after
  identifying the critical gate bug. The gate itself must be fixed and the
  negative-test verification performed before re-review.

---

## Additional defect: status.events.jsonl has unresolved git conflict markers

`kitty-specs/admin-spa-realtime-config-contract-01KS3ST4/status.events.jsonl`
lines 5, 30, and 31 contain `<<<<<<< HEAD` / `=======` / `>>>>>>> kitty/mission-m006-translation-hardening-01KS3RY9-lane-a` from an unresolved
rebase/merge against M-006. This blocks every `spec-kitty agent status emit`
call against this mission with `Invalid JSON on line 5`. Even this rejection
verdict could not be persisted to the lane history.

This must be resolved before re-review can advance:

```bash
# Inspect the conflict region (lines 5-31) and merge the two log windows by
# timestamp, dropping the <<<<<<<, =======, >>>>>>> markers. The conflict
# spans both M-F and M-006 events that were appended concurrently to the
# shared event log; both sequences must be preserved.
${EDITOR:-vim} kitty-specs/admin-spa-realtime-config-contract-01KS3ST4/status.events.jsonl
```

## Required actions for next implementer cycle

1. Fix `bin/check-admin-coercion-patterns` per the root-cause analysis above
   (drop `-E`, or add stderr capture + explicit grep-error handling).
2. **Perform the negative test specified in the WP04 prompt** and paste the
   `EXIT: <nonzero>` output into the WP cycle notes. Without proof of negative
   detection, this gate cannot ship.
3. Optionally: add a self-test mode (`bin/check-admin-coercion-patterns --self-test`)
   that writes a synthetic violation to a tempdir under `packages/admin/app/`,
   runs the gate, asserts non-zero exit, and removes the temp file — so the
   gate's own correctness is enforced going forward.
4. Re-run `composer verify` and confirm `@check-admin-coercion-patterns` is
   actually exercised end-to-end with the fix in place.
