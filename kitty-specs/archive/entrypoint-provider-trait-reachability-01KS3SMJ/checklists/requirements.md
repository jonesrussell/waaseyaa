# Specification Quality Checklist: Entrypoint Provider — Trait-Member Reachability

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-20
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
  - Names framework symbols (`WaaseyaaEntrypointProvider`, shipmonk surface) — these are the artifacts this mission modifies.
- [x] Focused on user value and business needs
  - User value: future contributors writing `@api`-annotated traits do not need to remember a per-trait baseline-edit dance; the propagation works the same way for traits as for classes.
- [x] Written for the audience that matters
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Requirement types are separated (Functional / Non-Functional / Constraints)
- [x] IDs are unique
- [x] All requirement rows include a non-empty Status value
- [x] Non-functional requirements include measurable thresholds
  - NFR-001: ≤100 ms additional PHPStan run time; NFR-002: zero new dependencies; NFR-003: durable, not one-shot.
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic
- [x] All acceptance scenarios are defined
  - Primary (entity trait recognized, testing trait recognized), recovery (developer adds new trait with @api).
- [x] Edge cases are identified
  - Non-entity non-test using class, using class without `@api`, transitive trait composition (explicitly out-of-scope), member-level `@api` (out-of-scope), pure reflection-hydrated properties.
- [x] Scope is clearly bounded
  - Out-of-scope: member-level `@api`, trait refactoring, CI gate tightening, transitive composition, new entrypoint mechanisms.
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- **WP01 is an investigative WP** — its deliverable is a diagnosis document, not code. This is intentional: the spec's hypotheses (deeper scan paths, declaring-class question, transitive composition) need empirical confirmation before WP02's fix lands. Skipping WP01 would risk shipping a fix to the wrong hypothesis.
- **The mission deliberately allows two narrow code paths inside one provider method** if WP01 finds the entity-trait gap and the testing-trait gap are mechanistically different. Both must still be driven by class-level `@api` — no per-trait allowlist (FR-003).
- **31 is the floor, not the ceiling.** The mission targets the 31 known entries. If baseline regeneration surfaces additional, unrelated dead-code findings (e.g. a fourth `@api` trait the mission's heuristic also unblocks), that's allowed but not required. Anything else is a separate mission.
- **No vendor patches** is a hard constraint (FR-001). Even if the cleanest fix is "patch shipmonk", we don't do that — our provider extends shipmonk; we patch ours.
