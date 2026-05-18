---
work_package_id: WP01
title: packages/ai-tools package + tool migration
dependencies: []
requirement_refs:
- FR-010
- FR-011
- FR-012
- FR-013
- FR-015
- FR-016
planning_base_branch: main
merge_target_branch: main
branch_strategy: 'Plan + merge target: main. Execution worktree is allocated per lane at finalize-tasks time; consult kitty-specs/agent-executor-01KRWPK7/lanes.json.'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
- T008
- T009
history:
- date: '2026-05-18T14:55:10Z'
  actor: tasks-skill
  event: drafted
authoritative_surface: packages/ai-tools/
execution_mode: code_change
owned_files:
- packages/ai-tools/**
- packages/mcp/src/Tools/**
- packages/mcp/src/McpController.php
- packages/mcp/src/McpToolExecutor.php
- packages/mcp/composer.json
- packages/ai-schema/src/Mcp/**
- bin/check-external-consumers
- docs/adr/0XX-mcp-tool-access-enforcement.md
- tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php
tags: []
---

# WP-01 — `packages/ai-tools` package + tool migration

## Objective

Stand up the shared Layer-5 tool catalogue (`packages/ai-tools`) and
move the four existing MCP tool classes into eight stock
`AgentToolInterface` implementations. Rewire `McpController` against
the new attribute-discovered registry, replace
`Waaseyaa\AI\Schema\Mcp\McpToolDefinition` with the new
`Waaseyaa\AI\Tools\AgentTool` VO, and remove the
`McpToolExecutor::accessCheck(false)` bypass.

This is the **bulk-edit foundation WP**. It must finish with every
quality gate green, including the bulk-edit diff-compliance check
against `occurrence_map.yaml`.

## Context

- Spec FRs in scope: **FR-010, FR-011, FR-012, FR-013, FR-015, FR-016**.
- Constraints applied: **C-005, C-006, C-007, C-013, C-014**.
- Doctrine spec section: §"`packages/ai-tools` package layout" and §"Bulk-edit migration plan" in `docs/specs/agent-executor.md`.
- Plan resolutions consumed: R-001 (occurrence_map dispositions) and the ADR commitment in the Engineering Alignment table of `plan.md`.
- Bulk-edit primary symbol: `Waaseyaa\AI\Schema\Mcp\McpToolDefinition → Waaseyaa\AI\Tools\AgentTool`. See `kitty-specs/agent-executor-01KRWPK7/occurrence_map.yaml`.
- All breaking changes are internal-only per **C-006**; T001 verifies this before any deletion lands.

## Branch strategy

Planning + merge target: `main`. Execution lane is allocated by `spec-kitty agent mission finalize-tasks`; the implementer enters the lane via `spec-kitty agent action implement WP01 --agent <name>` and works inside the resulting worktree. The worktree lacks `vendor/`, so the first action inside the worktree is `composer install`.

---

## Subtask T001 — External-consumer verification grep

**Purpose:** Prove **C-006** (no external consumers exist for the symbols being broken) before the deletions in T006, T007, T008 land.

**Steps:**
1. Create executable script `bin/check-external-consumers` (PHP, mirrors `bin/check-package-layers` shape):
   - Accepts a single argument: a key, e.g. `ai-agent-orphans`.
   - For `ai-agent-orphans`, greps the following targets:
     - `Waaseyaa\\AI\\Schema\\Mcp\\McpToolDefinition` (FQCN, also matches imports)
     - `Waaseyaa\\AI\\Agent\\AgentInterface`
     - `packages/mcp/src/Tools/` (path strings outside the moving files themselves)
     - `accessCheck(false)`
   - Search scope: `packages/`, `tests/`, `defaults/`, `bin/`, `public/`, `docs/`, `kitty-specs/`. **Exclude** `packages/ai-tools/**` (the destination), `kitty-specs/agent-executor-01KRWPK7/` (planning artifacts), `docs/specs/agent-executor.md` (doctrine spec naming the symbols), and `packages/ai-schema/src/Mcp/`/`packages/mcp/src/Tools/` (will be rewritten or deleted in this WP).
2. Exit `0` only if zero unexpected hits remain; otherwise print each hit and exit `1`.
3. Add `bin/check-external-consumers ai-agent-orphans` to `composer verify` in the root `composer.json` (alongside the existing layer / dead-code / composer-policy entries).

**Files:**
- `bin/check-external-consumers` (NEW)
- `composer.json` (root) — add to the `verify` script (this file is shared; coordinate via a precise `Edit` that only touches the `scripts` block).

**Validation:**
- [ ] Script exits 0 against current `main`.
- [ ] Running the script against an injected fake consumer (`echo 'McpToolDefinition' > /tmp/probe.php`) — when added to the search root — exits 1. (Smoke test, do not commit the probe file.)

**Edge cases:**
- A grep false positive on a legitimate doctrine spec reference: handled by the explicit exclusion list.

---

## Subtask T002 — Scaffold `packages/ai-tools`

**Purpose:** Create the Layer-5 package shell so subsequent subtasks have a target namespace and autoload root.

**Steps:**
1. Create `packages/ai-tools/composer.json` modelled on `packages/ai-schema/composer.json`:
   - `name: "waaseyaa/ai-tools"`, `type: "library"`.
   - `config.sort-packages: true` (CP-001).
   - `require`: `php: ">=8.5"`, `waaseyaa/foundation: ^<current-tag>`, `waaseyaa/entity: ^<current-tag>`, `waaseyaa/access: ^<current-tag>` (use `bin/sync-internal-versions`-managed literal — read the current tag with `git describe --tags --abbrev=0 --match='v*.*.*'`).
   - `require-dev`: `waaseyaa/testing: ^<current-tag>`, PHPUnit 10.5.
   - `autoload.psr-4`: `"Waaseyaa\\AI\\Tools\\": "src/"`.
   - `autoload-dev.psr-4`: `"Waaseyaa\\AI\\Tools\\Tests\\": "tests/"`.
   - `extra.waaseyaa.providers`: `["Waaseyaa\\AI\\Tools\\AiToolsServiceProvider"]`.
   - `extra.waaseyaa.layer: 5`.
2. Create `packages/ai-tools/src/AiToolsServiceProvider.php` extending `Waaseyaa\Foundation\ServiceProvider`:
   - `register()`: bind `AttributeToolRegistry` as singleton implementing `ToolRegistryInterface` (created in T004).
   - `boot()`: no-op for v1.
3. Add the package to the root composer path repositories list and run `composer update --no-install waaseyaa/ai-tools` to refresh `composer.lock`.
4. Add `packages/ai-tools` to `bin/check-package-layers` declared layer table (Layer 5).
5. Create stub `packages/ai-tools/README.md` (just the title + one-line summary; full content lands in T005 / T006).

**Files:**
- `packages/ai-tools/composer.json` (NEW)
- `packages/ai-tools/src/AiToolsServiceProvider.php` (NEW)
- `packages/ai-tools/README.md` (NEW, stub)
- `composer.json` (root) — add path repository entry
- `composer.lock` — refresh
- `bin/check-package-layers` — register Layer 5 membership

**Validation:**
- [ ] `composer dump-autoload -o` succeeds.
- [ ] `bin/check-package-layers` exits 0.
- [ ] `bin/check-composer-policy` exits 0 for the new manifest (CP-001/CP-002/CP-003/CP-NEW).

---

## Subtask T003 — `AgentTool` VO + interfaces + `#[AsAgentTool]`

**Purpose:** Define the contract the rest of the runtime consumes.

**Steps:**
1. Create `packages/ai-tools/src/AgentToolInterface.php`:
   ```php
   interface AgentToolInterface
   {
       public function execute(array $arguments, AccountInterface $account): AgentToolResult;
       public function dryRun(array $arguments, AccountInterface $account): AgentToolResult;
       public function argumentsForAudit(array $arguments): array;
   }
   ```
   `dryRun()` may delegate to `execute()` when `dryRunSupported` is false; document this in PHPDoc.
2. Create `packages/ai-tools/src/AgentTool.php` as a `final readonly class` matching the data-model fields (`name`, `capability`, `destructive`, `dryRunSupported`, `category`, `inputSchema`, `impl`). Constructor uses named parameters.
3. Create `packages/ai-tools/src/AgentToolResult.php` as a `final readonly class` (`isError: bool`, `content: array`, `summary: ?string`). Constructor + named static `success(array $content, ?string $summary = null)` and `error(string $message, ?string $summary = null)` factories.
4. Create `packages/ai-tools/src/AbstractAgentTool.php`:
   - Implements `AgentToolInterface`.
   - Provides default `argumentsForAudit()` (redacts keys named `password`, `token`, `api_key`).
   - Provides default `dryRun()` that returns `AgentToolResult::error('dry_run_not_supported')`.
5. Create `packages/ai-tools/src/Attribute/AsAgentTool.php` (PHP `#[Attribute(Attribute::TARGET_CLASS)]`):
   ```php
   final class AsAgentTool {
       public function __construct(
           public string $name,
           public string $capability,
           public bool $destructive = false,
           public bool $dryRunSupported = false,
           public string $category = 'general',
       ) {}
   }
   ```
6. Add a class-level `@api` PHPDoc on `AgentToolInterface`, `AgentTool`, `AgentToolResult`, `AbstractAgentTool`, and `AsAgentTool` so the dead-code gate treats them as extension points.

**Files:**
- `packages/ai-tools/src/AgentTool.php`
- `packages/ai-tools/src/AgentToolInterface.php`
- `packages/ai-tools/src/AgentToolResult.php`
- `packages/ai-tools/src/AbstractAgentTool.php`
- `packages/ai-tools/src/Attribute/AsAgentTool.php`
- `packages/ai-tools/tests/Unit/AgentToolTest.php` — construction + named-arg ergonomics
- `packages/ai-tools/tests/Contract/AgentToolInterfaceContractTest.php` — abstract contract that concrete tests extend

**Validation:**
- [ ] Unit tests cover VO equality and `AgentToolResult::error/success` factories.
- [ ] PHPStan level 5 passes.
- [ ] Dead-code gate sees the public extension points as used (`@api`).

---

## Subtask T004 — `AttributeToolRegistry` + manifest-compiler discovery

**Purpose:** Build the runtime registry that `AgentExecutor` and `McpController` will both consume.

**Steps:**
1. Create `packages/ai-tools/src/ToolRegistryInterface.php` with `register(AgentTool $tool): void`, `get(string $name): AgentTool`, `all(): iterable<AgentTool>`, `has(string $name): bool`.
2. Create `packages/ai-tools/src/Catalogue/AttributeToolRegistry.php`:
   - Constructor takes `PackageManifestCompiler $manifest`, `ServiceContainerInterface $container`, `?LoggerInterface $logger = null`.
   - On first `all()` / `get()`, the registry lazily walks the manifest's `agent_tools` section (populated by the compiler — see step 3) and instantiates each tool class via `$container->get()`.
   - Wraps each in an `AgentTool` VO using the attribute payload.
3. Extend `Waaseyaa\Foundation\Package\PackageManifestCompiler` to scan for `#[AsAgentTool]` (use the **string FQCN** `'Waaseyaa\\AI\\Tools\\Attribute\\AsAgentTool'` to keep Foundation Layer-0 clean per the constitution gotcha). Emit a new manifest section:
   ```php
   'agent_tools' => [
       ['class' => '...', 'name' => '...', 'capability' => '...', ...],
       ...
   ]
   ```
4. Add unit tests using a temporary fixture tool class.
5. Add an `optimize:manifest` regeneration step to the WP's verification checklist (T009).

**Files:**
- `packages/ai-tools/src/ToolRegistryInterface.php`
- `packages/ai-tools/src/Catalogue/AttributeToolRegistry.php`
- `packages/foundation/src/Package/PackageManifestCompiler.php` — extend (this file is **owned** by foundation; this is a minimal targeted edit recording the new manifest section. Coordinate with foundation maintainer if conflicts arise. Foundation's owned_files for this WP is via the narrow path noted at top.)
- `packages/ai-tools/tests/Unit/Catalogue/AttributeToolRegistryTest.php`

**Validation:**
- [ ] `bin/waaseyaa optimize:manifest` produces an `agent_tools` array.
- [ ] Registry's `all()` returns the eight stock tools (after T005 lands).

> **Note on PackageManifestCompiler edits:** The foundation file is touched here because no other WP owns it. Keep the diff minimal — add the new `agent_tools` collector beside existing collectors (middleware, providers). Do not refactor other collectors in this WP.

---

## Subtask T005 — Eight stock tool implementations  `[P]`

**Purpose:** Ship the framework's stock tool catalogue, replacing the four MCP-side classes.

**Steps:** Implement each tool below as a `final class … extends AbstractAgentTool` with the `#[AsAgentTool]` attribute. Each class lives in the directory tree noted in `plan.md` § "Project Structure". All tools enforce entity-level access against the passed `AccountInterface`.

| Class | `name` | `capability` | `destructive` | `category` | Behaviour |
|---|---|---|---|---|---|
| `EntityReadTool` | `entity.read` | `tool.entity.read` | false | `entity` | Loads a single entity by type + id via `EntityRepository`; respects field-level access policy. |
| `EntityListTool` | `entity.list` | `tool.entity.list` | false | `entity` | Lists entities of a given type with filter / sort / limit. |
| `EntityCreateTool` | `entity.create` | `tool.entity.create` | **true** | `entity` | Creates entities via `EntityRepository::save()` with `enforceIsNew()`. |
| `EntityUpdateTool` | `entity.update` | `tool.entity.update` | **true** | `entity` | Loads + mutates + saves via `EntityRepository`. |
| `EntityDeleteTool` | `entity.delete` | `tool.entity.delete` | **true** | `entity` | Hard-delete via `EntityRepository::delete()`. |
| `EntitySearchTool` | `entity.search` | `tool.entity.search` | false | `entity` | Full-text search (uses `packages/search` if available; otherwise LIKE). |
| `RelationshipTraverseTool` | `relationship.traverse` | `tool.relationship.traverse` | false | `relationship` | Graph traversal via `packages/relationship`. |
| `VectorSearchTool` | `vector.search` | `tool.vector.search` | false | `vector` | Semantic search via `VectorStoreInterface` + `EmbeddingProviderInterface` from `packages/ai-vector`. |

**Input schemas** SHALL be JSON Schema draft 2020-12, declared inline in each class via a static method `inputSchema(): array` referenced from the `#[AsAgentTool]` attribute. Each tool unit test verifies:
- Required argument validation (returns `AgentToolResult::error` for missing fields).
- Access enforcement (returns `error` when the account lacks the tool capability).
- Audit-redaction of any password / token argument keys.

**Files (per tool):**
- `packages/ai-tools/src/{Entity,Relationship,Vector}/<Class>.php`
- `packages/ai-tools/tests/Unit/{Entity,Relationship,Vector}/<Class>Test.php`

**Validation:**
- [ ] All eight tools appear in `AttributeToolRegistry::all()`.
- [ ] Destructive tools have `destructive = true`.
- [ ] Contract suite (see T003) passes for every tool.

---

## Subtask T006 — Delete `packages/mcp/src/Tools/*` and rewire `McpController`

**Purpose:** Eliminate the duplicate tool implementations and have `McpController` serve `tools/list` and `tools/call` from the new registry.

**Steps:**
1. Delete `packages/mcp/src/Tools/EntityTools.php`, `DiscoveryTools.php`, `TraversalTools.php`, `EditorialTools.php`.
2. Update `packages/mcp/src/McpController.php`:
   - Inject `ToolRegistryInterface` (from `packages/ai-tools`).
   - `tools/list`: serialize every `AgentTool` to MCP's `Tool` shape (name, description, inputSchema).
   - `tools/call`: look up the `AgentTool`, invoke its `impl->execute($arguments, $this->account())` where `$account()` reads `_account` from the current request.
3. Update `packages/mcp/composer.json`:
   - Add `waaseyaa/ai-tools: ^<current-tag>`.
   - Remove any now-unused `require` entries.
4. Create acceptance test `tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php`:
   - Boots the kernel.
   - Calls the MCP `/mcp` endpoint with `tools/list`.
   - Asserts the response includes the eight new tools by name.
   - Calls `tools/call` for `entity.list` with a seeded admin account; asserts the response includes the seeded entity.

**Files:**
- `packages/mcp/src/Tools/EntityTools.php` (DELETE)
- `packages/mcp/src/Tools/DiscoveryTools.php` (DELETE)
- `packages/mcp/src/Tools/TraversalTools.php` (DELETE)
- `packages/mcp/src/Tools/EditorialTools.php` (DELETE)
- `packages/mcp/src/McpController.php` (REWIRE)
- `packages/mcp/composer.json`
- `tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php` (NEW)

**Validation:**
- [ ] `McpControllerToolsSharingTest` passes.
- [ ] `bin/check-package-layers` exits 0 (`mcp` still at Layer 6, `ai-tools` at Layer 5).
- [ ] No symbol from the deleted classes remains reachable.

---

## Subtask T007 — Update `ai-schema/Mcp/*` generators; remove `McpToolDefinition`

**Purpose:** Replace `Waaseyaa\AI\Schema\Mcp\McpToolDefinition` with `Waaseyaa\AI\Tools\AgentTool` across `ai-schema`. Generators that emit tool descriptors must now consume `AgentTool`.

**Steps:**
1. Identify every generator in `packages/ai-schema/src/Mcp/` that consumes `McpToolDefinition` (grep within the package).
2. For each generator, change parameter and return types to `AgentTool` (from `Waaseyaa\AI\Tools\AgentTool`).
3. Delete `packages/ai-schema/src/Mcp/McpToolDefinition.php`.
4. Update `packages/ai-schema/composer.json` to require `waaseyaa/ai-tools`.
5. Update or remove tests under `packages/ai-schema/tests/` that referenced `McpToolDefinition`.

**Files:**
- `packages/ai-schema/src/Mcp/*.php`
- `packages/ai-schema/composer.json`
- `packages/ai-schema/tests/**`

**Validation:**
- [ ] No remaining references to `McpToolDefinition` after this subtask.
- [ ] `composer phpstan` passes for `packages/ai-schema`.

---

## Subtask T008 — Remove `accessCheck(false)`; file ADR

**Purpose:** Strengthen security: entity-touching tool calls SHALL enforce entity-level access against the initiator's account.

**Steps:**
1. Open `packages/mcp/src/McpToolExecutor.php`. Locate every `accessCheck(false)` invocation. Replace with the default (`true`) or delete the override.
2. Verify every tool-call path now passes `$account` from `_account` (the MCP `McpController` already reads it).
3. Add `tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php::testUnauthorizedAccountIsRejected` — seed an account lacking the tool capability; call `entity.create`; expect `403`-shaped error in the MCP response.
4. File `docs/adr/0XX-mcp-tool-access-enforcement.md`:
   - Title: "MCP tool access enforcement against initiator account"
   - Context: explain the prior bypass and why it existed (legacy McpServer orphan, since deleted in PR #1508).
   - Decision: every tool call enforces entity-level access against the bearer-token / session account; `accessCheck(false)` is removed.
   - Consequences: external MCP clients that depended on the bypass are intentionally broken; this is the intended posture per C-013.
   - Allocate the next available ADR number — read `docs/adr/` directory and pick `max(N) + 1`.

**Files:**
- `packages/mcp/src/McpToolExecutor.php` (EDIT)
- `tests/Integration/PhaseN/AgentRuntime/McpControllerToolsSharingTest.php` (EXTEND)
- `docs/adr/0XX-mcp-tool-access-enforcement.md` (NEW; rename to actual number)

**Validation:**
- [ ] Unauthorized-account test fails closed (returns error).
- [ ] ADR is filed under the next sequential number.

---

## Subtask T009 — Gate verification + bulk-edit diff-compliance

**Purpose:** Prove every framework gate passes before the WP merges.

**Steps:**
1. Run `bin/waaseyaa optimize:manifest` to regenerate the package manifest (catches the new `agent_tools` section).
2. Run the full gate suite:
   - `composer cs-check`
   - `composer phpstan`
   - `composer test`
   - `bin/check-package-layers`
   - `bin/check-dead-code`
   - `bin/check-composer-policy`
   - `bin/check-external-consumers ai-agent-orphans` (introduced in T001)
3. Run the bulk-edit diff-compliance pass against `kitty-specs/agent-executor-01KRWPK7/occurrence_map.yaml`:
   - `spec-kitty agent mission bulk-edit-check --mission agent-executor-01KRWPK7`
   - Expect zero `BLOCK` rows.
4. If `bin/check-dead-code` reports new findings:
   - Every finding must be a legitimate `@api` extension point or attribute-discovered class.
   - Regenerate the baseline only if every finding is justified (`vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon`).
   - Add a short rationale to the PR description for any baseline diff.

**Validation:**
- [ ] All gates exit 0.
- [ ] Bulk-edit diff-compliance: zero `BLOCK` rows.

---

## Definition of Done

- [ ] All nine subtasks T001..T009 complete (checkboxes in `tasks.md` flipped).
- [ ] Quality gates green: cs-check, phpstan, test, layers, dead-code, composer-policy, external-consumers, bulk-edit.
- [ ] `packages/ai-tools` published as a sibling waaseyaa/* package (resolves locally via path repo; release-cut.yml's `bin/sync-internal-versions` will pick it up automatically at next tag).
- [ ] `packages/mcp/src/Tools/*` is gone; `McpController` serves tools from `AttributeToolRegistry`.
- [ ] `McpToolDefinition` is gone; generators consume `AgentTool`.
- [ ] `accessCheck(false)` is gone; ADR filed.
- [ ] Three new tests pass: tool unit suite, manifest registry test, `McpControllerToolsSharingTest`.

## Risks & mitigations

1. **Hidden consumer of `McpToolDefinition`.** T001 enumerates; abort the WP if anything appears in external sample apps before deletion.
2. **Dead-code regressions.** Use `@api` on extension points; regenerate baseline only after audit per `CLAUDE.md` § "Marking intentional scaffolding".
3. **Layer regression** if anything in `ai-tools` accidentally imports from `packages/mcp` or higher. *Mitigation:* `bin/check-package-layers` and the targeted test in T006.
4. **Manifest compiler edits at Layer 0** could ripple. *Mitigation:* keep the diff minimal — add a single new collector beside existing collectors; do not refactor.

## Reviewer guidance

- Verify the **bulk-edit diff** against `occurrence_map.yaml` first — this is the WP's load-bearing artifact.
- Spot-check each of the eight tool classes for: `extends AbstractAgentTool`, `#[AsAgentTool]` attribute present, capability matches the table in T005, `destructive` flag correct.
- Confirm `McpController` route shape (`tools/list`, `tools/call`) is byte-identical to the prior contract — only the implementation should change.
- Confirm the ADR allocates the next sequential number under `docs/adr/`.
- Sanity-check `bin/check-dead-code` baseline diff: every new entry must be `@api` or attribute-discovered.

## Implementation command

```
spec-kitty agent action implement WP01 --agent <name>
```
