# Specification Quality Checklist: Agent Executor v1

**Purpose:** Validate specification completeness and quality before proceeding to `/spec-kitty.plan`.
**Created:** 2026-05-18
**Feature:** [spec.md](../spec.md)
**Doctrine spec:** [../../../docs/specs/agent-executor.md](../../../docs/specs/agent-executor.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs) leak into the spec's "what / why" sections — *FRs explicitly reference Symfony Messenger, BroadcastStorage, and Layer-5 packages because the framework substrate IS the product surface; this is per-project convention (see other docs/specs/* in the repo). The doctrine spec carries the architectural depth.*
- [x] Focused on user value and business needs — primary scenarios cover admin, operator, automation, reliability, compliance flows.
- [x] Written so business stakeholders can follow the scenarios; technical readers consult the doctrine spec for depth.
- [x] All mandatory sections completed (Why / Scenarios / Requirements / Success Criteria / Key Entities / Bulk-Edit / Assumptions / Dependencies / Scope / WPs / Outstanding-for-plan / References).

## Requirement Completeness

- [x] No `[NEEDS CLARIFICATION]` markers remain in spec.md.
- [x] Requirements are testable and unambiguous — each FR maps to a specific behaviour observable from outside the framework; each NFR has a measurable threshold.
- [x] Requirement types are separated into three tables (Functional `FR-###`, Non-Functional `NFR-###`, Constraints `C-###`).
- [x] IDs are unique across FR-001 through FR-033, NFR-001 through NFR-015, and C-001 through C-014.
- [x] All requirement rows include a non-empty `Status` value (`Active`).
- [x] Non-functional requirements include measurable thresholds (latency budgets, retry counts, byte caps, exit codes).
- [x] Success criteria are measurable — each SC is an observable user / operator / auditor outcome.
- [x] Success criteria are technology-agnostic (no Symfony, no Messenger, no PHP version mentioned).
- [x] All acceptance scenarios are defined (primary, operator, automation, reliability, compliance, plus edge cases).
- [x] Edge cases are identified (cancel-before-pickup, rate-limit storm, HITL timeout, MCP server down, transcript overflow, `--inline` with interactive HITL).
- [x] Scope is clearly bounded — in-scope and out-of-scope sections list specific items.
- [x] Dependencies (upstream + downstream) and assumptions are identified.

## Mission Sizing

- [x] 9 WPs is consistent with prior Track 2 missions (M-006 had 12; M-007 had 11). Mission is large but bounded; critical path (WP-01→05) is sequential by data-flow dependency, and WP-06/07/08 are parallelizable after WP-04.
- [x] No WP is so small it should be absorbed into another (each WP carries ≥1 entity / package / route surface).
- [x] No WP is so large it should be split (WP-01 carries the new package + tool migration but those are tightly coupled; WP-04 carries the message + handler + service but the three pieces have no independent landing path).

## Cross-Mission Linkage

- [x] Doctrine spec (`docs/specs/agent-executor.md`) is the canonical source; this mission spec references it for architectural depth.
- [x] Refs issue #1496 (the agent consumer decision issue, reopened after the predecessor mission was archived).
- [x] Refs PR #1508 (the companion `McpServer` orphan deletion that this mission's design split off from).
- [x] References related specs: `ai-integration.md`, `mcp-endpoint.md`, `broadcasting.md`, `authoring-assist-contract.md`, `infrastructure.md`.
- [x] References governing rule: `.claude/rules/entity-storage-invariant.md` (C-003).
- [x] References archived predecessor: `kitty-specs/archive/ai-agent-end-to-end-01KRW91P/`.
- [x] No conflicting claims with any other in-flight mission. (Verified: no other open mission touches `packages/ai-agent`, `packages/mcp/src/Tools/`, or `packages/ai-tools`.)

## Bulk-Edit Readiness

- [x] `meta.json` carries `change_mode: bulk_edit`.
- [x] Bulk-Edit section in `spec.md` enumerates the 5 cross-cutting renames / moves.
- [x] Expected per-category dispositions sketched in the "Outstanding work for plan" section; `occurrence_map.yaml` to be filed during `/spec-kitty.plan`.
- [x] All 8 standard categories accounted for in the sketch (`code_symbols`, `import_paths`, `filesystem_paths`, `serialized_keys`, `cli_commands`, `user_facing_strings`, `tests_fixtures`, `logs_telemetry`).

## Filing Readiness

- [x] Mission is on `main` (`branch_matches_target: true` from `branch-context`).
- [x] Doctrine spec already committed (commit `5a25eeed8`, 679 lines).
- [x] `feature_dir` scaffolds populated: `spec.md` (this version), `meta.json` (enriched with friendly_name / source_description / vcs / change_mode), `tasks/README.md` (scaffold), this checklist.
- [x] No `status.events.jsonl` writes required at specify time (scaffold-pickup only).

## Feature Readiness

- [x] All FRs have implicit acceptance criteria (the user scenarios + NFR thresholds + success criteria together cover every FR).
- [x] User scenarios cover primary flows (admin SPA, CLI operator, programmatic automation, reliability, compliance).
- [x] Feature meets measurable outcomes defined in Success Criteria.
- [x] No implementation details leak into the success-criteria section.

## Outstanding Work for `/spec-kitty.plan`

- [ ] Produce `occurrence_map.yaml` with all 8 standard categories classified.
- [ ] Confirm `agent.run.approve` capability default versus separate seed.
- [ ] Confirm static price-table location for token cost computation.
- [ ] Confirm `transcript_json` storage column type per backend (TEXT / LONGTEXT / JSON).
- [ ] WP-01 SHALL include a pre-deletion verification grep (per C-006) before removing `AgentInterface`, `McpToolDefinition`, and `packages/mcp/src/Tools/*`.
- [ ] WP-01 SHALL document the security posture of removing `McpToolExecutor::accessCheck(false)` for the `McpController` consumer path (per C-013).

## Notes

- The doctrine spec carries the architectural depth (~679 lines); this mission spec deliberately keeps requirement statements compact and defers schema details, file paths, and diagrams to the doctrine.
- Items in "Outstanding Work for /spec-kitty.plan" are not blockers for filing — they are explicit follow-ups for the plan phase to resolve.
- Charter alignment: software-dev-default template set; domain-driven-design paradigm. No DIR-001/002/003 conflicts apparent.
