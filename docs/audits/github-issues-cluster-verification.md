# GitHub open issues — cluster verification (main)

**Date:** 2026-04-26  
**Repo:** `waaseyaa/framework`  
**Companion:** [github-issues-triage-enriched.md](github-issues-triage-enriched.md), [github-issues-triage-enriched.csv](github-issues-triage-enriched.csv)

This document records **code-backed** verification for high-signal clusters. Re-run checks after substantive merges.

## `refactor_extract_interfaces` (#1239–#1243)

| Issue | Verdict | Evidence |
|-------|---------|----------|
| #1239 Core Data | **Not done** | No `AccessCheckerInterface`, `AuthManagerInterface`, or `TwoFactorManagerInterface` in `packages/access` / `packages/auth` (grep). |
| #1240 Services | **Not done** | No `TransitionAccessResolverInterface`, `EditorialWorkflowServiceInterface`, `SitemapGeneratorInterface`, etc. under proposed names in packages. |
| #1241 API | **Not done** | No `RouteProviderInterface` / `ResourceSerializerInterface` / `WaaseyaaRouter`-level `RouterInterface` extractions matching AC. |
| #1242 AI | **Not done** | `AgentExecutorInterface`, `EmbeddingPipelineInterface`, `PipelineExecutorInterface` not present; `SchemaRegistryInterface` exists in **foundation** schema package, not as substitute for `Waaseyaa\AI\Schema\SchemaRegistry` — do not conflate. |
| #1243 Interface layer | **Not done** | No `CommandRegistryInterface`, `GraphQlEndpointInterface`, `ComponentRegistryInterface`, `FieldFormatterRegistryInterface`, `PageHandlerInterface` in named packages. |

**Next step:** Implement per issue or spawn one Spec Kitty WP per layer; keep issues open until interfaces + bindings land.

## `#1339` — L4 bimaaji + phpstan + CodifiedContext coupling

| AC item | Verdict | Evidence |
|---------|---------|----------|
| CLAUDE L4 lists `bimaaji` | **Done** | [CLAUDE.md](../../CLAUDE.md) Layer 4 row: `api, bimaaji, routing`. |
| `phpstan.neon` includes bimaaji | **Done** | `phpstan.neon` lists `packages/bimaaji/src`. |
| PHPStan clean for bimaaji | **Done** | `composer phpstan` → **No errors** (2026-04-26). |
| L4→L6 static import | **Done** | [CodifiedContextController.php](../../packages/api/src/Controller/CodifiedContextController.php) uses `CodifiedContextSessionStoreInterface` only; no `use Waaseyaa\Telescope\…`. |

**Action:** Closed on GitHub as **completed** with comment pointing at `main` and PR **#1349** (telemetry / codified-context alignment).

## `#1296` — waaseyaa/groups extraction

| Verdict | Evidence |
|---------|----------|
| **Done** | [PR #1297](https://github.com/waaseyaa/framework/pull/1297) merged: same scope as issue title (`feat: waaseyaa/groups extraction + per-bundle field activation`). Package present at `packages/groups/`. |

**Action:** Closed on GitHub as **completed** with link to PR **#1297**.

## `#1313` — Shadow-collision guard + duplicate-registration DX

| Ask | Verdict |
|-----|---------|
| A. Notice when subtable missing | **Partial** | [SqlEntityStorage.php](../../packages/entity-storage/src/SqlEntityStorage.php) logs structured context for missing bundle subtable at save; confirm parity with `load()` path vs issue wording. |
| B. Duplicate registration message | **Verify** | Search `EntityTypeManager` / `PackageManifestCompiler` collision paths for dual-registrant wording before closing. |

**Action:** Leave **open**; optional follow-up issue split if A/B tracked separately.

## Remediation / audit / PHPDoc clusters

No mass closure: each `impl(remediation)(M*)` and `audit(...)` ticket needs **body AC** vs current `docs/audits/` and code. Use enriched CSV `merged_prs` column to prioritize issues that already have merged cross-refs, then re-read AC checkboxes.

## Sprint slice (Track 4)

See label **`sprint-candidate`** on milestone **Track 4 — Schema evolution** issues (small, schema-focused batch). Filter:

`is:open milestone:"Track 4 — Schema evolution" label:sprint-candidate`
