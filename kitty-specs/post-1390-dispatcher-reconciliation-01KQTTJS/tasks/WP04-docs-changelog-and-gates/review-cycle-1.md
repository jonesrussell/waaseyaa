---
affected_files: []
cycle_number: 1
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
reproduction_command:
reviewed_at: '2026-05-05T16:16:26Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP04
---

# WP04 Review Feedback (Cycle 1 → reject, return to planned)

## Verdict: REJECT

The T015 skip is not justified. The pre-existing `[Unreleased]` bullet (added by main commit `454d00f77`) was written **before** WP01's contract pinned the post-#1390 schema. It documents two things wrongly relative to the locked contract and the production code that WP02 shipped, and it actively misdescribes the unbound-array behaviour. Because consumers (Minoo and other downstream projects) will read this CHANGELOG line at the next alpha cut, this must be corrected before WP04 can be approved.

T013 (api-layer.md cross-link) and T014 (CLAUDE.md skip) are otherwise fine — see notes below.

---

## Issue 1 (BLOCKING): CHANGELOG bullet uses `method_name`; contract and code emit `method`

- **File:** `CHANGELOG.md`, the `[Unreleased] > Fixed` bullet starting `**Implicit-array controller signature compatibility (#1390)**` (single line).
- **Offending text (literal):** `with payload keys \`controller_class\`, \`method_name\`, \`parameter_name\`, \`recommended_attribute\` so consumer tooling can inventory migration debt.`
- **Why wrong:**
  - WP01 contract §5 (`kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/post-1390-dispatcher-contract.md`) lists the payload field as `method`, not `method_name`.
  - Source `packages/ssr/src/Http/AppController/AppParameterBindingBuilder.php` lines 501 and 539 both emit `'method' => $method` in the log context.
  - Contract test `packages/ssr/tests/Contract/DispatcherDeprecationContractTest.php` asserts the bare `method` key (no occurrences of `method_name` in assertion code).
- **Required fix:** Change `method_name` to `method` in the payload-keys list. Consumer tooling that grep/jq this CHANGELOG line for the dedup-key shape will get a false signal.

## Issue 2 (BLOCKING): CHANGELOG bullet says unbound `array $X` "continue to raise `InvalidAppControllerBindingException`"; WP02 changed that behaviour

- **File:** `CHANGELOG.md`, same `[Unreleased]` bullet, near the end.
- **Offending text (literal):** `Other unannotated \`array\` parameter names continue to raise \`InvalidAppControllerBindingException\`.`
- **Why wrong:**
  - Contract §3 / §6 (post-shim) defines the unbound case: an unannotated `array $X` parameter named anything other than `params`/`query` MUST bind to `[]` and emit one `implicit_array_unbound` notice with `recommended_attribute=''`. It MUST NOT throw.
  - Source `AppParameterBindingBuilder.php` (around line 539) emits the `implicit_array_unbound` notice and binds to `[]` — verified live in this lane's source.
  - Contract test §Test 6 (`packages/ssr/tests/Contract/DispatcherDeprecationContractTest.php` lines 202–235) locks this: `assertSame('implicit_array_unbound', $entry['context']['event'])` and `assertSame('', $entry['context']['recommended_attribute'])`. No exception is thrown.
- **Required fix:** Replace the throw-claim with a one-clause description of the unbound behaviour, e.g. `Other unannotated \`array\` parameters now bind to \`[]\` and emit one \`implicit_array_unbound\` notice (\`recommended_attribute=''\`) rather than throwing, so consumers get a non-fatal migration signal.` Adjust phrasing to taste; the load-bearing facts are: binds to `[]`, emits `implicit_array_unbound`, no exception.

## Issue 3 (NON-BLOCKING but related — fix while you're in there): T013 spec text repeats the `method_name` drift

- **File:** `docs/specs/api-layer.md`, the new `<!-- Spec reviewed 2026-05-05 ... -->` HTML comment (line ~48 in the diff).
- **Offending text:** `structured \`dispatcher.deprecation\` log payload deduplicated per \`(controller_class, method_name, parameter_name)\` per request.`
- **Why noted:** The dedup tuple in this comment uses `method_name`, matching the CHANGELOG drift. The contract uses `method` as the canonical field name, and the body of the new spec section (the table) doesn't repeat `method_name` — only the change-history HTML comment does. Either align the comment to `method`, or rephrase to make clear the dedup key is the (class, method, parameter) triple at runtime regardless of how the field is labelled in the log payload.
- **Recommendation:** Fix at the same time as the CHANGELOG so api-layer.md spec and CHANGELOG agree post-merge.

---

## What is fine in commit `683e47b27` (do not change)

- T013 added a real, well-formed `## Controller parameter binding (SSR app dispatcher)` section in `docs/specs/api-layer.md` that documents the params/query/unbound trichotomy correctly in the table, and the cross-link `kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/post-1390-dispatcher-contract.md` resolves on disk (verified `ls -la`).
- T014 skip is justified: `CLAUDE.md` line 46 maps `packages/ssr/*` to `packages/ssr/README.md`, which transitively covers `packages/ssr/src/Http/AppController/*`. Adding a duplicate row would dilute the table.
- Diff scope of `683e47b27` is correct: only `docs/specs/api-layer.md` is touched (verified via `git show --name-only 683e47b27` → 1 file). The cumulative lane diff (`git diff kitty/mission-...lane-a`) shows WP02+WP03+WP04 files combined, which is expected for a shared-lane workspace.

## Gates (all green, retain on next cycle)

- `composer cs-check` → exit 0
- `composer phpstan` → `[OK] No errors`, exit 0
- `bin/check-composer-policy` → `OK: Composer policy checks passed`, exit 0
- `bin/check-package-layers` → `OK — package layer constraints (composer.json + PHP file-level) satisfied.`, exit 0
- `./vendor/bin/phpunit` → 7222 tests, 2 failures both in `tests/Integration/Queue/QueueIntegrationTest.php` (`workerRunProcessesMultipleJobsThroughDbalTransport`, `workerRunMixesSuccessAndFailure`); confirmed pre-existing — `./vendor/bin/phpunit tests/Integration/Queue/QueueIntegrationTest.php` returns 8/8 OK in isolation. Orthogonal to this mission.
- `tools/drift-detector.sh` → `No specs affected by recent changes.`, exit 0

## Cycle-2 implementer instructions (succinct)

1. Edit the existing `[Unreleased] > Fixed` bullet in `CHANGELOG.md` in place. Do not add a second bullet — augment the existing one. Specifically:
   a. Replace `method_name` with `method` in the payload-keys list.
   b. Replace the `... continue to raise \`InvalidAppControllerBindingException\`` clause with a description of the new `implicit_array_unbound` non-throwing behaviour (binds to `[]`, emits notice with `recommended_attribute=''`).
2. Optionally (Issue 3) fix `method_name` → `method` in the new HTML comment in `docs/specs/api-layer.md`.
3. Re-run all five gates plus `tools/drift-detector.sh`. Do not touch `phpstan-baseline.neon`.
4. Amend or add a commit on top of `683e47b27`; do not rebase WP02/WP03 commits.
