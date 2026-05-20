# Implementation Plan: Agent Executor v1

**Branch:** `main` (planning + merge target)
**Date:** 2026-05-18
**Spec:** [spec.md](spec.md)
**Doctrine:** [../../docs/specs/agent-executor.md](../../docs/specs/agent-executor.md)
**Mission ID:** `01KRWPK74279EH1Y2RKQZRG9RM`

## Summary

Implement what "running an agent" means in Waaseyaa as a worker-native
hybrid. CLI and HTTP both enqueue a `RunAgent` Symfony Messenger message;
the Messenger worker is the only path that executes `AgentExecutor` in
production; streaming via existing `BroadcastStorage` SSE on channel
`agent.run.<id>`.

Technical approach (from doctrine spec): new Layer-5 package
`packages/ai-tools` houses the attribute-driven tool catalogue shared by
`packages/mcp` (Layer-6 host) and `packages/ai-agent` (Layer-5 runtime).
Identity is the initiator's account; capability gates enforce
`agent.run` (route) plus `tool.<name>` (per-tool). HITL has three modes
(`none`, `all`, `interactive`) with a pause/resume state machine.
`AgentRun` and `AgentAuditLog` are persisted entities under a 30-day
TTL with a scheduler-driven purge job and a reaper for stuck workers.
`McpClientToolSource` adapts remote MCP servers (Streamable HTTP only)
into the same tool catalogue. `ai-observability` subscribes to AgentRun
lifecycle events for token / cost / tool-count / latency telemetry.

## Technical Context

**Language/Version:** PHP 8.5+
**Primary Dependencies:**
- Symfony 7.x (Messenger, EventDispatcher, HttpFoundation, Routing, DependencyInjection, Uid, Yaml)
- Doctrine DBAL 4.x (`Types::TEXT`, which maps to MEDIUMTEXT on MySQL; LONGTEXT/TEXT elsewhere)
- `waaseyaa/foundation`, `waaseyaa/queue`, `waaseyaa/entity`, `waaseyaa/entity-storage`, `waaseyaa/access`, `waaseyaa/config`, `waaseyaa/scheduler`, `waaseyaa/ai-schema`, `waaseyaa/ai-vector` (downstream consumers)

**Storage:** SQLite (dev / tests), MySQL / PostgreSQL (production) via existing `DBALDatabase` abstraction. Entities go through `EntityRepository` per `.claude/rules/entity-storage-invariant.md`.

**Testing:**
- Unit (PHPUnit 10.5, per package, `Waaseyaa\<Pkg>\Tests\Unit`)
- Contract (`Waaseyaa\<Pkg>\Tests\Contract\`)
- Integration (`tests/Integration/PhaseN/AgentRuntime/`)
- Acceptance (CLI + HTTP end-to-end)
- Messenger transport `sync` for tests; real transport (e.g. Doctrine) for production worker

**Target Platform:** Linux (`linux/amd64` and `linux/arm64`) PHP-FPM + Symfony Console. CLI runs under any POSIX shell. Worker runs under systemd or k8s as `bin/waaseyaa messenger:consume`.

**Project Type:** Library / framework monorepo. New scope = 1 new package (`packages/ai-tools`) + significant edits across 7 packages (`ai-agent`, `mcp`, `api`, `routing`, `cli`, `ai-observability`, `scheduler`, `config`).

**Performance Goals (NFR derived):**
- `ai:run --inline` against `NullLlmProvider`: under 10 s wall-clock.
- HTTP-enqueued run end-to-end (HTTP 202 → SSE `run_completed`): under 30 s wall-clock with a single warm worker.
- Cancellation latency: ≤ 3 iteration boundaries + 1 in-flight tool call.
- HITL approval timeout: `hitl_timeout_seconds` (default 300 s) + 1 s.
- Stalled-run reaper: detects past `max_runtime_seconds` (default 600 s) within one scheduler tick (5 min).

**Constraints:**
- `bin/check-package-layers` SHALL pass. `packages/ai-agent` SHALL NOT import from `packages/mcp` (Layer-5 → Layer-6 is forbidden).
- `bin/check-dead-code` SHALL pass with no new findings.
- `composer phpstan` level 5 SHALL pass.
- `composer cs-check` SHALL pass.
- `composer check-composer-policy` SHALL pass (new package CP002 / CP003 / CP-NEW compliance).
- Bulk-edit gate (`occurrence_map.yaml` admissibility + diff-compliance review) SHALL pass.
- All breaking changes are internal-only — pre-deletion grep in WP-01 verifies zero external consumers.
- PHP secrets in env vars only; `config.ai.providers` rows carry env var names.

**Scale/Scope:**
- Initial agent count: dozens (per installation).
- Initial concurrent runs: 1–10 per worker; multi-worker safe via Messenger transport locking.
- Initial tool catalogue: 8 framework-shipped + N remote MCP tools.
- Audit volume: dominated by tool calls; ~10-50 audit rows per typical run; 30-day TTL caps total storage.

## Charter Check

*Gate: must pass before Phase 0 research. Re-checked after Phase 1 design.*

Charter template: `software-dev-default`. Active paradigm: domain-driven-design. Active directives: `DIR-001`, `DIR-002`, `DIR-003`.

| Gate | Pass? | Note |
|---|---|---|
| **DDD: bounded contexts** | ✅ | `ai-agent` (runtime), `ai-tools` (catalogue), `ai-observability` (telemetry), `api` (HTTP), `cli` (operator surface) are distinct contexts with explicit interfaces. |
| **DDD: aggregates** | ✅ | `AgentRun` is the aggregate root for `AgentAuditLog` rows; lifecycle ownership is clear. |
| **DDD: ubiquitous language** | ✅ | Glossary canonicalises `AgentDefinition`, `AgentRun`, `AgentTool`, `AgentAuditLog`, HITL modes. No competing terms across packages after the rename. |
| **Testing standards** | ✅ | Unit + contract + integration + acceptance layering matches Waaseyaa convention; PHPUnit 10.5 attributes; `CommandTester` for CLI; in-memory storage where applicable. |
| **Quality gates** | ✅ | Layer check, dead-code, PHPStan, cs-check, composer-policy all required to pass per NFR-008..013. |
| **Performance benchmarks** | ✅ | NFR-001..007 carry measurable thresholds. |
| **Branch strategy** | ✅ | Mission targets `main` directly (matches `branch_matches_target: true` from `setup-plan`). |
| **DIR-001 / DIR-002 / DIR-003** | ✅ | No conflicts identified. (Will re-check post-design.) |

No violations to track in Complexity Tracking.

## Engineering Alignment

The doctrine spec at `docs/specs/agent-executor.md` (679 lines) carries
the architectural source of truth. The mission spec at `spec.md` carries
the testable requirement statements. This plan adds:

1. **Resolutions for the six outstanding-for-plan items.** Decided
   without re-interrogation because each maps onto an existing project
   convention or has a strongly favoured default:

   | Item | Resolution | Rationale |
   |---|---|---|
   | Per-category dispositions for `occurrence_map.yaml` | See `occurrence_map.yaml`. Default category actions + 3 path exceptions for the `packages/mcp/src/Tools/` → `packages/ai-tools/src/` move, the deletion of `AgentInterface`, and the `accessCheck(false)` removal. | Mirrors the rename / move shape; conservative defaults on `serialized_keys`, `cli_commands`, and `logs_telemetry`. |
   | `agent.run.approve` default | Equals `agent.run`. Holders of `agent.run` may approve. Admins can split via permission config later. | One capability covers the v1 admin/operator personas; split-by-default adds operational friction with no clear consumer. |
   | Static price-table location | `packages/ai-observability/src/Pricing/ModelPriceTable.php` (new file). | Lives with the consumer; namespace consistent (`Waaseyaa\AI\Observability\Pricing\ModelPriceTable`). |
   | `transcript_json` column type | `Doctrine\DBAL\Types\Types::TEXT` (maps to MEDIUMTEXT on MySQL, TEXT on SQLite / PostgreSQL). Truncation logic enforced in application layer at `config.ai.transcript_max_bytes`. | DBAL Types abstracts backend differences; application-layer truncation guarantees deterministic cap independent of column max. |
   | WP-01 pre-deletion verification grep | Added to WP-01 acceptance: `bin/check-external-consumers ai-agent-orphans` (new ad-hoc script in WP-01) greps `packages/`, `tests/`, sample apps for `McpToolDefinition`, `AgentInterface`, `Waaseyaa\\AI\\Schema\\Mcp\\`, `packages/mcp/src/Tools/`. Zero hits required before deletion lands. | Concrete, automatable; aligns with `bin/check-package-layers` / `bin/check-dead-code` pattern. |
   | WP-01 security posture note for `accessCheck(false)` removal | WP-01 includes a `docs/adr/0XX-mcp-tool-access-enforcement.md` ADR noting: removing `accessCheck(false)` strengthens security (entity ACLs now apply); any `McpController` consumer relying on bypass is broken by design (the bypass was for the deleted orphan). | An ADR is the canonical place to document this kind of intentional behavioural change. |

2. **No additional unknowns warranting Phase 0 research agents.** The
   doctrine spec has resolved architectural questions through the
   brainstorming pass. The Phase 0 `research.md` records the outstanding
   decisions resolved above for traceability.

3. **Bulk-edit classification artifact filed.** See
   `occurrence_map.yaml`.

## Project Structure

### Mission documents

```
kitty-specs/agent-executor-01KRWPK7/
├── spec.md                 # Mission spec (filed)
├── plan.md                 # This file
├── research.md             # Phase 0 output
├── data-model.md           # Phase 1 output
├── quickstart.md           # Phase 1 output
├── contracts/
│   └── agent-run-api.yaml  # OpenAPI 3.1 for /api/ai/agent/run*
├── occurrence_map.yaml     # Bulk-edit gate artifact
├── checklists/
│   └── requirements.md     # Spec quality checklist (filed)
├── tasks/                  # /spec-kitty.tasks output (next phase)
├── meta.json               # Mission metadata
└── status.events.jsonl     # Runtime event log
```

### Source code (repository root)

```
packages/
├── ai-tools/                              # NEW package, Layer 5
│   ├── composer.json
│   ├── src/
│   │   ├── AgentTool.php                  # Runtime tool VO
│   │   ├── AgentToolInterface.php
│   │   ├── AgentToolResult.php
│   │   ├── AbstractAgentTool.php          # Provides default argumentsForAudit()
│   │   ├── Attribute/
│   │   │   └── AsAgentTool.php
│   │   ├── ToolRegistryInterface.php
│   │   ├── Catalogue/
│   │   │   └── AttributeToolRegistry.php  # Manifest-compiler-discovered
│   │   ├── Entity/
│   │   │   ├── EntityReadTool.php
│   │   │   ├── EntityListTool.php
│   │   │   ├── EntityCreateTool.php       # destructive
│   │   │   ├── EntityUpdateTool.php       # destructive
│   │   │   ├── EntityDeleteTool.php       # destructive
│   │   │   └── EntitySearchTool.php
│   │   ├── Relationship/
│   │   │   └── RelationshipTraverseTool.php
│   │   ├── Vector/
│   │   │   └── VectorSearchTool.php
│   │   └── AiToolsServiceProvider.php
│   ├── tests/
│   │   ├── Unit/                          # Per-tool tests
│   │   └── Contract/                      # AgentToolInterface conformance
│   └── README.md
├── ai-agent/                              # MAJOR edit (Layer 5)
│   ├── src/
│   │   ├── AgentContext.php               # retained
│   │   ├── AgentResult.php                # extended: token_usage_in/out, cost_cents
│   │   ├── AgentAction.php                # retained
│   │   ├── AgentExecutor.php              # rewired for new ToolRegistry + HITL
│   │   ├── AgentDefinition.php            # NEW: bundle VO
│   │   ├── AgentDefinitionRegistry.php    # NEW
│   │   ├── Attribute/
│   │   │   └── AsAgentDefinition.php      # NEW
│   │   ├── AgentRunService.php            # NEW: enqueue() + runInline()
│   │   ├── Message/
│   │   │   ├── RunAgent.php               # NEW: Messenger message
│   │   │   └── RunAgentHandler.php        # NEW: worker
│   │   ├── Entity/
│   │   │   ├── AgentRun.php               # NEW: persisted entity
│   │   │   └── AgentAuditLog.php          # PROMOTED: in-memory VO → persisted entity
│   │   ├── Repository/
│   │   │   ├── AgentRunRepository.php     # NEW
│   │   │   └── AgentAuditLogRepository.php # NEW
│   │   ├── Mcp/
│   │   │   └── McpClientToolSource.php    # NEW: Streamable-HTTP MCP client
│   │   ├── Reaper/
│   │   │   └── StalledRunReaper.php       # NEW
│   │   ├── Provider/                      # retained (Anthropic, NullLlm, etc.)
│   │   ├── ToolRegistry.php               # signature change: register(AgentTool)
│   │   ├── ToolRegistryInterface.php
│   │   └── (DELETED) AgentInterface.php
│   ├── tests/
│   └── README.md (rewrite)
├── mcp/                                   # MAJOR edit (Layer 6)
│   ├── src/
│   │   ├── McpController.php              # Rewired to consume ai-tools registry
│   │   ├── (DELETED) Tools/                # All four classes deleted
│   │   ├── (existing surface)
│   │   └── ...
│   └── README.md (updated)
├── api/                                   # +controller
│   └── src/
│       └── Controller/
│           └── AgentRunController.php     # NEW: POST/GET/DELETE/approve
├── routing/                               # +1 file
│   └── src/
│       └── AgentRouteServiceProvider.php  # NEW
├── ai-observability/                      # +listeners + price table
│   └── src/
│       ├── Pricing/
│       │   └── ModelPriceTable.php        # NEW
│       └── Listener/
│           └── AgentRunTelemetryListener.php # NEW
├── cli/                                   # +commands
│   └── src/
│       └── Command/
│           └── Ai/
│               ├── AiRunCommand.php       # NEW: ai:run
│               ├── AiPurgeRunsCommand.php # NEW: ai:purge-runs
│               └── AiReapStalledRunsCommand.php # NEW: ai:reap-stalled-runs
├── config/                                # +config entity schemas
│   └── (config entities registered for config.ai.*)
├── scheduler/                             # +schedule entries
│   └── (cron entries for ai:purge-runs, ai:reap-stalled-runs)
└── ai-schema/                             # ToolGenerators updated to emit AgentTool

tests/
└── Integration/
    └── PhaseN/                            # N = current phase
        └── AgentRuntime/
            ├── AsyncHttpRunTest.php
            ├── CliInlineRunTest.php
            ├── CancellationTest.php
            ├── InteractiveHitlTest.php
            ├── McpClientToolSourceTest.php
            ├── ReaperTest.php
            ├── PurgeJobTest.php
            └── McpControllerToolsSharingTest.php

docs/
├── specs/
│   └── agent-executor.md                  # doctrine spec (filed)
└── adr/
    └── 0XX-mcp-tool-access-enforcement.md # NEW (WP-01)
```

**Structure decision:** Multi-package monorepo extension. One new package
(`packages/ai-tools`); seven existing packages edited. Layer rules
enforced by `bin/check-package-layers`. No upward `waaseyaa/*` edges
introduced.

## Phase 0 — Research

See [research.md](research.md). All outstanding-for-plan items resolved
(see Engineering Alignment table above). No NEEDS CLARIFICATION markers
remain. No additional research agents required.

## Phase 1 — Design & Contracts

- **Data model:** [data-model.md](data-model.md). Defines `AgentRun`,
  `AgentAuditLog`, `AgentDefinition`, `AgentTool` plus config entities
  with field-level detail, indexes, state transitions, and column-type
  decisions.
- **API contracts:** [contracts/agent-run-api.yaml](contracts/agent-run-api.yaml)
  is an OpenAPI 3.1 spec for the four HTTP endpoints (`POST` /
  `GET` / `DELETE` / `POST .../approve`). Includes request and
  response schemas, error envelopes, the SSE event vocabulary, and
  capability requirements.
- **Quickstart:** [quickstart.md](quickstart.md) walks an operator
  through configuration, capability seeding, first agent run from CLI,
  and first run from HTTP including SSE consumption.

## Bulk-Edit Plan

`change_mode: bulk_edit`. See [occurrence_map.yaml](occurrence_map.yaml).

Five named cross-cutting changes (per `spec.md` § Bulk-Edit
Classification). Primary target term in the occurrence map is
`McpToolDefinition` → `AgentTool` (the most cross-cutting symbol);
secondary renames (`packages/mcp/src/Tools/` path move, `AgentInterface`
deletion, `ToolRegistry::register()` signature change, `accessCheck(false)`
removal) follow the same category-level rules with explicit path
exceptions where needed.

### Category dispositions

| Category | Action | Notes |
|---|---|---|
| `code_symbols` | `rename` | All affected symbols are internal PHP classes; no external consumers (verified by WP-01 pre-deletion grep). |
| `import_paths` | `rename` | `use` statements follow the symbol renames mechanically. |
| `filesystem_paths` | `rename` | `packages/mcp/src/Tools/*` → `packages/ai-tools/src/*`. No on-disk references outside the repo. |
| `serialized_keys` | `do_not_change` | New JSON API response shapes for `/api/ai/agent/run*` introduce new keys; no existing API key changes its name. |
| `cli_commands` | `do_not_change` | New `ai:*` commands are net-new; no prior `ai:*` namespace exists. |
| `user_facing_strings` | `manual_review` | Admin SPA strings TBD; PR reviewers flag any leakage. |
| `tests_fixtures` | `rename` | Tests follow new symbol names. |
| `logs_telemetry` | `do_not_change` | New telemetry uses new event names; existing log/metric labels preserved. |

### Exceptions

- `packages/mcp/src/Tools/**`: action `rename`, reason: tool classes are
  *moved* (deleted from `mcp`, recreated under new namespace in `ai-tools`).
  The `filesystem_paths` default already permits this; the exception
  documents the move explicitly so reviewers don't confuse it with a
  cross-cutting symbol-only rename.
- `docs/adr/0XX-mcp-tool-access-enforcement.md`: action `rename`, reason:
  new ADR documenting the `accessCheck(false)` removal posture.
- `docs/specs/agent-executor.md`: action `rename`, reason: doctrine spec
  references symbols by name and is part of the rename surface.

## Complexity Tracking

*No charter violations; no entries required.*

## Branch Contract (restated)

- Current branch at plan start: `main`
- Planning / base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: `true`

## Next step

`/spec-kitty.tasks --mission agent-executor-01KRWPK7` to break the 9 WPs
(WP-01 through WP-09 per `spec.md`) into a task manifest.
