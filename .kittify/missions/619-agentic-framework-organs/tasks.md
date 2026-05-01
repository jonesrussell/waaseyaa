# Tasks / work packages — 619-agentic-framework-organs

WP01 was the Pass-2 decomposition (this directory's `decomposition.md`). WP02 spec-locks contracts and copies the RFC. WP03 + WP04 may run in parallel after WP02 merges. WP05 depends on `AgentExecutorInterface` extraction. WP06 stitches organs to the brainstem. WP07 verifies layer discipline against the `#1335` gate.

| WP | Title | Outcome | Status |
|----|-------|---------|--------|
| WP01 | mission-decomposition | Decomposition artifact in `decomposition.md`. NO-SPLIT decision recorded. 10 contracts surfaced (C1-C10), 6 drift flags raised, all 6 resolved in spec. | done |
| WP02 | spec-lock-and-rfc-import | All 10 proposed contracts ratified by user; RFC copied from claudriel into `docs/specs/ai-memory.md` + `docs/specs/ai-guardrails.md`; D1 Path A resolution committed; 21-pattern→file map drafted in `docs/specs/ai-integration.md`. | unscheduled |
| WP03 | ai-memory-package-scaffold | New `packages/ai-memory/` with `MemoryStoreInterface`, `MemoryRetrieverInterface`, `ReweaveProcessorInterface`, plus `Memory` / `MemorySession` / `MemoryTurn` entity types using `ContentEntityBase` + `EntityRepository`. Passes layer + composer policy checks. Independent of `ai-agent`. | unscheduled |
| WP04 | ai-guardrails-package-scaffold | New `packages/ai-guardrails/` with `GuardrailPolicyInterface`, `ToolPermissionPolicyInterface` + tier enum, `PreExecutionHookInterface` / `PostExecutionHookInterface`, `EscalationHandlerInterface`. Anishinaabe sovereignty policy class implements `GuardrailPolicyInterface` and consumes Bimaaji `GuardrailRule[]` (D1 Path A). Bimaaji L4 untouched. | unscheduled |
| WP05 | ai-agent-kernel-extensions | `ai-agent` exposes `PlannerInterface` + `PlanStep`, `TaskClassifierInterface`, `ModelRouterInterface`, `ReflectionLoopInterface`, `AgentRegistryInterface`, `OrchestratorInterface`. Executor accepts paired-nullable `?MemoryRetrieverInterface` and `?PreExecutionHookInterface` / `?PostExecutionHookInterface`. Depends on `AgentExecutorInterface` extraction (`#1242` mission or in-WP). | unscheduled |
| WP06 | brainstem-organ-wiring | `ai-agent` declares interface-only `require` edges on `ai-memory` and `ai-guardrails`. Service providers wire organs into the executor. Negative-path tests cover both organs absent. No concrete-class import edges. | unscheduled |
| WP07 | layer-discipline-and-coverage-gate | Both new packages added to `bin/check-package-layers` table, `phpstan.neon` paths, and the `#1335` audit-tool L5 enumeration. `tools/drift-detector.sh` clean for L5 specs. 21-pattern→file map verified end-to-end. | unscheduled |

**Review gate:** Each WP runs through Spec Kitty implement → review per `docs/specs/workflow.md`. WP02 must reach `approved` before any other WP can enter `implement`.

## Dependencies (between WPs)

- WP02 → WP03, WP04, WP05 (contracts must be ratified before implementation)
- WP03 ⟂ WP04 (parallel; independent contract clusters)
- WP05 → `#1242` `AgentExecutorInterface` (external precondition; if not landed, WP05 absorbs the extraction)
- WP06 → WP03 + WP04 + WP05 (needs all interfaces published)
- WP07 → WP06 (final verification gate)
- WP07 → `#1335` mission acceptance (the layer-coverage CI gate must be live for WP07 to pass cleanly)

## Per-WP gating notes

- **WP02** must NOT start until the user has ratified C1-C10 (or directed which to drop). Without ratification the spec-lock is empty.
- **WP04** carries the D1 Path A resolution as a required acceptance item: `bin/check-package-layers` must remain green for `bimaaji` after WP04 merges.
- **WP05** must record at start whether `#1242` has landed. If yes, consume `AgentExecutorInterface` directly. If no, extract it as preliminary work and cross-link both missions.
- **WP06** has the highest contract-shape risk. Method signatures on `MemoryRetrieverInterface` and `PreExecutionHookInterface` must not reference `AgentInterface` or any other brainstem type — that re-introduces the L5-internal cycle the architecture forbids.
