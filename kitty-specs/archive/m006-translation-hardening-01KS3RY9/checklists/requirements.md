# Specification Quality Checklist: M-006 Translation Hardening

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-20
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
  - **Note:** Names internal artifacts (`TranslationController`, `EntityAccessHandler`, `phpStringLiteral`) — these are existing framework symbols this mission modifies, not "implementation choices."
- [x] Focused on user value and business needs
  - User value: consumers can safely flip `translatable: true` without ad-hoc access wiring or operator-driven code injection.
- [x] Written for the audience that matters
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Requirement types are separated (Functional / Non-Functional / Constraints)
- [x] IDs are unique across FR-###, NFR-###, and C-### entries
- [x] All requirement rows include a non-empty Status value
- [x] Non-functional requirements include measurable thresholds
  - NFR-001: ≤2% p95 overhead; NFR-002: single canonical constant referenced from a named location; NFR-003: conforms to existing JSON:API error shape per `docs/specs/jsonapi.md`.
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
  - Primary (denied PATCH), two recoveries (CLI injection rejected; consumer interface implementation), five edge cases.
- [x] Edge cases are identified
- [x] Scope is clearly bounded
  - Out-of-scope: per-langcode policies, schema changes, controller base-class refactor, encryption, admin UI, new audit subsystem.
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
  - FR-001..FR-004 → SC-001/002. FR-005..FR-007 → SC-003. FR-008/009 → SC-004. FR-010..FR-013 are the regression-test FRs themselves; their SCs are the test-suite results.
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification
  - WP outline is indicative; FRs name outcomes (access check called, regex validates, interface declares method), not implementation steps.

## Notes

- **WP02 has a named decision point** the planner should resolve early: where the canonical regex constant lives. The mission allows `packages/i18n/` or `packages/entity/`; the planner picks based on which package owns langcode semantics today. Recording the chosen location in the WP02 prompt closes that loop.
- **Cross-mission interaction with M-B:** M-B's container-resolved `AccessPolicyRegistry` lands before or alongside this mission. M-C's FR-001 access gate uses `EntityAccessHandler` (the same class M-B touches as policies are resolved through it), so the WP01 author should confirm M-B's WP02 has not changed `EntityAccessHandler::check`'s signature in an incompatible way. If both missions are in flight in parallel, the planner should sequence M-B's WP02 before this mission's WP01.
- The mission **does not** revisit M-006's schema or storage decisions. Anyone tempted to fold in "while we're here, let's fix X in the schema" should file a new mission.
