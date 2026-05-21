# Specification Quality Checklist: Access Fail-Closed Completeness

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-20
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
  - **Note:** Per Waaseyaa convention, spec is framework-internal so it names PHP classes and the kernel surface (`AccessPolicyRegistry`, `SqlEntityQuery`, etc.). These are *artifact identities* — what we are closing the wire on — not "implementation choice" in the sense the checklist is guarding. The framework's own architecture is the user-facing surface.
- [x] Focused on user value and business needs
  - **User value here:** consumer apps (Minoo, future Waaseyaa apps) get a framework that does not leak access-restricted rows from semantic search and does not silently drop access policies. The "user" is the framework consumer (downstream developer) and, transitively, the end user of the consumer app.
- [x] Written for the audience that matters
  - Framework-internal spec for the maintainer + future contributors. Stakeholders are downstream Waaseyaa consumers.
- [x] All mandatory sections completed
  - Why, scenarios, requirements (FR/NFR/C separated), success criteria, key entities, assumptions, out-of-scope, WP outline, references.

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
  - FR-001..FR-013 each name a specific code path / artifact and an observable outcome.
- [x] Requirement types are separated (Functional / Non-Functional / Constraints)
- [x] IDs are unique across FR-###, NFR-###, and C-### entries
- [x] All requirement rows include a non-empty Status value
  - All FRs, NFRs, and Cs carry "Mandatory" in the Status column.
- [x] Non-functional requirements include measurable thresholds
  - NFR-001: <30 s on full repo scan.
  - NFR-002: ≤5 ms per policy class added; existing benchmark or new one.
  - NFR-003: sorted deterministic plain text.
  - NFR-004: ≤2% test duration regression.
  - NFR-005: PSR-11-compatible signature, no Symfony types in public surface.
- [x] Success criteria are measurable
  - SC-001..SC-008 each name an observable outcome and a verification method.
- [x] Success criteria are technology-agnostic
  - SCs name *behaviors* (auth search returns filtered rows, CI fails, kernel throws) — verification methods are necessarily framework-specific (test files, CI gates) but the criteria themselves are outcomes.
- [x] All acceptance scenarios are defined
  - Primary flow (semantic search), two recovery flows (policy add, getQuery regression), edge cases (mid-mission offender, baseline rename ambiguity, nullable deps, circular deps, double-binding test).
- [x] Edge cases are identified
- [x] Scope is clearly bounded
  - Out-of-scope section explicit: baseline-to-zero is M-B.1, other-router retrofit is a follow-up, EntityQueryInterface refactor excluded, field-level access excluded, multi-tenant unchanged.
- [x] Dependencies and assumptions identified
  - Assumptions section covers: container availability at discovery time, EntityQueryInterface stability, CI surface as wiring point, M-B.1 timing, constructor-injection only (YAGNI on setter/property injection).

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
  - Each FR maps to one or more SC entries (FR-001 → SC-001, FR-002/003 → SC-002/008, FR-005/006/007 → SC-003, FR-009 → SC-004, FR-013 → SC-007).
- [x] User scenarios cover primary flows
  - Primary: semantic search with two users.
  - Recovery: new policy added with injected deps; new unbound getQuery callsite.
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification
  - WP outline is indicative; FRs name outcomes, not implementation steps.

## Notes

- The reviewer (planner) should question whether **WP02 needs to land BEFORE WP01**. If `SearchController`'s query-binding helper depends on the new container-resolved registry pattern (e.g. if it needs a service the registry exposes), the dependency flips. Default order in WP outline is WP01 first (independent: it just threads a request attribute), but the planner should verify by reading `SearchController` early in WP01.
- The retro regression tests (FR-009, WP05) intentionally test *behavior* (`setAccount` was called, or `accessCheck(false)` was set), not *implementation*. If a refactor moves the binding to a different chain position, the tests still pass.
- **The mission introduces zero new public framework concepts** — it closes gaps in existing ones. No new entity types, no new attributes, no new interfaces *for consumers*. The new resolver protocol is L0/L1 internal (consumed by the kernel, not by consumer code).
- Items marked incomplete require spec updates before `/spec-kitty.plan`. None are incomplete.
