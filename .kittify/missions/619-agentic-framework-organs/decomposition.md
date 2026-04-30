# Decomposition — agentic-framework-organs

Date: 2026-04-30 (Pass 2 WP01 output)

Mission absorbs 4 closed issues (#619 epic, #620 ai-memory, #621 ai-guardrails, #623 ai-agent kernel extensions) from the Track 2 Bimaaji & agentic roadmap. The umbrella RFC lives at `claudriel/docs/specs/waaseyaa-agentic-framework-rfc.md`. The mission charter (per `spec.md`) names three organs: **ai-memory**, **ai-guardrails**, **ai-observability**. Observability already exists in live source (`packages/ai-observability/`), so the mission's net-new framework work is two new packages (memory, guardrails) plus brainstem extensions to the existing `ai-agent` package.

---

## Mission summary

The Track 2 agentic-framework mission grows Waaseyaa from "AI plumbing" (schema, vector, pipeline, agent, observability) into a native agentic framework with three organ packages and an extended brainstem. The architecture is deliberately star-shaped: the brainstem (`ai-agent`) orchestrates, the organs (memory, guardrails, observability) sit beside it as independent capabilities, and organs do not depend on the brainstem. Live source confirms five Layer 5 packages exist today (`ai-agent`, `ai-pipeline`, `ai-schema`, `ai-vector`, `ai-observability`); two are missing (`ai-memory`, `ai-guardrails`). The mission's job is to add the two missing organs, extend the brainstem with planning/routing/reflection/multi-agent capabilities, and lock the contract surface so downstream consumers (Claudriel, Bimaaji, application code) can build against stable interfaces rather than concrete classes.

This is a contract-first mission, not a mechanical sweep. It belongs in the same architectural-mode lane as #824, not the layer-coverage lane as #1335.

---

## Absorbed issues

| # | Title | Contract surfaces extracted |
|---|-------|------------------------------|
| #619 | epic: Waaseyaa Native Agentic Framework | Star-shaped Layer-5 topology; "organs do not depend on brainstem" rule; 21-pattern coverage map; design principles (entity system as persistence, composable via service providers, zero downstream knowledge) |
| #620 | feat: ai-memory package — conversation, semantic, episodic memory | Memory store interfaces (`ConversationMemoryStore`, `SemanticMemoryStore`, `EpisodicMemoryStore`, `StaticMemoryBlock`); operations (`MemorySummarizer`, `MemoryRetriever`, `MemoryConsolidator`, `MemoryDecay`, `ReweaveProcessor`, `FactExtractor`); entity types (`Memory`, `MemorySession`, `MemoryTurn`); dependency on `ai-vector` + `entity` + `entity-storage`; non-dependency on `ai-agent` |
| #621 | feat: ai-guardrails package — permissions, safety, assurance | Permission model (`ToolPermissionPolicy`, `CanUseToolCallback`, `PermissionResolver`, `ActionClassifier`); safety policies (`GuardrailPolicy`, `InputValidator`, `OutputValidator`, `ModelConstraint`); assurance hooks (`PreExecutionHook`, `PostExecutionHook`, `EscalationHandler`); audit (`GuardrailAuditLog`); zero-dependency policy layer |
| #623 | feat: ai-agent kernel extensions — planning, routing, reflection, multi-agent | Planning (`PlannerInterface`, `PlanExecutor`, `PlanStep`); routing (`TaskClassifier`, `ModelRouter`, `PromptRouter`, `FallbackChain`); reflection (`ReflectionLoop`, `SynthesisOutput`, `CritiqueAgent`); multi-agent (`AgentRegistry`, `Orchestrator`, `ResultSynthesizer`, `PeerDiscovery`); parallelization + context assembly hooks |

All four closed at 2026-04-30 with the `track-2-bimaaji` label.

---

## Contract surfaces consolidated

Surfaces are clusters of interfaces, value objects, events, and registration hooks that move together.

### S1 — Memory organ public surface (ai-memory)

- **Stores:** `ConversationMemoryStoreInterface`, `SemanticMemoryStoreInterface`, `EpisodicMemoryStoreInterface`, `StaticMemoryBlockInterface`. Each is a read/write store keyed on session id or fact id.
- **Operations:** `MemorySummarizerInterface`, `MemoryRetrieverInterface`, `MemoryConsolidatorInterface`, `MemoryDecayInterface`, `ReweaveProcessorInterface`, `FactExtractorInterface`.
- **Entity types:** `Memory`, `MemorySession`, `MemoryTurn` registered via `EntityTypeManager` (Layer 1 contract; honors entity-storage invariant).
- **Dependencies:** `waaseyaa/ai-vector` (embeddings), `waaseyaa/entity`, `waaseyaa/entity-storage`. Must NOT depend on `waaseyaa/ai-agent`.
- **Layer:** 5 (AI). Dependency chain stays inside L5 + L1.

### S2 — Guardrails organ public surface (ai-guardrails)

- **Permission model:** `ToolPermissionPolicyInterface`, `CanUseToolCallback` (functional callable contract), `PermissionResolverInterface`, `ActionClassifierInterface`. Tier enum (read-only / write-low / write-high / destructive) lives here.
- **Policies:** `GuardrailPolicyInterface`, `InputValidatorInterface`, `OutputValidatorInterface`, `ModelConstraintInterface`.
- **Hooks:** `PreExecutionHookInterface`, `PostExecutionHookInterface`, `EscalationHandlerInterface`. These are the integration seams the brainstem calls into.
- **Audit:** `GuardrailAuditLogInterface` (parallel to existing `AgentAuditLog` in `ai-agent`).
- **Dependencies:** none mandatory. Pure policy layer. May depend on `waaseyaa/foundation` for logging.
- **Layer:** 5. Independent of memory and brainstem.

### S3 — Brainstem extension surface (ai-agent additions)

This surface extends an existing package; it does not create one. Existing source (`packages/ai-agent/src/`) contains `AgentInterface`, `AgentExecutor` (final, see #1242), `AgentAuditLog`, `ToolRegistry`, `Provider/ProviderInterface`, plus `Event/{LlmCallCompleted,ToolCallStarted,ToolCallCompleted}`. The extension layers four new clusters on top:

- **Planning:** `PlannerInterface`, `PlanExecutorInterface`, `PlanStep` (value object), planning events.
- **Routing:** `TaskClassifierInterface`, `ModelRouterInterface`, `PromptRouterInterface`, `FallbackChainInterface`.
- **Reflection:** `ReflectionLoopInterface`, `SynthesisOutput` (value object), `CritiqueAgentInterface`.
- **Multi-agent:** `AgentRegistryInterface`, `OrchestratorInterface`, `ResultSynthesizerInterface`, `PeerDiscoveryInterface`.
- **Hookpoints to organs:** brainstem calls memory's retriever during context assembly and guardrails' pre/post hooks around tool execution. These call sites live in `AgentExecutor` (or its successor interface).

This surface has the highest #1242 entanglement: extracting `AgentExecutorInterface` is a precondition for substituting in planning/routing/reflection/multi-agent variants.

### S4 — Brainstem-to-organ wiring contract

The cross-cutting glue. Even though organs do not depend on the brainstem, the brainstem depends on organs by interface. This means:

- `ai-agent` may `require` `waaseyaa/ai-memory` and `waaseyaa/ai-guardrails` only against published interfaces.
- The brainstem's executor must accept `?MemoryRetrieverInterface $memory = null` and `?PreExecutionHookInterface $guardrail = null` (paired-nullable pattern is acceptable here too).
- Service providers expose organs to the kernel; the brainstem resolves them by type.

This is the surface most likely to drift if WPs are run in the wrong order.

### S5 — Entity types and storage (Memory entities only)

`Memory`, `MemorySession`, `MemoryTurn` follow the entity-storage invariant from `.claude/rules/entity-storage-invariant.md`: extend `ContentEntityBase`, register via `EntityTypeManager`, persisted through `EntityRepository`. No raw PDO. This is mechanical but worth flagging because issue #620 lists the entity types without specifying the storage path; the implementer must follow the framework rule, not invent a parallel store.

### S6 — Layer discipline and composer.json topology

Two new packages added to `composer.json`, `bin/check-package-layers`, and `phpstan.neon`. Both new packages must:

- Land in Layer 5 only (no upward edges).
- Declare providers/commands/routes via `extra.waaseyaa` (per S7 of the 824 mission, already ratified).
- Pass `bin/check-package-layers` and `bin/check-composer-policy`.
- Appear in `phpstan.neon` `paths:` to satisfy the layer-coverage gate (mission #1335 acceptance condition 2).

This surface inherits enforcement from the existing architectural-remediation mission.

---

## Coupling matrix

|              | S1 mem | S2 grd | S3 brain | S4 wire | S5 entity | S6 layer |
|--------------|:------:|:------:|:--------:|:-------:|:---------:|:--------:|
| S1 memory    |   --   |  none  |  none*   |  weak   |  strong   |  weak    |
| S2 guardrail |  none  |   --   |  none*   |  weak   |   none    |  weak    |
| S3 brainstem |  none* |  none* |    --    |  strong |   none    |  weak    |
| S4 wiring    |  weak  |  weak  |  strong  |   --    |   none    |  none    |
| S5 entities  | strong |  none  |   none   |  none   |    --     |  none    |
| S6 layer     |  weak  |  weak  |   weak   |  none   |   none    |   --     |

`none*` is the load-bearing principle: organs (S1, S2) do not import from brainstem (S3). The reverse direction (S3 imports interfaces from S1, S2) is permitted and expressed in S4.

---

## Decision: NO-SPLIT

The four issues form one coherent organ-system contract. Although memory and guardrails could in principle ship as independent packages, three structural facts argue against splitting the mission:

1. **The wiring surface (S4) is shared.** Brainstem extensions (#623) consume both organs through the executor's call sites. Splitting memory and guardrails into separate missions would force WP-level coordination across mission boundaries to keep the executor's hookpoints coherent. That is exactly the cross-mission coupling NO-SPLIT exists to avoid.
2. **The brainstem extensions (#623) are not independent.** Planning/routing/reflection/multi-agent need somewhere to read context from (memory) and somewhere to gate tool calls (guardrails). Shipping the brainstem extension mission ahead of either organ produces dead hooks. Shipping organs without brainstem extensions produces unused interfaces. The dependency graph collapses to a single critical path.
3. **The 21-pattern coverage map (#619 epic) is the acceptance frame.** The mission accepts when all framework-side patterns are covered across the 7 Layer-5 packages. Pattern coverage is a single audit, not three.

Counter-argument considered: ai-guardrails has zero runtime dependencies and could plausibly ship first as a standalone safety library. Rejected because the brainstem hookpoints (S4) must be designed alongside guardrails to avoid retrofitting; the hooks are the value, not the policy classes alone.

NO-SPLIT. One mission, one decomposition, organ-level WPs sequenced by dependency.

---

## Work package roster

WP01 was this decomposition (you are reading it). Subsequent WPs are sequenced strictly: spec-lock first, then organs (memory and guardrails can run in parallel because their surfaces don't touch), then brainstem extensions (which consume both), then the wiring + verification gate.

| Slug | Title | Surface | Member issues |
|------|-------|---------|---------------|
| WP02 | spec-lock-and-rfc-import | S1, S2, S3, S4 | #619 |
| WP03 | ai-memory-package-scaffold | S1, S5, S6 | #620 |
| WP04 | ai-guardrails-package-scaffold | S2, S6 | #621 |
| WP05 | ai-agent-kernel-extensions | S3 | #623 (and depends on #1242 `AgentExecutorInterface` extraction) |
| WP06 | brainstem-organ-wiring | S4 | derived from all four issues |
| WP07 | layer-discipline-and-coverage-gate | S6 | derived; depends on mission #1335 acceptance criteria |

Sequencing rules:

- WP02 must complete before any implementation WP enters the implement lane. The current `spec.md` is a scaffold; it must name public contracts, breaking changes, and verification evidence before WP03+ can run.
- WP03 and WP04 may run in parallel (independent contract clusters, S1 and S2 do not couple).
- WP05 depends on `AgentExecutorInterface` extraction (#1242, separate Track 3 mission). If that mission has not landed, WP05 must include the extraction as preliminary work — flag this in WP05 spec.
- WP06 depends on WP03, WP04, and WP05 all having published interfaces.
- WP07 is the verification gate: confirms both new packages appear in `bin/check-package-layers`, `phpstan.neon`, and the layer-coverage gate from mission #1335.

### Cleanup follow-ups (no WP required)

- Update `CLAUDE.md` Layer 5 table to list `ai-memory` and `ai-guardrails`.
- Update `docs/specs/ai-integration.md` (and create `docs/specs/ai-memory.md` + `docs/specs/ai-guardrails.md` if structure warrants).
- Re-run `tools/drift-detector.sh` to confirm Layer 5 specs are not stale post-merge.

---

## PROPOSED CONTRACTS — needs ratification before WP02 implement

These contracts are net-new framework surfaces not previously ratified in `824-architectural-remediation/spec.md` or anywhere else under `docs/specs/`. They cannot be auto-approved by a WP02 implementer; the user must sign off on them as framework-public commitments before WP03+ touch code.

| # | Contract | Package | Justification |
|---|----------|---------|---------------|
| C1 | `MemoryStoreInterface` (parent of conversation/semantic/episodic) | ai-memory | Lets consumers depend on a single read/write contract instead of three concrete sub-stores. |
| C2 | `MemoryRetrieverInterface` | ai-memory | The brainstem's hookpoint for context assembly. Stable name needed before S4 wiring. |
| C3 | `ReweaveProcessorInterface` | ai-memory | Ars Contexta backward-pass pattern. Novel concept; needs framework-level naming. |
| C4 | `GuardrailPolicyInterface` | ai-guardrails | Core policy contract. Must not collide with existing `Waaseyaa\Bimaaji\Policy\GuardrailRule` (different layer, different concept — see drift flag D1). |
| C5 | `ToolPermissionPolicyInterface` + tier enum | ai-guardrails | Tiered access model. The four-tier shape (read-only / write-low / write-high / destructive) is a framework commitment. |
| C6 | `PreExecutionHookInterface`, `PostExecutionHookInterface` | ai-guardrails | Brainstem call sites. Naming must align with existing `ai-agent` event names (`ToolCallStarted`, `ToolCallCompleted`) to avoid two parallel concepts. |
| C7 | `EscalationHandlerInterface` | ai-guardrails | Human-in-the-loop seam. Could overlap with existing workflow/notification packages — needs scope ratification. |
| C8 | `PlannerInterface` + `PlanStep` value object | ai-agent | Public planning surface. |
| C9 | `AgentRegistryInterface` + `OrchestratorInterface` | ai-agent | Multi-agent surface. The "registry" name conflicts with existing `ToolRegistry` semantics — naming needs explicit ratification. |
| C10 | `AgentExecutorInterface` | ai-agent | Already proposed in #1242 (Track 3). Listed here because WP05 depends on it; if #1242 lands first, this contract is already ratified. Otherwise WP05 must extract it. |

Ratified (do NOT re-propose):

- `Waaseyaa\Foundation\Kernel\KernelServicesInterface` (824 mission).
- `composer verify` root command (824 mission).

---

## Drift flags

| # | Flag | Detail |
|---|------|--------|
| D1 | **Existing `Guardrail*` classes in `bimaaji`** | Live source has `packages/bimaaji/src/Policy/GuardrailRule.php` and `SovereigntyGuardrails.php`. These are Bimaaji-specific Anishinaabe sovereignty guardrails (Layer 4), not the framework-level `ai-guardrails` package proposed in #621. Issue #621 does not acknowledge this overlap. WP02 must explicitly state the relationship: `ai-guardrails` provides the framework contract; `bimaaji` becomes a consumer that implements `GuardrailPolicyInterface` for Anishinaabe-specific rules. Otherwise the names will collide in code review and in `docs/specs/`. |
| D2 | **Issue #619 architecture diagram lists `ai-observability` as an organ to be built, but the package already exists** | `packages/ai-observability/` is in live source (see `Trace.php`, `Recorder/`, `BudgetManager.php`, etc.). The mission `spec.md` correctly names observability as one of three organs, but #619's "Phase plan" lists it as `[ ]` not built. Mission scope is therefore *extend* observability, not *build* it. Clarify in WP02. |
| D3 | **Issue #620 references `ai-pipeline` as a memory dependency in the architecture diagram but says "depends on `ai-vector`, `entity`, `entity-storage`" in the Dependencies section** | Resolve in WP02: pipeline is not a hard dependency. The diagram shows topology, not edges. |
| D4 | **`#1242` (AI-layer interface extraction) is not absorbed by this mission** | Issue #1242 proposes `AgentExecutorInterface`, `EmbeddingPipelineInterface`, etc. It is in Track 3, not Track 2, so it is correctly not in this mission's `child_issues`. But WP05 depends on it. If #1242 lands first, WP05 is cleaner. If not, WP05 inherits the extraction. WP05 spec must call this out explicitly. |
| D5 | **No `claudriel/docs/specs/waaseyaa-agentic-framework-rfc.md` accessible from this repo** | Issue #619 references the RFC by relative path inside the Claudriel repo. Subagent confirms it is not under `/home/jones/dev/waaseyaa/docs/`. WP02 must either inline the relevant sections of the RFC into `docs/specs/ai-memory.md` and `docs/specs/ai-guardrails.md`, or fetch and copy the RFC into Waaseyaa's `docs/specs/` before WP03+ run. The framework cannot accept contracts whose authoritative reference lives in a downstream consumer repo. |
| D6 | **No `Waaseyaa\AI\Memory` or `Waaseyaa\AI\Guardrails` namespace exists today** | Confirmed by file system scan. This is expected (these are net-new packages), but WP03 and WP04 must claim those namespaces explicitly in `composer.json` autoload sections. |

---

## Spec doc impact

| Spec doc | Surfaces touching it | Nature of change |
|----------|----------------------|------------------|
| `ai-integration.md` | S1, S2, S3, S4 | Add memory/guardrails as cross-cutting agentic concerns; document brainstem hookpoints |
| `ai-memory.md` (new) | S1, S5 | Author from scratch; lift from claudriel RFC |
| `ai-guardrails.md` (new) | S2 | Author from scratch; cross-reference Bimaaji guardrails as a consumer |
| `extension-compatibility-matrix.md` | S6 | Refresh Layer 5 row to list 7 packages |
| `package-discovery.md` | S6 | List `extra.waaseyaa` registration for the two new packages |
| `CLAUDE.md` Layer Architecture section | S6 | Add `ai-memory`, `ai-guardrails` to Layer 5 |
| `authoring-assist-contract.md` | S3, S4 | Audit: planning/reflection extensions may require contract update |

---

## Risks

1. **Brainstem-organ circularity through interface shape.** Even with the "organs do not depend on brainstem" rule, careless interface design (e.g., `MemoryRetrieverInterface` taking an `AgentInterface` parameter) can sneak the dependency back in. Mitigation: WP02 must enumerate every method signature on the cross-organ interfaces and verify zero brainstem types appear.
2. **Naming collision with existing Bimaaji guardrails (D1).** Two `Guardrail` concepts in the codebase, one Layer 4, one Layer 5. Without WP02 spec text disambiguating, code review will produce drift over multiple PRs.
3. **#1242 sequencing (D4).** WP05 either follows #1242's track-3 mission or has to do the extraction itself. Either path works, but the choice must be made in WP02, not discovered mid-implementation.
4. **Entity-storage invariant compliance for Memory entities.** Issue #620 lists `Memory`, `MemorySession`, `MemoryTurn` as entity types but does not enforce the entity-storage rule. Implementer drift toward custom storage (e.g., Redis-backed conversation logs) would violate the invariant. WP03 spec must explicitly require `ContentEntityBase` + `EntityRepository`.
5. **RFC drift between Waaseyaa and Claudriel (D5).** The authoritative RFC lives in Claudriel. Two repos, one source of truth, common drift hazard. Pulling it into Waaseyaa's `docs/specs/` is the only reliable fix.
6. **21-pattern coverage claim is unverifiable from this mission alone.** #619 acceptance says "all 21 patterns covered across the 7 packages." Without an explicit pattern→file mapping committed to `docs/specs/ai-integration.md`, this claim cannot be proven. WP02 should produce that mapping; WP07 should verify it.

---

## Acceptance for the mission as a whole

The mission is done when:

1. `packages/ai-memory/` and `packages/ai-guardrails/` exist with `composer.json`, `src/`, `tests/`, README, and `extra.waaseyaa` registration.
2. Both packages pass `bin/check-package-layers`, `bin/check-composer-policy`, `composer phpstan`, and `composer cs-check`.
3. `ai-agent` exposes `PlannerInterface`, `TaskClassifierInterface`, `ReflectionLoopInterface`, `AgentRegistryInterface` (plus value objects) and the executor surface accepts `?MemoryRetrieverInterface` + `?PreExecutionHookInterface`/`?PostExecutionHookInterface` paired-nullable parameters with negative-path tests.
4. `CLAUDE.md` Layer 5 table lists 7 packages.
5. `docs/specs/ai-memory.md` and `docs/specs/ai-guardrails.md` exist; `docs/specs/ai-integration.md` references them; the 21-pattern→file map is committed.
6. `phpstan.neon` includes both new packages; the mission #1335 layer-coverage gate is green for L5.
7. All 4 absorbed GitHub issues remain closed; merge commits/PRs cite this mission per `docs/specs/workflow.md`.

If any acceptance item slips, the mission stays open. Partial completion of a contract surface produces exactly the kind of integration debt this mission exists to retire.
