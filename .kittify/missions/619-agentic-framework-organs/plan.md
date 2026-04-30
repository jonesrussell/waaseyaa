# Plan: 619-agentic-framework-organs

Architectural mode mission. Six implementation phases mapped 1:1 to WP02-WP07. Phase boundaries are merge points; nothing crosses a phase line until the prior phase's WP is `approved` per `docs/specs/workflow.md`.

## Phase 1 — Spec lock and RFC import (WP02)

Objective: zero ambiguity on contracts before any new package directory is created.

- User ratifies the 10 proposed contracts (C1-C10 in `spec.md`). Each ratification recorded in `docs/specs/ai-memory.md` or `docs/specs/ai-guardrails.md` as a contract section.
- Copy the authoritative RFC from `claudriel/docs/specs/waaseyaa-agentic-framework-rfc.md` into Waaseyaa `docs/specs/ai-memory.md` and `docs/specs/ai-guardrails.md`. After this, the framework is the source of truth and the consumer's RFC becomes historical.
- Confirm D1 Path A resolution in writing: L5 `GuardrailPolicyInterface` defined in `ai-guardrails`; L4 `Bimaaji\Policy\GuardrailRule` value object unchanged; new L5 `Anishinaabe\SovereigntyGuardrailPolicy` class implements the interface and consumes Bimaaji's data.
- Draft the 21-pattern→file map (per `#619` epic acceptance) in `docs/specs/ai-integration.md`.
- Record `#1242` sequencing decision: precondition-or-absorb.

Exit criteria: WP02 review approved. No code changes outside `docs/specs/`.

## Phase 2 — Organ scaffolds in parallel (WP03 + WP04)

Objective: stand up `ai-memory` and `ai-guardrails` as independent L5 packages with published interfaces.

- WP03: `packages/ai-memory/` with the three store interfaces, three operation interfaces, three entity types via `EntityTypeManager`, `extra.waaseyaa` registration, README, tests.
- WP04: `packages/ai-guardrails/` with policy / permission / hook / audit interfaces, the Anishinaabe sovereignty policy class, `extra.waaseyaa` registration, README, tests. Anishinaabe class consumes `Bimaaji\Policy\GuardrailRule` via DI; no L4→L5 edge.
- Both packages pass `bin/check-package-layers`, `bin/check-composer-policy`, `composer phpstan`, `composer cs-check`.
- Neither package imports from `ai-agent` (confirmed by static check).

Exit criteria: WP03 and WP04 both approved. `bimaaji` still green on layer checks.

## Phase 3 — Brainstem extensions (WP05)

Objective: extend `ai-agent` with planning / routing / reflection / multi-agent surfaces and accept organs by interface.

- Confirm `#1242` status. If `AgentExecutorInterface` has been ratified there, consume directly. If not, extract it as preliminary work in this WP and cross-link.
- Add `PlannerInterface` + `PlanStep`, `TaskClassifierInterface`, `ModelRouterInterface`, `ReflectionLoopInterface`, `AgentRegistryInterface`, `OrchestratorInterface`, plus matching value objects.
- Modify the executor to accept paired-nullable `?MemoryRetrieverInterface` and `?PreExecutionHookInterface` / `?PostExecutionHookInterface` parameters.
- Add negative-path tests: organs absent, organs present, organs throwing.

Exit criteria: WP05 approved. `ai-agent` interfaces stable and documented.

## Phase 4 — Wiring (WP06)

Objective: stitch the brainstem to the organs through interface-only `require` edges and service composition.

- `ai-agent`'s `composer.json` declares `waaseyaa/ai-memory` and `waaseyaa/ai-guardrails` as `require` dependencies, but only public interfaces are imported.
- Service providers wire concrete organ implementations into the executor at boot.
- Static check: zero concrete-class imports across the L5 boundary inside `ai-agent`.
- Integration tests cover the cross-organ executor path.

Exit criteria: WP06 approved. Brainstem and organs work together end-to-end.

## Phase 5 — Layer discipline and coverage gate (WP07)

Objective: prove the new surface honors the framework's enforcement story.

- `bin/check-package-layers` table updated to enumerate `ai-memory` and `ai-guardrails`.
- `phpstan.neon` `paths:` includes both new packages.
- `tools/audit/GenerateLayerAudit.php` (post-`#1335` modernization) reports zero missing public symbols on L5.
- `tools/drift-detector.sh` clean against `docs/specs/ai-memory.md`, `docs/specs/ai-guardrails.md`, `docs/specs/ai-integration.md`.
- 21-pattern→file map verified: every pattern in `#619` epic resolves to a real file in one of the 7 L5 packages.

Exit criteria: WP07 approved. Mission accepts.

## Cross-phase invariants

- "Organs do not depend on brainstem." Statically enforced by `bin/check-package-layers` and verified at every WP review.
- Paired-nullable parameters (`?Foo $a = null, ?Bar $b = null`) follow the framework convention: both non-null or both null. Negative-path tests required.
- `composer verify` (the root command from `824` mission) gates every WP merge. Any new package that does not appear in `composer verify`'s coverage fails review.
- No `psr/log`. Use `Waaseyaa\Foundation\Log\LoggerInterface` everywhere.

## Sequencing summary

```
WP02 ─┬─→ WP03 ─┐
       │         ├─→ WP06 ─→ WP07
       ├─→ WP04 ─┤
       └─→ WP05 ─┘
              ↑
       (depends on #1242 or absorbs extraction)
```

WP05 sits on the critical path through Phase 3. WP03 and WP04 sit in Phase 2 and may run in parallel.
