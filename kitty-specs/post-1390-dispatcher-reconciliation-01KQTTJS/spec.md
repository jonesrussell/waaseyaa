# Specification: Post-#1390 Dispatcher Reconciliation

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Mission ID**: `01KQTTJS73GVXHFPY5W8E8K3DX`
**Mission Type**: software-dev
**Created**: 2026-05-05
**Target branch**: `main`
**Tracking issue**: [waaseyaa/framework#1391](https://github.com/waaseyaa/framework/issues/1391) (Track 3 — Parity & performance)
**Upstream dependency**: [waaseyaa/framework#1390](https://github.com/waaseyaa/framework/issues/1390)

---

## 1. Overview

Alpha.171/172 introduced a stricter controller-dispatcher invariant that rejects `array $params` / `array $query` parameters on controller method signatures unless they carry an explicit `#[MapRoute]` / `#[MapQuery]` attribute. This was a hard contract break with no shim or deprecation period; the canonical pre-existing controller signature now produces a runtime 500 on every public route. The break re-froze Minoo's `upgrade-waaseyaa-alpha-171-01KQTDC2` mission and is filed upstream as **framework#1390**, which is being addressed independently.

This mission is the **framework-side follow-up reconciliation** that becomes possible once #1390 lands. Its purpose is to:

- Lock in the post-#1390 dispatcher contract so the implicit-array migration ergonomics match the alpha.165 `tenancy:` precedent (working default + deprecation warning + clean migration path).
- Ensure deprecation signals, contract tests, and documentation are in place for the next alpha after #1390.
- Hand Minoo a precise, unambiguous verification recipe so its upgrade mission can resume with confidence.

This mission **does not** re-implement #1390. The dispatcher patch itself is upstream work and is treated as a precondition for WP02+.

## 2. Goals & Success Criteria

### Goals

1. Post-#1390 controller-dispatcher contract is documented in `docs/specs/` with the deprecation surface, implicit-array semantics, and the relationship to `#[MapRoute]`/`#[MapQuery]` made explicit.
2. The framework emits a structured deprecation signal (logger + manifest record) for every controller method that relies on the implicit-array shim, allowing consumers to inventory their migration debt without grepping source.
3. A regression / contract test suite covers both legacy (implicit array) and modern (attribute-based) signatures, plus the deprecation emission, so future invariant tightening cannot silently re-break consumers.
4. CHANGELOG / release-notes entries land for the alpha that supersedes #1390, framed as the closure of the #1388 / #1390 / #1389-era controller-dispatcher reconciliation.
5. Minoo's frozen upgrade mission has a written, verifiable resume checklist (commands + expected outcomes) and can re-test against the next alpha without further framework discovery.

### Success Criteria

- **SC-001**: After installing the post-#1390 alpha, a controller method using the historical implicit-array signature responds successfully on a public route (no 500), confirmed by an integration test that lives in the framework.
- **SC-002**: The same method emits exactly one deprecation log line per registration, identifying the controller class, method, and the missing attribute (`#[MapRoute]` or `#[MapQuery]`) — verified by a unit/contract test.
- **SC-003**: A controller method using explicit `#[MapRoute]` / `#[MapQuery]` attributes produces zero deprecation noise and behaves identically to current alpha.172.
- **SC-004**: An attribute-mapping coverage audit lists every controller method shape in the framework's own controllers and confirms each is either (a) already attribute-annotated, or (b) explicitly accepted as relying on the shim.
- **SC-005**: A Minoo Resume Verification Plan, published in the mission, lists the exact `composer`, test, and smoke commands Minoo must run, with explicit pass/fail signals — usable by an external operator without reading framework source.
- **SC-006**: CHANGELOG `[Unreleased]` carries a release-notes bullet referencing #1390 and the deprecation contract, ready for `release-cut.yml` to promote at the next alpha tag.

## 3. User Scenarios

### Scenario A — Consumer (Minoo) resumes its upgrade

A Minoo maintainer follows the published Resume Verification Plan: bumps `waaseyaa/framework` to the post-#1390 alpha, runs the listed integration and smoke commands, observes successful HTTP 200 responses on previously-failing routes, and ingests the framework deprecation log lines as the canonical migration backlog. They do not need to read framework source to interpret the result.

### Scenario B — Consumer that already migrated

A consumer whose controllers already use `#[MapRoute]` / `#[MapQuery]` (or whose methods do not take `array $params`/`array $query`) bumps to the post-#1390 alpha and observes zero deprecation log lines and unchanged behavior. The new alpha is a no-op for them.

### Scenario C — Framework maintainer guards against future drift

A framework maintainer attempts to add a new dispatcher invariant that would re-break the implicit-array signature. The contract test suite fails, naming the dropped behavior. The maintainer must either (a) extend the deprecation policy explicitly or (b) revise the change.

### Scenario D — Operator inventory

A consumer operator runs the framework's CLI (or tail-reads logs after a smoke test) and obtains a structured list of `(controller, method, missing-attribute)` triples representing the implicit-array migration debt — without modifying their code.

### Edge cases

- A controller method takes both `array $params` and a typed parameter (`AccountInterface $account`, `HttpRequest $request`) — must resolve correctly; the shim only addresses unannotated `array` parameters.
- A controller method has only `array $query` (no `array $params`) — the shim must apply per-parameter, not gate on both being present.
- A controller method declares an `array` parameter that is **not** intended to be route- or query-bound (e.g., a constructor-injected dependency) — the shim must not silently coerce it; the deprecation signal should still flag it for the author.
- The post-#1390 dispatcher patch chooses a different surface than expected (e.g., uses different attributes or default semantics) — WP01's analysis must reconcile this before WP02+ commit to specific test shapes.

## 4. Functional Requirements

| ID      | Requirement                                                                                                                                                          | Status   |
|---------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------|
| FR-001  | The framework SHALL document the post-#1390 controller-dispatcher contract in a `docs/specs/` artifact, covering implicit-array semantics, attribute equivalence, and deprecation policy. | Required |
| FR-002  | The dispatcher SHALL emit exactly one structured deprecation log line per controller-method registration that relies on the implicit-array shim, identifying the class, method name, parameter name, and recommended attribute. | Required |
| FR-003  | The framework SHALL provide a contract test verifying that a controller method with `array $params` (no attribute) responds successfully via the shim and emits the expected deprecation signal. | Required |
| FR-004  | The framework SHALL provide a contract test verifying that a controller method with `#[MapRoute] array $params` responds successfully and emits NO deprecation signal. | Required |
| FR-005  | The framework SHALL provide a contract test verifying that a controller method with `array $query` (no attribute) is treated as `#[MapQuery]` for shim purposes, parallel to FR-003. | Required |
| FR-006  | The framework SHALL provide an attribute-mapping coverage report (audit artifact in the mission directory) for every controller method shape used by framework-shipped controllers. | Required |
| FR-007  | The CHANGELOG `[Unreleased]` section SHALL carry a release-notes bullet describing the dispatcher reconciliation, referencing GitHub `#1390` (and `#1388` if relevant), ready for `release-cut.yml` promotion at the next alpha tag. | Required |
| FR-008  | The mission SHALL publish a "Minoo Resume Verification Plan" artifact listing exact composer, test, and smoke commands plus pass/fail criteria, in the mission's `kitty-specs/.../artifacts/` (or equivalent) directory. | Required |
| FR-009  | The framework MAY expose a CLI surface (e.g., `bin/waaseyaa controllers:audit`) that prints the implicit-array deprecation backlog without requiring a live request — only if WP01 analysis concludes the existing log path is insufficient. | Optional |
| FR-010  | The deprecation log line format SHALL be stable across the next alpha and SHALL be documented in the contract spec (FR-001) so consumer tooling can parse it. | Required |

## 5. Non-Functional Requirements

| ID       | Requirement                                                                                                                                  | Threshold                                                                  | Status   |
|----------|----------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------|----------|
| NFR-001  | The deprecation signal MUST NOT degrade request latency for controllers that do not rely on the shim.                                         | Zero added work per request when no implicit-array params are present.    | Required |
| NFR-002  | The deprecation signal MUST be emitted at most once per controller-method registration per process.                                           | Deduplicated by `(class::method)` key for the lifetime of the dispatcher. | Required |
| NFR-003  | The contract test suite MUST run within the existing PHPUnit configuration without requiring new database engines or external services.       | Uses `DBALDatabase::createSqlite()` or in-memory fixtures.                 | Required |
| NFR-004  | The Minoo Resume Verification Plan MUST be self-contained and runnable by an operator with no framework knowledge beyond the README.          | Plan reviewer reproduces all steps without escalation.                    | Required |
| NFR-005  | All new code MUST follow the project's PHP 8.4+ style: `declare(strict_types=1)`, typed signatures, `final class` for concrete implementations, named constructor parameters where ambiguous. | PHPStan level 5 passes; PHP-CS-Fixer dry-run clean.                       | Required |

## 6. Constraints

| ID    | Constraint                                                                                                                                                     |
|-------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| C-001 | This mission MUST NOT implement framework#1390 itself. The dispatcher fix is upstream work and is treated as a precondition for WP02+.                          |
| C-002 | This mission MUST NOT modify Minoo or any consumer outside `waaseyaa/framework`. Consumer-side migration is a separate engagement.                              |
| C-003 | This mission MUST NOT touch `vendor/`.                                                                                                                          |
| C-004 | WP01 (analysis) executes immediately on `main`. WP02 onwards are GATED — no WP02+ work begins until framework#1390 is merged on `main`. The gate is enforced by the WP file's preconditions section, verified at agent dispatch. |
| C-005 | The mission MUST stay within the controller-dispatcher subsystem. Adjacent invariants (e.g., entity field-definition migration, JsonResponseTrait) discovered during analysis are filed as separate issues, not absorbed into this mission. |
| C-006 | All framework changes MUST land on `main` via Spec-Kitty PRs that reference the mission and a GitHub issue per project workflow rules (`docs/specs/workflow.md`). |
| C-007 | Layer discipline: changes MUST stay within the layer of the affected package(s) per the layer architecture table in CLAUDE.md. The dispatcher lives in API/L4; cross-layer ripple is in scope only if it does not introduce upward dependencies. |
| C-008 | Composer policy: any manifest changes MUST satisfy `bin/check-composer-policy` (no `@dev`, sorted packages, `self.version` only at root, etc.).                  |

## 7. Assumptions

- Framework#1390 will be fixed via a compatibility shim that defaults unannotated `array $params` / `array $query` to `#[MapRoute]` / `#[MapQuery]` semantics, plus a deprecation signal — matching the pattern requested in the issue. WP01 reconciles this assumption against the actual landed PR.
- The post-#1390 alpha will be cut as a single release that consumers (especially Minoo) can pin to. CHANGELOG promotion is via the existing `release-cut.yml` workflow.
- The existing `LoggerInterface` in `Waaseyaa\Foundation\Log\` is the appropriate emission channel for the deprecation signal. WP01 confirms or proposes an alternative.
- Minoo is the canonical "stuck consumer" used to size the migration. The Resume Verification Plan is written for Minoo first; other consumers can adapt it.
- The contract test base class (or fixture pattern) for dispatcher tests already exists in some form in `packages/api/tests/` or `packages/routing/tests/`. WP01 confirms or scaffolds.
- No new entity types, schema migrations, or storage changes are required.

## 8. Dependencies & Out-of-Scope

### Hard dependencies

- **framework#1390** — the dispatcher compatibility shim itself. WP02+ are gated until this lands on `main`. WP01 may proceed against the current `main` (alpha.172) state, but its final analysis must reference the merged shape of #1390.
- **framework#1391** — this mission's GitHub tracking issue (Track 3 — Parity & performance). All Phase B PRs reference both #1390 and #1391 in their PR description. #1391 is closed manually after WP04 merges and the next alpha tag ships, per `feedback_pr_traceability_signals.md`.

### Adjacent / informational

- **framework#1388** — companion regression also surfaced by the Minoo upgrade mission, already closed. Referenced for context and CHANGELOG cross-link.
- **Minoo `upgrade-waaseyaa-alpha-171-01KQTDC2`** — the consumer-side mission that this work unblocks. Out of scope to modify, but its frozen state is the canonical use case for the Resume Verification Plan.
- **Other alpha.171/172 controller-dispatcher invariants** — the original issue suggests "a sweep of all alpha.171–172 controller-dispatcher invariants for hard breaks vs deprecation paths". Such a sweep is in scope at the level of audit (FR-006) but any further invariants discovered are filed as separate issues, not absorbed.

### Out of scope

- Fixing #1390 itself.
- Modifying Minoo or any consumer.
- Re-architecting the controller-dispatcher contract beyond the deprecation/shim policy.
- New attribute kinds beyond `#[MapRoute]` / `#[MapQuery]`.
- `JsonResponseTrait`, EntityType `_fieldDefinitions`, ServiceProvider `setKernelServices`, phpstan baseline — all listed as separate Minoo migration items in #1390's "Mission impact" section. They remain Minoo's problem to migrate; this mission only addresses the dispatcher invariant.

## 9. Key Entities (informational)

- **Controller-dispatcher contract**: The set of rules the framework's request dispatcher applies to controller method signatures (parameter types, attributes, resolution order). Owned by the API layer (L4). Documented post-mission in `docs/specs/`.
- **Implicit-array shim**: The compatibility behavior introduced by #1390 that maps unannotated `array $params` → `#[MapRoute]` semantics and unannotated `array $query` → `#[MapQuery]` semantics, with a deprecation signal.
- **Deprecation log line**: A structured `LoggerInterface` emission keyed by `(controller_class, method_name, parameter_name, recommended_attribute)`, deduplicated per process, used by consumer tooling to inventory migration debt.
- **Minoo Resume Verification Plan**: A self-contained, runnable checklist published in the mission directory that lets a Minoo operator validate the post-#1390 alpha and resume the frozen upgrade mission without further framework discovery.

## 10. Risks

| Risk                                                                                                              | Mitigation                                                                                                              |
|-------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------|
| #1390 lands with a different shape than this spec assumes (e.g., different attributes, different deprecation channel). | WP01 explicitly reconciles its analysis output against the merged #1390 PR and updates the contract spec before WP02 dispatches. |
| Deprecation signal is too noisy and floods consumer logs.                                                          | NFR-002 enforces dedup-per-registration; FR-002 specifies "exactly one" per method registration; contract tests verify. |
| Minoo verification plan misses an edge case and Minoo's upgrade still fails.                                       | The plan is reviewed by mission completion; Minoo's mission is reactivated and run against the new alpha as the live verification. |
| Adjacent invariants (JsonResponseTrait, EntityType `_fieldDefinitions`) get conflated into this mission.            | C-005 enforces hard scope boundary; surface them as separate issues only.                                               |
| Layer discipline drift if dispatcher fix touches both routing and api packages.                                    | C-007 enforces layer rules; `bin/check-package-layers` runs in CI on every WP merge.                                    |

## 11. Acceptance Gates

- All FRs and NFRs above are addressed (or explicitly deferred with rationale).
- All SCs are demonstrably met by tests, artifacts, or runnable plans.
- `bin/check-composer-policy`, `bin/check-package-layers`, `composer phpstan`, and the full PHPUnit suite pass on the merged mission branch.
- CHANGELOG `[Unreleased]` carries the bullet (per FR-007) and is ready for `release-cut.yml` to promote at tag time (per `feedback_changelog_release_workflow.md`).
- Mission tracking issue (if filed per project workflow) is closed; release notes are edited per `feedback_pr_traceability_signals.md`.

## 12. Open Questions

None at spec time. WP01's analysis output may surface clarifications, which are recorded in the WP01 deliverable rather than back-amended into this spec.
