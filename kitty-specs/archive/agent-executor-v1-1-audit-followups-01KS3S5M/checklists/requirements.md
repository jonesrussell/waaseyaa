# Specification Quality Checklist: Agent Executor v1.1 — Audit Follow-ups

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-05-20
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
  - Names framework symbols (`AgentExecutor`, `AnthropicProvider`, etc.) — these are the artifacts this mission modifies, not "implementation choice."
- [x] Focused on user value and business needs
  - User value: operators see live `--watch` progress; observability dashboards are real; retries don't burn quota on 4xx; one broadcaster path exists, not two.
- [x] Written for the audience that matters
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Requirement types are separated (Functional / Non-Functional / Constraints)
- [x] IDs are unique
- [x] All requirement rows include a non-empty Status value
- [x] Non-functional requirements include measurable thresholds
  - NFR-001: ≤1% p95 overhead; NFR-002: ≤2 ms median per event; NFR-003: zero new server-side memory; NFR-004: OpenAPI lint runs in `composer verify`.
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic
- [x] All acceptance scenarios are defined
  - Primary (watch a run, observability dashboard receives events), two recoveries (4xx no retry, 5xx retries), edge cases (concurrent watch, drop, listener exception, double-dispatch, broadcast adapter removal coordination).
- [x] Edge cases are identified
- [x] Scope is clearly bounded
  - Out-of-scope: other providers, state-machine refactor, new lifecycle events, admin UI, new SSE channels, broadcast pruning (#1536 goes to M-D).
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- **WP03's deletion-vs-deprecation decision** is gated by a grep that runs in the WP itself. Honest spec: if any external consumer (Minoo, third-party plugin) references `BroadcastStorageAdapter`, the deletion stages via `@deprecated` first and the real removal lands in a follow-up mission. Planner should record the grep result in the WP prompt.
- **WP02 has an implicit dependency on having an actual canonical OpenAPI doc.** If `packages/api/openapi.yaml` does not exist, NFR-004's "OpenAPI lint" becomes "establish the OpenAPI doc + add the lint." The planner should sanity-check this early — it's a scope risk for WP02.
- **Cross-mission interaction with M-D (Scheduler entry auto-discovery):** M-D folds in #1512 (AgentScheduleEntries::register not invoked) and #1536 (BroadcastStorage::prune scheduling). M-A explicitly excludes both. If M-D lands first, the agent runtime gets free auto-discovery for `AgentScheduleEntries`; if M-A lands first, scheduler wiring stays manual. Either order works; the planner should not block M-A on M-D.
