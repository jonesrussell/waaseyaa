---
work_package_id: WP09
title: Spec, docs, security review
dependencies:
- WP01
- WP02
- WP03
- WP04
- WP05
- WP06
- WP07
- WP08
requirement_refs:
- NFR-008
- NFR-009
- NFR-010
- NFR-011
- NFR-012
- NFR-013
- NFR-014
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T045
- T046
- T047
- T048
- T049
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: skills/waaseyaa/ai-integration/SKILL.md
execution_mode: planning_artifact
owned_files:
- skills/waaseyaa/ai-integration/SKILL.md
- packages/ai-agent/README.md
- CHANGELOG.md
- docs/security/agent-executor-review.md
- kitty-specs/agent-executor-01KRWPK7/checklists/release-readiness.md
tags: []
---

# WP-09 — Spec, docs, security review

## Objective

Wrap up the mission. Refresh the operator-facing docs that describe
the new runtime, bullet the CHANGELOG `[Unreleased]` section,
run the `security-review` skill on the aggregate PR, and file a
v1 release-readiness checklist that confirms every success criterion
and gate.

## Context

- Spec SCs in scope: **SC-010** (gates green).
- Doctrine spec: `docs/specs/agent-executor.md` is **already filed** and stays the canonical source of truth — this WP does not rewrite it.
- Skill location: `skills/waaseyaa/ai-integration/SKILL.md`.
- Constitution rule: keep README content lean; prefer one-line description + link to doctrine spec.

## Branch strategy

Planning + merge target: `main`. Lane allocated by `spec-kitty agent mission finalize-tasks`. WP-09 is the wrap-up — it depends on the union of WP-01..WP-08 having merged.

---

## Subtask T045 — Update `skills/waaseyaa/ai-integration/SKILL.md`  `[P]`

**Purpose:** Make the specialist skill discover the new runtime.

**Steps:**
1. Open `skills/waaseyaa/ai-integration/SKILL.md`.
2. Update the spec list to add `docs/specs/agent-executor.md` as a primary reference.
3. Add a "Running an agent" section with three short subsections:
   - **CLI:** `bin/waaseyaa ai:run "<prompt>" --inline`.
   - **HTTP:** `POST /api/ai/agent/run`; consume SSE via `/broadcast?channels=agent.run.<id>`.
   - **Extension:** how to register an `AgentDefinition` or `AgentTool` via attribute discovery.
4. Add a "Where the code lives" section listing the new package + the entity locations + the route service provider.
5. Cross-reference `packages/ai-tools/README.md` (filed in WP-01).
6. Keep total content focused; this skill is loaded on demand and must stay small.

**Files:**
- `skills/waaseyaa/ai-integration/SKILL.md`

**Validation:**
- [ ] Skill renders.
- [ ] Doctrine spec link works.

---

## Subtask T046 — Rewrite `packages/ai-agent/README.md`  `[P]`

**Purpose:** Replace the legacy README that documented the deleted `AgentInterface`.

**Steps:**
1. Open `packages/ai-agent/README.md`.
2. Title + one-paragraph description: "Waaseyaa agent runtime: the executor, the run service, the Messenger handler, and the in-process audit log persistence."
3. Sections:
   - **Where to start:** point to doctrine spec.
   - **Surfaces:** CLI (link to WP-06 commands), HTTP (link to API contract), Messenger (the worker).
   - **Extension points:** `#[AsAgentDefinition]`, `#[AsAgentTool]` (in `waaseyaa/ai-tools`).
   - **Quality gates:** layers, dead-code, composer-policy, bulk-edit.
4. Remove every reference to `AgentInterface` and `McpToolDefinition`.

**Files:**
- `packages/ai-agent/README.md`

**Validation:**
- [ ] No stale references.
- [ ] Doctrine spec link works.

---

## Subtask T047 — CHANGELOG bullet  `[P]`

**Purpose:** Surface the mission in the next release.

**Steps:**
1. Open `CHANGELOG.md` at the top, under the `[Unreleased]` heading.
2. Add bullets per the changelog skill convention. Suggested wording:
   ```markdown
   ### Added
   - `packages/ai-tools` — shared tool catalogue (8 stock tools + remote MCP via `McpClientToolSource`). #1496
   - Agent executor v1: `bin/waaseyaa ai:run`, `POST /api/ai/agent/run*`, `AgentRun` + `AgentAuditLog` persisted entities, HITL state machine, stalled-run reaper, scheduled purge. #1496

   ### Changed
   - `Waaseyaa\AI\Agent\ToolRegistry::register()` now takes a single `AgentTool` VO. The legacy `(McpToolDefinition, callable)` signature has been removed.
   - `McpController` consumes the `packages/ai-tools` registry for `tools/list` / `tools/call`.

   ### Removed
   - `Waaseyaa\AI\Agent\AgentInterface` (replaced by `AgentRunService`).
   - `Waaseyaa\AI\Schema\Mcp\McpToolDefinition` (replaced by `Waaseyaa\AI\Tools\AgentTool`).
   - `packages/mcp/src/Tools/{Entity,Discovery,Traversal,Editorial}Tools.php`.
   - `McpToolExecutor::accessCheck(false)` bypass. Entity ACLs now apply to all MCP tool calls.
   ```
3. The release-cut workflow promotes `[Unreleased]` content into the next version heading automatically.

**Files:**
- `CHANGELOG.md`

**Validation:**
- [ ] Bullets land under `[Unreleased]`.
- [ ] Format matches the Keep-a-Changelog template (Added / Changed / Removed sections).

---

## Subtask T048 — Run `security-review` skill on the aggregate PR

**Purpose:** Independent security pass before tagging.

**Steps:**
1. Invoke the `security-review` skill against the aggregate diff for the agent executor mission.
2. Focus areas per the spec's surfaces:
   - Authentication: `_account` reads only; no new auth surface (**C-011**).
   - Authorization: per-route capability gates + entity-level `AgentRunAccessPolicy`.
   - Input validation: `AgentRunRequestValidator` (T033). JSON Schema enforcement on tool arguments.
   - Secret handling: env-var indirection on `api_key_env_var` / `auth_header_env_var` (**C-010**).
   - Bypass-capability scope (`agent.run.bypass_ownership`): does it leak into admin-SPA UX unintentionally? (No SPA work in this mission — verify no leak.)
   - Audit-log tamper resistance: `AgentAuditLog` is append-only outside the purge job (**C-014**).
   - Provider HTTP timeouts: are they bounded? (Yes, per `config.ai.providers.timeout_ms`.)
3. Record the output in `docs/security/agent-executor-review.md` with at-minimum sections: **Scope**, **Findings**, **Mitigations**, **Outstanding risks**, **Sign-off date**.
4. If the review surfaces real findings, raise them as separate issues + decide per-finding whether to defer (file under v1.1) or block the merge.

**Files:**
- `docs/security/agent-executor-review.md`

**Validation:**
- [ ] File exists with the documented sections.
- [ ] No deferred blocking findings remain at the time of merge.

---

## Subtask T049 — v1 release-readiness checklist

**Purpose:** Single artifact that proves every success criterion + gate.

**Steps:**
1. Create `kitty-specs/agent-executor-01KRWPK7/checklists/release-readiness.md`.
2. Sections:
   - **Success Criteria (SC-001..SC-010).** Each row: criterion + evidence link (test name, command output, PR/commit reference).
   - **NFR thresholds.** Each NFR-001..NFR-015: threshold + measured value or test-name reference.
   - **Quality gates.** Layers, dead-code, composer-policy, PHPStan, cs-check, bulk-edit, no-secrets, external-consumers.
   - **Sign-off.** Mission lead + date.
3. Run `composer verify` (the umbrella script that runs every gate) and paste the tail into the checklist as evidence.
4. Run the relevant integration tests one more time and verify NFR thresholds (`AsyncHttpRunTest` for NFR-002, `CliInlineRunTest` for NFR-001, `CancellationTest` for NFR-003, `ReaperTest` for NFR-004, `InteractiveHitlTest` for NFR-005).

**Files:**
- `kitty-specs/agent-executor-01KRWPK7/checklists/release-readiness.md`

**Validation:**
- [ ] Every SC has an evidence link.
- [ ] Every NFR has a measured value.
- [ ] Every gate exits 0 in the most recent CI run.

---

## Definition of Done

- [ ] T045..T049 checkboxes flipped.
- [ ] Skill, README, CHANGELOG updated.
- [ ] Security review output filed.
- [ ] Release-readiness checklist filed with full SC + NFR + gate evidence.
- [ ] Bulk-edit diff-compliance: zero `BLOCK` rows (run one final time).
- [ ] All gates green.

## Risks & mitigations

1. **Security review surfaces blocking findings.** *Mitigation:* re-open the relevant earlier WP; not all findings can be deferred.
2. **Docs go stale by the time of merge.** *Mitigation:* land this WP last in the train; reviewers cross-check against the final state of WP-01..08.

## Reviewer guidance

- Read each doc top-to-bottom — out-of-date paragraphs are the usual failure mode.
- Verify CHANGELOG bullets reference `#1496` (or the canonical mission issue).
- Confirm the security review covers every external-facing surface (HTTP, CLI, MCP).
- Confirm the release-readiness checklist's evidence links resolve.

## Implementation command

```
spec-kitty agent action implement WP09 --agent <name>
```
