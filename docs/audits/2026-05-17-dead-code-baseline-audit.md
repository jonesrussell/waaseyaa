# Dead-Code Baseline Audit — 2026-05-17

**Scope:** Audit of `phpstan-dead-code-baseline.neon` (1,341 grandfathered findings across 60 packages) ahead of beta. No code changes — this report exists to scope the work, not perform it.

**Baseline file at time of audit:** `phpstan-dead-code-baseline.neon` (259 KB, 8,047 lines, modified 2026-05-12).

**Reproducer:** `bin/audit-dead-code` (warn-only CI job). Triage scripts used to generate this report are not yet checked in; see *Reproducibility* below.

## TL;DR

Of 1,341 baselined findings, roughly **two thirds are false positives** caused by detection gaps, not real dead code:

| Category | Count | Recommended action |
|---|---:|---|
| **A.** Entity-class members in `EntityBase`/`ContentEntityBase` subclasses (reflection-hydrated) | 178 | **T1 — detection rule.** Extend `WaaseyaaEntrypointProvider` to mark all `EntityBase`/`ContentEntityBase` subclasses as used. |
| **B.** Extension points in `*Interface.php`, `*Provider.php`, `Attribute/`, `Contract/`, `Event/`, `Plugin/`, `Policy/` paths | 218 | **T2 — `@api` sweep.** Mass-annotate classes; rely on shipmonk's `ApiPhpDocUsageProvider` rather than baselining. |
| **C.** Test fixtures in `tests/` and `testing/` | 38 | **Keep.** Intentional stubs (e.g. `StubEntityTypeManager::*` throw `BadMethodCallException`). |
| **D.** Real review surface (non-entity, non-extension `src/`) | 907 | **T2/T3.** Subdivides further — see §3. |

After T1 + T2 the baseline should drop from **1,341 → ~360 entries**, and what remains will be a meaningful list of genuinely unused or under-wired code that can be deleted or shipped. That is the real beta-readiness work.

The CI gate stays warn-only throughout — no risk of breaking the build.

---

## 1. Why the baseline is this size

Three causes, ranked by impact:

### 1a. Reflection hydration is invisible to AST analysis

`SqlEntityStorage::mapRowToEntity()` populates entity properties via `ReflectionProperty::setValue()`. Setters like `OidcClient::setClientId` are invoked through `ContentEntityBase::set()` (string-keyed field magic), not via direct method calls. Both are call-graph-invisible.

Affected files include every `*EntityBase`/`*ContentEntityBase` subclass — confirmed in:

- `packages/node/src/Node.php` (22)
- `packages/oidc/src/Entity/OidcClient.php` (15)
- `packages/taxonomy/src/Term.php` (14)
- `packages/user/src/User.php` (13)
- `packages/menu/src/MenuLink.php` (12)
- `packages/media/src/Media.php` (8)
- `packages/path/src/PathAlias.php` (8)
- 16 more entity classes

**178 entries total.** `WaaseyaaEntrypointProvider` already covers policies, middleware, providers, ingestion mappers, and route providers — but **not entities**. This is the single highest-leverage detection-rule fix.

### 1b. Extension-point classes annotated implicitly

Interfaces, providers, attributes, contracts, events, policies — these are designed to be implemented or extended by consumers, not called internally. The CLAUDE.md "dead code audits" section explicitly tells contributors to add `@api`, but pre-existing classes were never swept.

**218 entries in extension-point file/dir patterns.** Mass `@api` annotation eliminates these without per-class judgment.

### 1c. Public API surfaces never used by framework's own internals

The framework provides classes for *consumers* to use. Examples confirmed in sampling:

- `Foundation\Result\Result` — `isOk`, `isFail`, `map`, `mapError`, `unwrap`, `unwrapOr`, `error`
- `Foundation\Http\Inbound\InboundHttpRequest` — `body`, `header`, `method`, `path`, `query`, `queryParam`, `routeParam`, `routeParams`, `cookie`, `rawContent`, `fromSymfonyRequest`
- `Foundation\Migration\TableBuilder` — `float`, `integer`, `index`, `primary`, `unique`, `entityBase`, `revisionColumns`, `translationColumns`
- `Foundation\Kernel\AbstractKernel` — `getEventDispatcher`, `getEntityAuditLogger`, `getLifecycleManager`, `applyDiscoveryExtensionContext`, etc.
- `CLI\Testing\CliTester` — `for`, `getOutput`, `getStdout`, `getStderr`, `getExitCode`, `executeMap`

These belong in T2 alongside extension points. `@api` is the right answer for all of them.

---

## 2. Per-package breakdown

Top 15 packages by total dead-code entries:

| Package | A (entity) | B (ext) | C (test) | D (real review) | Total |
|---|---:|---:|---:|---:|---:|
| foundation | 1 | 41 | 0 | 131 | 173 |
| cli | 6 | 1 | 0 | 84 | 91 |
| entity | 27 | 46 | 0 | 9 | 82 |
| field | 25 | 11 | 0 | 31 | 67 |
| telescope | 1 | 13 | 0 | 32 | 46 |
| config | 23 | 10 | 0 | 12 | 45 |
| queue | 18 | 4 | 0 | 17 | 39 |
| testing | 0 | 0 | 38 | 0 | 38 |
| typed-data | 6 | 11 | 0 | 19 | 36 |
| cache | 4 | 6 | 0 | 23 | 33 |
| entity-storage | 16 | 1 | 0 | 13 | 30 |
| media | 25 | 4 | 0 | 0 | 29 |
| node | 28 | 0 | 0 | 0 | 28 |
| oauth-provider | 15 | 12 | 0 | 0 | 27 |
| ai-observability | 0 | 4 | 0 | 22 | 26 |

**Reading:** packages dominated by column A (`node`, `media`, `oauth-provider`, `config`) drop to near-zero once T1 lands. Packages dominated by column D (`foundation`, `cli`, `telescope`, `cache`, `ai-observability`) need per-file review.

---

## 3. The 907 "real review" entries

After excluding entity classes (178), extension points (218), and test fixtures (38), the remaining 907 entries are the surface that requires per-file judgment.

Top 20 files by entry count:

| Count | File | Smell |
|---:|---|---|
| 15 | `scheduler/src/ScheduleBuilder.php` | Builder DSL — likely public API → `@api` |
| 11 | `foundation/src/Http/Inbound/InboundHttpRequest.php` | Public HTTP API → `@api` |
| 10 | `search/src/SearchHit.php` | DTO, public properties read via serialization → `@api` |
| 8 | `entity-storage/src/EntityRepository.php` | Public repository API → `@api` |
| 8 | `foundation/src/Migration/TableBuilder.php` | Public migration DSL → `@api` |
| 8 | `workflows/src/Workflow.php` | Public workflow API → `@api` |
| 7 | `ai-agent/src/AgentAuditLog.php` | Audit-log DTO → `@api` or detection rule |
| 7 | `field/src/FieldDefinition.php` | Field config DTO → `@api` |
| 7 | `field/src/FieldItemList.php` | Field iteration API → `@api` |
| 7 | `field/src/Form/FormFieldDescriptor.php` | Form descriptor DTO → `@api` |
| 7 | `foundation/src/Broadcasting/SseBroadcaster.php` | **Real candidate** — broadcaster with no internal callers, no `@api` |
| 7 | `foundation/src/Kernel/AbstractKernel.php` | Public kernel surface → `@api` |
| 7 | `foundation/src/Result/Result.php` | Public Result monad → `@api` |
| 7 | `foundation/src/Schema/Compiler/Sqlite/SqliteCapabilityMatrix.php` | Schema-driver API → `@api` |
| 7 | `github/src/GitHubClient.php` | External-service client → `@api` |
| 7 | `github/src/Issue.php` | DTO → `@api` |
| 7 | `queue/src/BatchedJobs.php` | Public job API → `@api` |
| 7 | `search/src/SearchResult.php` | DTO → `@api` |
| 6 | `admin-surface/src/Catalog/FieldDefinition.php` | Public catalog API → `@api` |
| 6 | `ai-agent/src/AgentExecutor.php` | **Possible candidate** — review per-method |

**Of the top 20, only 2 look like real dead-code candidates** (`SseBroadcaster`, `AgentExecutor`). The other 18 are public API that the framework's own code happens not to consume.

This confirms the audit pattern: **the dead-code baseline is mostly a documentation gap, not a code-quality problem.** The genuinely abandoned code is a much smaller subset.

---

## 4. Recommended work plan

### Phase 1 — T1 detection rule fix (≤ 2 hours, single PR)

**Goal:** Extend `tools/phpstan/WaaseyaaEntrypointProvider.php` to recognize `EntityBase`/`ContentEntityBase` subclasses. Regenerate `phpstan-dead-code-baseline.neon`. Expected reduction: **1,341 → ~1,163** entries.

**Files touched:**
- `tools/phpstan/WaaseyaaEntrypointProvider.php` — add `isEntitySubclass()` check by walking `getParentClass()` chain.
- `phpstan-dead-code-baseline.neon` — regenerate with `vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon`.

**Risk:** Very low. Entity classes are hydrated by reflection so all members are legitimately "used"; the rule just teaches the detector this.

**Verification:** Baseline line count drops by ~1,200 lines. Manually confirm 5 entity files (`Node.php`, `OidcClient.php`, `Term.php`, `User.php`, `MenuLink.php`) disappear entirely from baseline.

### Phase 2 — T2 `@api` mass-annotation sweep (≤ 1 day, batched PRs by package)

**Goal:** Annotate the 218 extension-point classes plus the ~600 public-API classes from the "real review" bucket. Regenerate baseline. Expected reduction: **~1,163 → ~360** entries.

**Approach (per package):**
1. List all classes with dead-code entries in that package.
2. For each, decide: public API → `@api` PHPDoc on class; truly private → skip.
3. Don't add `@api` to internal helpers — let those surface in Phase 3.

**Files touched (representative):**
- `packages/foundation/src/Result/Result.php`, `packages/foundation/src/Http/Inbound/InboundHttpRequest.php`, `packages/foundation/src/Kernel/AbstractKernel.php`, `packages/foundation/src/Migration/TableBuilder.php`
- `packages/scheduler/src/ScheduleBuilder.php`
- `packages/workflows/src/Workflow.php`
- `packages/entity-storage/src/EntityRepository.php`
- `packages/field/src/FieldDefinition.php`, `FieldItemList.php`, `Form/FormFieldDescriptor.php`
- `packages/search/src/SearchHit.php`, `SearchResult.php`
- All `*Interface.php`, `*Provider.php`, files under `*/Attribute/`, `*/Contract/`, `*/Event/`, `*/Plugin/`
- ~60 more files across 30 packages

**Risk:** Very low. `@api` is a PHPDoc-only signal — no runtime effect, no behavioural change.

**Verification:** After each package PR, regenerate baseline locally; confirm the count drops by roughly the expected amount.

### Phase 3 — T3 per-file review (1–2 weeks, scoped sub-missions)

**Goal:** For the remaining ~360 entries, decide per-class: **ship**, **delete**, or **`@api` and document**. Expected outcome: baseline at zero, or replaced by a much smaller intentional list with `// reason:` comments inline.

**Candidates already identified in §3:**
- `foundation/src/Broadcasting/SseBroadcaster.php` — has 7 dead methods including `broadcast`, `clearLog`, `subscriberCount`. Either wire it up via a `BroadcastingExtensionPoint` or delete the SSE broadcasting scaffold.
- `ai-agent/src/AgentExecutor.php` — 6 dead methods. Likely orphaned from an earlier agent design.
- The 3 confirmed "not yet implemented" stubs from the initial scan:
  - `entity-storage/src/SqlEntityQuery.php` — "access checking is not implemented yet" (v0.1.0 — beta blocker?)
  - `entity-storage/src/RevisionPruner.php` — "active pruning logic is not yet implemented (scaffold)"
  - `entity-storage/src/Backend/ReservedBackendIds.php` — "Reserved for future use; not yet implemented"

**Approach:** One sub-mission per package or related-package cluster. Each sub-mission produces either:
1. A deletion PR (with grep-verified zero callers).
2. A wire-up PR (the feature gets implemented and tested).
3. An `@api`-with-rationale PR (the symbol is intentional public surface).

**Risk:** Medium — some symbols may have non-PHP callers (admin SPA via JSON API, ingestion harvesters, etc.). Each deletion needs a grep across `packages/admin/`, `docs/specs/`, and `kitty-specs/` for indirect references.

### Phase 4 — Optional: flip gate from warn to fail

Once Phase 3 lands and the baseline is at zero (or a very small intentional list), update `bin/audit-dead-code` and CI to **fail** on any new finding rather than warn. This is the actual beta-readiness payoff: future PRs can't add dead code.

**Recommend deferring this** until after Phase 3 to avoid blocking unrelated work during cleanup.

---

## 5. Reproducibility

The classification numbers in this report were produced by parsing `phpstan-dead-code-baseline.neon` directly. To regenerate during Phase 1/2 verification:

```bash
# Regenerate baseline
vendor/bin/phpstan analyse -c phpstan-dead-code.neon --generate-baseline=phpstan-dead-code-baseline.neon

# Count entries
grep -c '^            message:' phpstan-dead-code-baseline.neon

# Count unique paths
grep '^            path:' phpstan-dead-code-baseline.neon | sort -u | wc -l
```

The Python triage scripts that generated §1–3 are not checked in; they ran ad-hoc against `/tmp/`. If we want them as part of `bin/audit-dead-code` output, that is a small (≤ 1 hour) follow-up.

---

## 6. Decisions needed from owner before any code change

1. **Confirm Phase 1 scope.** Adding entity-class auto-marking will mask any genuinely unused entity-class methods (e.g. dead setters from an early experiment). Risk is small but real — accept that trade?
2. **Phase 2 PR cadence.** One big sweep or per-package PRs? Per-package is easier to review but adds 30+ PRs.
3. **Phase 3 sub-mission ownership.** Spec-kitty mission per package, or one rolling cleanup mission?
4. **The three "not yet implemented" stubs** — these were flagged separately in the initial repo-hygiene scan. Beta-block on these (ship-it), or `@api`-annotate and defer to v1.1?
5. **Adjacent hygiene items not in this audit's scope** — see *Out of scope* below — should any of these be folded into the dead-code cleanup, or tracked as a separate audit?

## Out of scope

This audit covers **only** the dead-code baseline. The wider repo-hygiene scan that preceded this audit also flagged:

- `phpstan-baseline.neon` — 1,735 lines, 250+ entries (mix of nuisance and real bug signals).
- 18 `markTestSkipped`/`markTestIncomplete` calls.
- `analytics` and `deployer` packages have source but no tests.
- 13 files over 600 lines in the entity-storage / foundation hot path.
- 4 require-dev upward layer warnings.

If you want these audited next, say the word.

---

*Audit performed 2026-05-17. Classification scripts ran against `phpstan-dead-code-baseline.neon` last modified 2026-05-12. Re-run before acting if the baseline has been regenerated since.*
