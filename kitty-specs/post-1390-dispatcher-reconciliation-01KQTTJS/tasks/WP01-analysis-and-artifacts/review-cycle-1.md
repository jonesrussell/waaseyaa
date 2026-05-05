---
affected_files: []
cycle_number: 1
mission_slug: post-1390-dispatcher-reconciliation-01KQTTJS
reproduction_command:
reviewed_at: '2026-05-05T01:30:31Z'
reviewer_agent: claude
verdict: rejected
wp_id: WP01
---

# WP01 Review — Cycle 1

**Reviewer**: claude (self-review — limited independence; flagged in summary)
**Date**: 2026-05-05
**Decision**: 🔴 Request changes
**Severity**: 2 blocking, 3 minor.

## Summary

Three artifacts produced as required (`post-1390-dispatcher-contract.md`, `controller-shape-audit.md`, `minoo-resume-verification.md`). Structure and coverage match the WP01 prompt. **The contract artifact has two internal contradictions that must be fixed before WP02 starts** — one would directly mislead WP02's implementer; the other contradicts NFR-002 in spec.md. Audit and resume plan have minor cleanups.

> Caveat: this review was produced by the same agent that authored the artifacts. For a true second-opinion review, dispatch a separate reviewer agent. The issues below are real (verified against source), not stylistic.

## Blocking issues (must fix before WP02)

### B1 — `#[FromRoute]` semantics contradicted across sections

**Where**: `artifacts/post-1390-dispatcher-contract.md` §3, §4, §6.

**Problem**: §3 and §4 list `#[FromRoute]` among the attributes that suppress the shim ("no `#[MapRoute]`, `#[MapQuery]`, or `#[FromRoute]`"). §6's edge-case row says the opposite: "Method with `array $params` carrying `#[FromRoute('id')]` only ... the shim still engages because `FromRoute` is a route-key remapper, not a binding-kind attribute."

Both can't be true. WP02's implementer will read §3/§4 first and silently produce the wrong behavior.

**Source-of-truth check**: `AppParameterBindingBuilder.php` lines 112–132 — `FromRoute` is processed *after* `MapRoute`/`MapQuery` and only sets a `routeKey` override; it does NOT short-circuit binding-kind classification. So §6 is correct: `#[FromRoute]` does not suppress the shim. §3 and §4 must be revised to say only `#[MapRoute]` and `#[MapQuery]` suppress the shim.

**Fix**: in §3 conditions #1 and #2, drop "or `#[FromRoute]`". In §4's "applies when" column, drop "or `#[FromRoute]`". §6's edge-case row stands.

### B2 — Dedup scope says "per-process" but the wiring delivers per-request

**Where**: `artifacts/post-1390-dispatcher-contract.md` §3 (last sentence) and §7.

**Problem**: §7 calls the dedup scope "per-process" but immediately admits the dedup table lives on the binding-builder, which is owned by the invoker, which `SsrPageHandler` instantiates per-request. So the actual scope today is per-request. NFR-002 in spec.md (mapped to WP02) reads "Deduplicated by `(class::method)` key for the lifetime of the dispatcher" — implying process-scope. The contract's claim is at odds with what the chosen wiring delivers.

WP02 will either (a) deliver per-request dedup and silently violate NFR-002 as the contract describes it, or (b) need a different wiring (move the dedup state to a longer-lived collaborator, e.g., a singleton `DispatcherDeprecationCollector`) — that's a real implementation decision the contract should make explicit.

**Fix options** (pick one and revise the contract):

- **(B2a)** Change §3 last sentence and §7 to say "per-request" scope, and revise NFR-002's prose interpretation: "one notice per registration per request" (acceptable because per-request invoker boots a fresh dedup table). This is the lower-risk path and matches the existing wiring.
- **(B2b)** Keep "per-process" scope and require WP02 to introduce a longer-lived `DispatcherDeprecationCollector` collaborator that the per-request invoker resolves from the container. Heavier change; truer to NFR-002 as written.

I recommend **B2a**: per-request dedup is fine for noise control, and the NFR can be honestly worded that way. But this is a judgment call that should be explicit, not silently inconsistent.

## Minor issues (fix while you're at it)

### M1 — `#[MapRoute] + #[MapQuery]` on same parameter — vague "presumably"

**Where**: `artifacts/post-1390-dispatcher-contract.md` §6, last edge-case row.

**Problem**: Says "Existing behavior (presumably one wins or the second is rejected)." A "decided" table shouldn't say "presumably."

**Fix**: source check says `MapRoute` is processed first (line 112), returns immediately on first attribute found. So `MapRoute` wins by declaration order. State this concretely.

### M2 — §12 cross-reference claim is loose

**Where**: `artifacts/post-1390-dispatcher-contract.md` §12.

**Problem**: "Mission spec: ../spec.md (FR-001, FR-002, FR-010, NFR-001, NFR-002 anchor here)." This artifact is mapped only to FR-001/006/008/009/010 (per WP01 frontmatter). FR-002, NFR-001, NFR-002 are mapped to WP02 — the artifact *describes* them as the contract WP02 will implement against, but it doesn't anchor them.

**Fix**: revise to "FR-001/006/008/009/010 anchor here; FR-002, NFR-001, NFR-002 (mapped to WP02) implemented against this contract."

### M3 — Audit's discovery method is grep-of-callers, not full route-trace — disclose

**Where**: `artifacts/controller-shape-audit.md` "Why the dispatcher-subject set is so small" + "How to reproduce this audit".

**Problem**: The audit excludes JSON:API/auth/MCP/router controllers because they don't `use AppParameterBindingBuilder` or `AppControllerMethodInvoker`. That proves they don't *invoke* the dispatcher directly, but doesn't prove the routes that *target* them don't go through `SsrPageHandler`. Without inspecting `SsrPageHandler`'s dispatch logic, the exclusion is inferred, not proven.

In practice, separate auth/API pipelines almost certainly bypass the SSR page handler. But the audit should disclose this verification limit so a future reader doesn't mistake "verified by grep of callers" for "verified by route trace".

**Fix**: in the audit, add a one-line disclaimer near the verification block: "This audit verifies callers of the dispatcher class. A complete route trace through `SsrPageHandler.handle()` is recommended if a consumer reports a controller emitting deprecation notices that this audit does not list."

### M4 — Resume plan Step 4 assumes JSON-formatted logs

**Where**: `artifacts/minoo-resume-verification.md` Step 4 — the `jq` recipe at the end.

**Problem**: Many Laravel installations write text-formatted logs by default. The `jq` parser will fail on non-JSON lines and produce no output, which the operator could mistake for "no deprecation events fired".

**Fix**: add a fallback grep recipe immediately after the `jq` block: `grep -F 'dispatcher.deprecation' storage/logs/laravel.log | head -20` — works regardless of log format. Note that operators should verify their logger formatter supports structured context, otherwise the JSON-extract recipe is the only way to inventory cleanly.

## Non-issues considered and dismissed

- **§10 (Reconciliation against landed #1390) is a placeholder.** That's correct — #1390 is OPEN, there's nothing to reconcile yet. The placeholder text is informative.
- **Adjacent invariants section says "none filed"**. That's correct — the dispatcher subsystem is internally consistent. Spec C-005 doesn't require filing for the sake of filing.
- **Schema lock at v1 (§5).** Locked correctly. WP02 implements against this; FR-010 is satisfied.
- **`framework_version` deferred from v1.** Correctly noted as optional; doesn't block WP02.

## Reviewer's recommendation

Fix B1 (FromRoute) and B2 (dedup scope) before re-submitting. M1–M4 can land in the same revision. After fixes, WP01 is approve-eligible; the artifacts are otherwise solid and well-structured.

When implementing the fixes, also update the requirement_refs traceability sentence (M2) so future readers don't misread the FR mapping.
