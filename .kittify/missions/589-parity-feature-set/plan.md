# Plan: 589-parity-feature-set

Three phases mapped to WP02 (verification gate) → WP03-WP07 (parallel reconciliation) → mission acceptance. Phase boundaries are merge points; nothing crosses a phase line until the prior phase's WPs are `approved` per `docs/specs/workflow.md`.

## Phase 0 — Ratification (WP01 acceptance)

Objective: lock K1-K10 + C1 and the K7/K8/K9/K10 choice points before any implementation WP starts.

- User ratifies K1-K6 (mechanical / spec-authoring conventions; mostly "yes do it").
- User ratifies K7 (UserBlock placement: messaging package vs deferral).
- User ratifies K8 (EntityFactory: testing-package placement, Faker dev-only, db:seed CLI in `packages/cli`).
- User ratifies K9 (Form API: deferral with explicit follow-up issue).
- User ratifies K10 (Webhook framework: extract to foundation OR defer to follow-up).
- User ratifies C1 (Redis/Memcached cache backends).

Exit criteria: choices recorded in `spec.md`. WP01 marked done. No code changes.

## Phase 1 — Verification gate (WP02)

Objective: produce the canonical audit doc before any code WP runs.

- New file: `docs/audits/2026-04-30-track-3-parity-audit.md`.
- One row per absorbed issue: live-source symbols satisfying it, spec-doc location (or gap), disposition (DONE / PARTIAL / GAP / DEFERRED), pointer to the WP that closes it.
- Spec.md is updated to reference the audit doc as the canonical source.
- The mission's discipline ("live source is ground truth") is recorded in spec.md and at the top of the audit doc.

Exit criteria: WP02 approved. Audit doc merged. Future agents read this first.

## Phase 2 — Parallel reconciliation (WP03 + WP04 + WP05 + WP06 + WP07)

Objective: close the real gaps, document the public surfaces, honestly defer what's unbuilt. Five independent WPs, parallelizable.

- WP03 ships Redis + Memcached cache backends. Integration tests env-gated; CI wires docker-compose if available.
- WP04 authors / extends 10 spec docs. Updates `CLAUDE.md` orchestration table for new docs. Acceptance checkbox per spec entry.
- WP05 ships `UserBlock` per K7 (a) OR documents deferral per K7 (b).
- WP06 ships `EntityFactory` base + Faker dev-dep + `bin/waaseyaa db:seed` CLI + factory definitions for `node`/`user`/`media`/`taxonomy_term`. Deprecates `EntityTypeFixtureValues`.
- WP07 documents Form API deferral (K9 (b)) + webhook framework path (K10 (a) extract OR (b) defer). Files new follow-up issues for any deferral.

Exit criteria: all five WPs approved. Mission acceptance criteria all met.

## Phase 3 — Mission acceptance

- All 13 absorbed issues remain closed; no re-opens.
- Cross-link comments updated on closed issues with merged-commit references.
- New follow-up issues filed (Form API per K9 (b); webhook framework per K10 (b) if chosen).
- `bin/check-package-layers`, `composer phpstan`, `composer cs-check`, `composer check-composer-policy` green.
- `tools/drift-detector.sh` clean for all spec docs touched in WP04.

## Cross-phase invariants

- The live source is ground truth. The 13 issue bodies are stale parity-audit references. Any conflict resolves in favor of source.
- No re-opening of closed issues. Deferrals file NEW issues that reference both this mission and the original closed ones.
- `composer verify` (824 mission) gates every WP merge.
- No `psr/log`. Use `Waaseyaa\Foundation\Log\LoggerInterface` everywhere.
- New spec docs (oidc.md, oauth.md if separated, workflows.md, etc.) get an entry in `CLAUDE.md` orchestration table at WP04 acceptance.

## Sequencing summary

```
WP01 (ratify) ─→ WP02 (audit doc) ─┬─→ WP03 (cache backends)
                                       ├─→ WP04 (spec authoring)
                                       ├─→ WP05 (UserBlock)
                                       ├─→ WP06 (EntityFactory + db:seed)
                                       └─→ WP07 (deferrals)
```

WP02 is the only serial gate. Everything else parallelizes.

## Note on mission shape

This mission is unusual in the Pass-2 cohort. Unlike 824 / 619 / 1257 / 1107, it does not propose meaningful new public framework contracts (apart from C1's two cache-backend implementations). Its primary value is:

1. **Reconciling the parity audit with what already shipped** (avoids an implementer rebuilding existing packages).
2. **Filling the spec-drift gap** for 8 already-shipping packages that landed without `docs/specs/` entries.
3. **Honestly deferring the 2 issues that were closed prematurely** (Form API, webhook framework) so the closure isn't a documentation lie.

If the user prefers, the entire mission could be reframed as a "documentation reconciliation" mission with a different name. Current mission name (`parity-feature-set`) preserved for backward compatibility with the Track 3 milestone label.
