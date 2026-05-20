# Specification Quality Checklist: Admin SPA — Realtime Config Contract

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-20
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
  - Names framework symbols (`useSchema`, `useRuntimeConfig`, `nuxt.config.ts`) — these are the artifacts this mission modifies.
- [x] Focused on user value and business needs
  - User value: future contributors writing flag-driven SPA code do not need to repeat the `String(x) === '1'` ceremony; concurrent schema callers do not race; operators get consistent flag behavior regardless of how they typed the value.
- [x] Written for the audience that matters
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Requirement types are separated (Functional / Non-Functional / Constraints)
- [x] IDs are unique
- [x] All requirement rows include a non-empty Status value
- [x] Non-functional requirements include measurable thresholds
  - NFR-001: zero cache-hit overhead, ≤1 ms cache-miss; NFR-002: composable-cached + referentially stable; NFR-003: strict TS build clean, no `any`/`unknown`; NFR-004: CI gate enforces zero residual patterns.
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic
- [x] All acceptance scenarios are defined
  - Primary (single schema fetch per visit, typed config), recovery (new flag), edge cases (invalidate mid-flight, cross-entityType, rejection clear, absent flag, non-public deferred).
- [x] Edge cases are identified
- [x] Scope is clearly bounded
  - Out-of-scope: server-side HTTP surface, schema-cache semantics, new public keys, Nuxt patching, broader E2E coverage.
- [x] Dependencies and assumptions identified
  - Vitest concurrency support; runtime-config keys stable during mission; grep-based CI gate is best-effort; Nuxt coercion is not patched.

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- **WP03's migration sweep depends on a stable inventory of `useRuntimeConfig()` consumers.** If the SPA grows new consumers between WP02 and WP03 (in a parallel branch), the sweep catches them at WP03 execution time — not a regression risk because WP03 is sequential after WP02.
- **The `bin/check-admin-coercion-patterns` CI gate is best-effort by design.** If a future legitimate use case requires a `String(x)` coercion outside `useAdminConfig()`, the convention is an inline `// allow-coercion: <reason>` suppression. This avoids spec inflation while still catching the regression-in-waiting class.
- **No new framework concepts.** `useAdminConfig()` is a composable, `asBoolean` is a pure function — both follow patterns the admin SPA already uses elsewhere.
- **The mission's surface is local to `packages/admin/`.** No PHP-side changes, no API-shape changes, no kernel changes. This is the cleanest, smallest-blast-radius mission of the seven on the triage queue.
