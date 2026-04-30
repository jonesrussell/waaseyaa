# Mission spec: 619-agentic-framework-organs

**Charter:** Grow Waaseyaa Layer 5 from "AI plumbing" into a native agentic framework. Add two new organ packages (`ai-memory`, `ai-guardrails`), extend the `ai-agent` brainstem with planning / routing / reflection / multi-agent surfaces, and lock the contract surface so downstream consumers (Claudriel, Bimaaji, application code) can build against stable interfaces rather than concrete classes.

**Milestone:** Track 2 — Bimaaji & agentic

**Origin:** Pass 1 architect-mode triage (2026-04-30). Mission absorbs 4 closed issues: `#619` (epic), `#620` (ai-memory), `#621` (ai-guardrails), `#623` (ai-agent kernel extensions).

**Decomposition artifact:** `decomposition.md` in this directory.

---

## Decision: NO-SPLIT (6 WPs)

Architectural mode (matches `824-architectural-remediation` pattern, not the mechanical `1335` pattern). The four issues form one coherent organ-system contract:

1. The wiring surface (S4: brainstem-to-organ) is shared. Splitting memory and guardrails across missions would force WP-level coordination across mission boundaries on the executor's hookpoints.
2. Brainstem extensions (#623) consume both organs. Shipping the brainstem ahead of either organ produces dead hooks; shipping organs without brainstem extensions produces unused interfaces.
3. The 21-pattern coverage map (#619 epic) is a single audit, not three.

| WP | Title | Surface | Member issues |
|----|-------|---------|---------------|
| WP02 | spec-lock-and-rfc-import | S1, S2, S3, S4 | #619 |
| WP03 | ai-memory-package-scaffold | S1, S5, S6 | #620 |
| WP04 | ai-guardrails-package-scaffold | S2, S6 | #621 |
| WP05 | ai-agent-kernel-extensions | S3 | #623 (depends on `#1242` `AgentExecutorInterface` extraction) |
| WP06 | brainstem-organ-wiring | S4 | derived from all four issues |
| WP07 | layer-discipline-and-coverage-gate | S6 | derived; depends on mission `#1335` acceptance criteria |

**Sequencing.** WP02 first (no implementation moves until contracts are ratified). WP03 + WP04 in parallel (S1 and S2 do not couple). WP05 after `AgentExecutorInterface` extraction (either via `#1242` landing or extracting in-mission). WP06 after WP03 + WP04 + WP05 publish interfaces. WP07 last — verifies layer discipline and feeds the `#1335` coverage gate.

Full per-WP detail in `tasks.md`.

---

## Drift flag resolutions

### D1 — Bimaaji guardrails / framework guardrails relationship — RESOLVED with architect refinement

**User direction (2026-04-30):** "Define `GuardrailPolicyInterface` at L5 and refactor `Bimaaji\Policy\GuardrailRule` to implement it."

**Architect refinement (two reasons the literal reading does not fit):**

1. **Layer rule.** Per `CLAUDE.md` Layer Architecture: *"Packages can only import from their own layer or lower."* `bimaaji` is L4 (API). `ai-guardrails` is L5 (AI). L4 cannot directly `implements` an interface defined in L5 — that requires an L4→L5 import edge, which `bin/check-package-layers` will reject.
2. **Shape mismatch.** Live source confirms `Waaseyaa\Bimaaji\Policy\GuardrailRule` is a `final readonly class` value object carrying `(string $operation, SovereigntyProfile $deniedProfile, string $reason)`. It is data, not behavior. `GuardrailPolicyInterface` is a behavioral contract (evaluate inputs, return a decision). Mashing the two together turns the value object into a hybrid data-and-behavior class and crosses the L4/L5 boundary.

**Resolution honoring user intent (Path A):**

- `GuardrailPolicyInterface` lives at L5 in `waaseyaa/ai-guardrails`. **(matches user direction)**
- `Bimaaji\Policy\GuardrailRule` stays at L4 unchanged as a value object describing a single sovereignty denial rule. **(no destructive refactor of working code)**
- A new L5 class `Waaseyaa\AI\Guardrails\Anishinaabe\SovereigntyGuardrailPolicy` (or sibling package) implements `GuardrailPolicyInterface`, consumes `GuardrailRule[]` instances and `SovereigntyProfile` (both L0/L4 inputs), and renders policy decisions. **(L5 owns behavior; L4 owns data; layer rules respected)**
- Bimaaji L4 becomes a *consumer* of the L5 organ via service composition, never via inheritance.
- The Anishinaabe-specific policy class can ship inside `ai-guardrails` itself or in a new sibling L5 package (`waaseyaa/anishinaabe-guardrails`) — that is a packaging choice deferred to WP04 spec-lock.

**Pushback open:** if the user prefers the literal class-implements-interface mechanic, two paths exist — (a) move `GuardrailRule` and its sibling `SovereigntyGuardrails` up to L5 entirely, deleting them from `bimaaji`; (b) define the contract at a lower layer (L1 or L3) instead of L5. Both contradict the user's "L5" direction in different ways. Path A above is the architect's recommendation; surface for re-ratification if the user disagrees.

### D2 — `ai-observability` already exists — RESOLVED

Mission scope is *extend* observability, not *build* it. Current `spec.md` charter mentions all three organs; reword in WP02 to make this explicit. `packages/ai-observability/` is in live source already.

### D3 — `ai-memory` dependency edge ambiguity — RESOLVED

Memory depends on `ai-vector` + `entity` + `entity-storage`. It does NOT depend on `ai-pipeline`. Issue `#620`'s architecture diagram shows topology, not import edges. WP03 spec must enforce this.

### D4 — `#1242` `AgentExecutorInterface` sequencing — RESOLVED

`#1242` is in Track 3, not Track 2. WP05 spec must call out: if `#1242` lands first, the interface is already ratified and WP05 only consumes it. If not, WP05 inherits the extraction as preliminary work. Surface again at WP05 spec-lock.

### D5 — Authoritative RFC lives in a downstream consumer repo — RESOLVED with copy-back action

The RFC at `claudriel/docs/specs/waaseyaa-agentic-framework-rfc.md` is the source of truth for memory and guardrails design. The framework cannot accept contracts whose authoritative reference lives in a consumer. WP02 must copy/inline the relevant sections into Waaseyaa's `docs/specs/ai-memory.md` and `docs/specs/ai-guardrails.md` before WP03+ run. After this mission lands, `claudriel/docs/specs/waaseyaa-agentic-framework-rfc.md` becomes a historical artifact; Waaseyaa's `docs/specs/` is canonical.

### D6 — `Waaseyaa\AI\Memory` and `Waaseyaa\AI\Guardrails` namespaces are net-new — RESOLVED

WP03 and WP04 must declare the namespaces in each package's `composer.json` `autoload` section. No surprise here; just enforce it.

---

## Ratified contracts (10) — approved 2026-04-30

All 10 contracts are framework-public commitments. Ratified in three batches by package boundary (memory / guardrails / kernel). None overlap with the two contracts already ratified in `824-architectural-remediation/spec.md` (`KernelServicesInterface`, `composer verify`). WP02 spec-lock writes these into `docs/specs/ai-memory.md` and `docs/specs/ai-guardrails.md` as canonical contract sections; `docs/specs/ai-integration.md` cross-references them.

### Batch 1 — Memory organ (ai-memory) — RATIFIED

| # | Contract | Justification |
|---|----------|---------------|
| C1 | `MemoryStoreInterface` (parent of conversation / semantic / episodic stores) | Single read/write contract instead of three concrete sub-stores. |
| C2 | `MemoryRetrieverInterface` | Brainstem hookpoint for context assembly. Stable name needed before S4 wiring. |
| C3 | `ReweaveProcessorInterface` | Ars Contexta backward-pass pattern. Novel concept; framework-level naming locked. |

### Batch 2 — Guardrails organ (ai-guardrails) — RATIFIED

| # | Contract | Justification |
|---|----------|---------------|
| C4 | `GuardrailPolicyInterface` | Core policy contract. L5-defined per D1 Path A; Bimaaji L4 consumes via DI without implementing the L5 interface directly. |
| C5 | `ToolPermissionPolicyInterface` + tier enum | Tiered access model (read-only / write-low / write-high / destructive). The four-tier shape is a framework commitment. |
| C6 | `PreExecutionHookInterface`, `PostExecutionHookInterface` | Brainstem call sites. Naming aligns with existing `ai-agent` events (`ToolCallStarted`, `ToolCallCompleted`); WP04 enforces the alignment. |
| C7 | `EscalationHandlerInterface` | Human-in-the-loop seam. Scope boundary against workflow / notification packages defined in WP04 (escalation = synchronous decision request; notification = async signal; workflow = state machine). |

### Batch 3 — Kernel extensions (ai-agent) — RATIFIED

| # | Contract | Justification |
|---|----------|---------------|
| C8 | `PlannerInterface` + `PlanStep` value object | Public planning surface. |
| C9 | `AgentRegistryInterface` + `OrchestratorInterface` | Multi-agent surface. Naming distinct from existing `ToolRegistry` (which manages tool callables, not agents); `AgentRegistry` is the agent counterpart. |
| C10 | `AgentExecutorInterface` | Also proposed in `#1242` (Track 3). Ratified here from this mission's perspective. If `#1242` ships its own version first, WP05 aligns to that version (single canonical interface, no parallel concepts). |

---

## Acceptance

The mission accepts when ALL of:

1. `packages/ai-memory/` and `packages/ai-guardrails/` exist with `composer.json`, `src/`, `tests/`, README, and `extra.waaseyaa` registration.
2. Both packages pass `bin/check-package-layers`, `bin/check-composer-policy`, `composer phpstan`, and `composer cs-check`.
3. `ai-agent` exposes `PlannerInterface`, `TaskClassifierInterface`, `ReflectionLoopInterface`, `AgentRegistryInterface` (plus value objects). The executor surface accepts paired-nullable `?MemoryRetrieverInterface` + `?PreExecutionHookInterface` / `?PostExecutionHookInterface` with negative-path tests.
4. `CLAUDE.md` Layer 5 table lists 7 packages.
5. `docs/specs/ai-memory.md` and `docs/specs/ai-guardrails.md` exist; `docs/specs/ai-integration.md` references them; the 21-pattern→file map (per `#619` epic) is committed.
6. `phpstan.neon` includes both new packages; the mission `#1335` layer-coverage gate is green for L5.
7. All 4 absorbed GitHub issues remain closed; merge commits / PRs cite this mission per `docs/specs/workflow.md`.
8. `bimaaji` continues to pass `bin/check-package-layers` after the D1 resolution lands (no L4→L5 edge introduced).

Partial completion of a contract surface produces exactly the integration debt this mission exists to retire. If any acceptance item slips, the mission stays open.

---

## Risks

1. **Brainstem-organ circularity through interface shape.** Even with the "organs do not depend on brainstem" rule, careless interface design (e.g., `MemoryRetrieverInterface` taking an `AgentInterface` parameter) sneaks the dependency back in. WP02 must enumerate every method signature on cross-organ interfaces and verify zero brainstem types appear.
2. **D1 layer-rule slip.** If WP04 implementer reaches for the literal "Bimaaji implements GuardrailPolicyInterface" reading, `bin/check-package-layers` will reject the resulting PR. Mitigation: WP04 spec carries the D1 Path A resolution explicitly.
3. **`#1242` sequencing (D4).** WP05 either follows `#1242`'s Track 3 mission or absorbs the extraction. Decision must be made in WP02, not discovered mid-implementation.
4. **Entity-storage invariant compliance for Memory entities.** `#620` lists `Memory`, `MemorySession`, `MemoryTurn` as entity types but does not enforce the invariant. WP03 spec must require `ContentEntityBase` + `EntityRepository`.
5. **RFC drift between Waaseyaa and Claudriel (D5).** Two repos, one source of truth, common drift hazard. Pulling the RFC into Waaseyaa's `docs/specs/` is the only reliable fix.
6. **21-pattern coverage claim is unverifiable from this mission alone.** `#619` acceptance says "all 21 patterns covered across the 7 packages." Without an explicit pattern→file mapping in `docs/specs/ai-integration.md`, the claim is unprovable. WP02 produces the mapping; WP07 verifies it.
