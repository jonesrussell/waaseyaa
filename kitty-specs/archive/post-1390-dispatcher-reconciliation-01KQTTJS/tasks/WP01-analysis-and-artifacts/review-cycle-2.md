---
affected_files:
  - kitty-specs/post-1390-dispatcher-reconciliation-01KQTTJS/artifacts/controller-shape-audit.md
cycle_number: 2
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
reproduction_command:
reviewed_at: '2026-05-05T01:45:00Z'
reviewer_agent: independent-reviewer (Task agent, dispatched via session)
verdict: rejected
wp_id: WP01
---

# WP01 Review — Cycle 2

**Reviewer**: independent reviewer (Task agent, separate context)
**Date**: 2026-05-05
**Decision**: 🔴 Request changes (one blocking issue)
**Severity**: 1 blocking, 0 minor.

## Summary

Cycle-1 fixes (B1, B2a, M1–M4) all confirmed correct against source. The reviewer independently re-verified each fix by reading `AppParameterBindingBuilder.php`, `AppControllerMethodInvoker.php`, `SsrPageHandler.php`, `MapRoute.php`, `MapQuery.php`, `FromRoute.php`, and the WP01 prompt's Definition of Done. **One downstream consequence of the B2a per-request change was missed**: the audit headline still uses per-process arithmetic.

## Independent verification of cycle-1 fixes

| ID | Fix | Verification | Status |
|----|-----|--------------|--------|
| B1 | Removed `#[FromRoute]` from shim-suppressing list in §3, §4. | Source: `AppParameterBindingBuilder.php:112-118` (MapRoute early-return), `120-126` (MapQuery early-return), `128-132` (FromRoute only sets `$fromRouteName`, no return). | ✅ Confirmed |
| B2a | Dedup scope is per-request. | Source: `AppControllerMethodInvoker.php:21` (instantiates `new AppParameterBindingBuilder()` as default), `SsrPageHandler.php` (instantiates the invoker per request). NFR-002 reinterpretation documented inline in §7. | ✅ Confirmed |
| M1 | MapRoute wins by source-iteration order in §4 / §6 last edge case. | Source: `AppParameterBindingBuilder.php:112` returns immediately on MapRoute match before MapQuery loop runs. | ✅ Confirmed |
| M2 | §12 cross-references attribute FRs/NFRs to actual mapped WPs. | Matches WP01 frontmatter `requirement_refs` exactly. | ✅ Confirmed |
| M3 | Audit verification-limit disclaimer added. | Disclosure block present in audit "Why the dispatcher-subject set is so small". | ✅ Confirmed |
| M4 | `grep -F` fallback alongside `jq` recipe. | Both recipes present in resume Step 4 with explanation of when to use each. | ✅ Confirmed |

## Blocking issue (must fix before approve)

### B3 — Audit headline contradicts the now-corrected dedup scope

**Where**: `artifacts/controller-shape-audit.md`, "Summary" section, "Headline" paragraph.

**Current text**:

> **Headline**: when #1390's shim + WP02's deprecation emission ship, the framework itself emits **8 deprecation events per process** (4 methods × 2 implicit-array params each = 8), all from `Waaseyaa\Genealogy\Ssr\GenealogySsrController`. Every other framework-shipped "controller" uses a different invocation pipeline and is not subject to this dispatcher.

**Problem**: This is wrong on two counts after the B2a per-request fix:

1. **Unit error.** With per-request dedup, "8 events" is the upper bound *per request that exercises all 4 methods* — which never happens (a single HTTP request hits one route). The accurate framing is "up to 2 events per request hitting any genealogy SSR route" or "8 unique `(class, method, parameter)` triples across the four genealogy routes". The "per process" claim is what B2b would have delivered, not what B2a did.
2. **Cumulative log volume drifts.** Under per-request dedup, log volume is `O(requests × shimmed-params-on-matched-route)`, not 8. A reader making capacity decisions off the audit will under-count the steady-state noise budget. The contract §7 already spells this out (`O(requests × distinct shimmed params)`); the audit headline contradicts it.

**Why blocking**: WP04 will lift the audit headline straight into CHANGELOG / docs as the framework's self-reported "deprecation noise budget for the next alpha" — this is exactly what the WP01 prompt's T004 validation specifies ("A reviewer can derive the framework's deprecation-noise budget for the next alpha"). Shipping a per-process number when the implementation is per-request gives consumers a wrong estimate they will plan log retention against.

**Suggested fix**: Replace the headline sentence with:

> **Headline**: the framework contains **4 dispatcher-subject methods × 2 implicit-array params = 8 unique `(class, method, parameter)` triples** that will trigger the shim, all from `Waaseyaa\Genealogy\Ssr\GenealogySsrController`. Per the per-request dedup scope (see `post-1390-dispatcher-contract.md` §7), a single HTTP request hitting one of these routes emits up to 2 notices; cumulative log volume scales with traffic as `O(requests × shimmed-params-on-matched-route)`, not with controller count. Every other framework-shipped "controller" uses a different invocation pipeline and is not subject to this dispatcher.

Also recommend adding one sentence to the Summary table caveat, noting the per-request scope so the table is not read in isolation.

## Non-issues considered and dismissed

- **Audit reported "22 first-party controller files surveyed"** — independent grep returned ~25 candidates. Difference attributable to counting conventions for foundation/utility controllers. Not blocking; the *dispatcher-subject* count of 1 is what matters and is verified.
- **Genealogy method count (4 methods × 2 params)** — arithmetically correct; only the unit framing is wrong.
- **Line-number citations** — verified accurate (rejection at 148, classification at 112-126).
- **A1-A6 assumption deferrals in contract §2** — explicit deferrals awaiting #1390's merged shape; not re-flagged per review-prompt instruction.
- **Resume plan NFR-004** — independently read end-to-end in operator mode; runnable without framework knowledge.

## Reviewer's recommendation

**Reject with one blocking issue.** The author did honest cycle-1 work; the B2a→audit downstream consequence was a reasonable miss. Fix B3 (headline rewrite + Summary table caveat sentence) and the artifacts are approve-ready. No source code edits, no other artifacts need to change. Estimated fix time: ~5 minutes.
