# Waaseyaa

## Project Structure
- Monorepo: 62 PHP packages in `packages/`, 3 meta-packages (core, cms, full), 1 JS admin SPA (`packages/admin/`, no composer.json)
- 7-layer architecture (Foundation → Core Data → Content Types → Services → API → AI → Interfaces)
- Each package has its own `composer.json` with path repository references
- Root `composer.json` uses `self.version` for all waaseyaa/* siblings — it is published to Packagist as `waaseyaa/framework`, so consumers receive the manifest verbatim. `self.version` resolves to `dev-main` locally (path repos) and to the exact tag version when crawled by Packagist (e.g. `0.1.0-alpha.170`), giving consumers exact-matching siblings without a release-time rewrite step. (#1382)
- Composer policy is codified and gated via `bin/check-composer-policy`:
  - `config.sort-packages` must be `true` in all first-party `composer.json` files
  - `@dev` is forbidden everywhere (CP002) — published artifacts cannot resolve it
  - `self.version` is allowed only in root `composer.json` (CP006) — sibling metapackage shape
  - wildcard internal constraints for `waaseyaa/*` are forbidden (CP003)
- Authorization pipeline in `public/index.php`: SessionMiddleware → AuthorizationMiddleware. Session always sets `_account` on request; authorization reads it.
- Route access control via route options: `_public`, `_authenticated`, `_session`, `_permission`, `_role`, `_gate` — checked by `AccessChecker`
- Field-level access: `FieldAccessPolicyInterface` (companion to `AccessPolicyInterface`). Classes must implement both — `EntityAccessHandler` finds field policies via `instanceof` check. Open-by-default: Neutral = accessible, only Forbidden restricts.
- Access result semantics differ by level: entity-level uses `isAllowed()` (deny unless granted), field-level uses `!isForbidden()` (allow unless denied). This asymmetry is intentional.

## Orchestration

When working on files matching these patterns, retrieve the spec for deep context. **Orchestration skills are not Skill-tool skills**: The `waaseyaa:*` entries below are conceptual routing hints — read the listed `docs/specs/*.md` files (Read tool or `rg` in `docs/specs/`), not the `Skill` tool, unless you explicitly load a specialist skill.

| File pattern | Specialist skill | Cold memory spec |
|---|---|---|
| `packages/entity/*`, `packages/entity-storage/*`, `packages/field/*`, `packages/config/*` | `waaseyaa:entity-system` | `docs/specs/entity-system.md` |
| `packages/access/*`, `packages/user/src/Middleware/*` | `waaseyaa:access-control` | `docs/specs/access-control.md`, `docs/specs/field-access.md` |
| `packages/auth/*` | `waaseyaa:access-control` | `docs/specs/access-control.md` |
| `packages/api/*`, `packages/routing/*` | `waaseyaa:api-layer` | `docs/specs/api-layer.md`, `docs/specs/jsonapi.md` (cast-aware attributes) |
| `packages/attachment/*`, `packages/structured-import/*`, `packages/field/src/Form/*`, `packages/field/src/Attribute/BundleTemplate.php`, `packages/field/src/Attribute/FieldTemplate.php`, `packages/field/src/BundleTemplateCompiler.php`, `packages/routing/src/EntityDeepLinkRouteBuilder.php`, `packages/api/src/Controller/FieldAutoSaveController.php` | — | `docs/specs/work-surface.md` |
| `packages/admin/*` | `waaseyaa:admin-spa` | `docs/specs/admin-spa.md` |
| `packages/ai-*/*` | `waaseyaa:ai-integration` | `docs/specs/ai-integration.md`, `docs/specs/authoring-assist-contract.md`, `docs/specs/semantic-refresh-trigger-contract.md` |
| `packages/foundation/src/Ingestion/*`, `defaults/ingestion.*` | `waaseyaa:ingestion` | `docs/specs/ingestion-defaults.md`, `docs/specs/ingestion-validator-contract.md`, `docs/specs/ingestion-validation-gates-contract.md`, `docs/specs/ingestion-fixture-pack-contract.md`, `docs/specs/ingestion-editorial-dashboard-contract.md`, `docs/specs/source-adapter-contract.md`, `docs/specs/source-connectors-contract.md`, `docs/specs/source-priority-merge-contract.md`, `docs/specs/cross-source-identity-contract.md` |
| `packages/ingestion/*` | `waaseyaa:ingestion` | `docs/specs/ingestion-defaults.md` |
| `defaults/*`, `bin/check-no-secrets`, `bin/check-ingestion-defaults` | `waaseyaa:security-defaults` | `docs/specs/security-defaults.md` |
| `packages/foundation/src/Diagnostic/*`, `packages/cli/src/Command/Health*`, `packages/cli/src/Command/SchemaCheck*` | `waaseyaa:operator-diagnostics` | `docs/specs/operator-diagnostics.md`, `docs/specs/operations-playbooks.md` |
| `packages/cli/src/CliKernel.php`, `packages/cli/src/CommandDefinition.php`, `packages/cli/src/Parser/**`, `packages/cli/src/Io/**`, `packages/cli/src/Testing/**`, `bin/waaseyaa` | — | `docs/specs/cli-kernel.md` |
| `packages/foundation/src/Http/Inbound/*` | `waaseyaa:infrastructure` | `docs/specs/infrastructure.md` |
| `packages/foundation/*`, `packages/cache/*`, `packages/database-legacy/*`, `packages/plugin/*`, `packages/i18n/*`, `packages/queue/*`, `packages/state/*`, `packages/validation/*`, `packages/typed-data/*`, `packages/testing/*`, `packages/http-client/*` | `waaseyaa:infrastructure` | `docs/specs/infrastructure.md`, `docs/specs/package-discovery.md`, `docs/specs/plugin-extension-points.md`, `docs/specs/external-extension-sdk.md`, `docs/specs/extension-compatibility-matrix.md`, `docs/specs/version-provenance.md`, `docs/specs/extension-release-playbook.md`, `docs/specs/extension-author-onboarding.md` |
| `packages/mcp/*` | `waaseyaa:mcp-endpoint` | `docs/specs/mcp-endpoint.md` |
| `public/index.php` | `waaseyaa:middleware-pipeline` | `docs/specs/http-entry-point.md` |
| `packages/*/src/Middleware/*` | `waaseyaa:middleware-pipeline` | `docs/specs/middleware-pipeline.md` |
| `packages/note/*` | — | `docs/specs/ingestion-defaults.md` |
| `packages/relationship/*` | — | `docs/specs/relationship-modeling.md`, `docs/specs/relationship-inference-contract.md` |
| `packages/genealogy/*` | — | `docs/specs/genealogy.md`, `docs/specs/relationship-modeling.md` |
| `packages/graphql/*` | — | `packages/graphql/README.md` |
| `packages/search/*` | — | `packages/search/README.md` |
| `packages/seo/*` | — | `docs/specs/seo.md` |
| `packages/ssr/*` | — | `packages/ssr/README.md` |
| `packages/error-handler/*` | — | `docs/specs/debugging-dx.md` |
| `packages/debug/*` | — | `docs/specs/debugging-dx.md` |
| `packages/telescope/*` (agent-context / codified-context telemetry, Prometheus) | — | `docs/specs/telescope-agent-context-telemetry.md`, `docs/specs/api-layer.md` |
| `packages/workflows/*` | — | `packages/workflows/README.md` |
| `packages/billing/*` | — | `packages/billing/README.md` |
| `packages/github/*` | — | `packages/github/README.md` |
| `packages/deployer/*` | — | `packages/deployer/README.md` |
| `packages/inertia/*` | — | `packages/inertia/README.md` |
| `packages/engagement/*` | — | `packages/engagement/README.md` |
| `packages/geo/*` | — | `packages/geo/README.md` |
| `packages/mercure/*` | — | `packages/mercure/README.md` |
| `packages/messaging/*` | — | `packages/messaging/README.md` |
| `packages/oauth-provider/*` | — | `packages/oauth-provider/README.md` |
| `packages/analytics/*` | — | `packages/analytics/README.md` |
| `packages/mail/*` | `waaseyaa:infrastructure` | `docs/specs/infrastructure.md` |
| `packages/scheduler/*` | `waaseyaa:infrastructure` | `docs/specs/infrastructure.md` |
| `packages/notification/*` | `waaseyaa:infrastructure` | `docs/specs/infrastructure.md` |
| `packages/cms/*`, `packages/core/*`, `packages/full/*` | — (metapackages) | — |

| Workflow, Spec Kitty, GitHub (PRs/issues), roadmap | — | `docs/specs/workflow.md`, `docs/specs/per-site-convergence-audit.md`, `docs/specs/v1.5-verification-gate-contract.md`, `docs/specs/v1.6-verification-gate-contract.md` |
| `skills/waaseyaa/app-development/*` | — | — |
| `skills/waaseyaa/framework-extraction/*` | — | `docs/specs/extraction-log.md` |
| `docs/audits/*` | — | — |
| `docs/specs/**`, `.claude/**`, `**/CLAUDE.md` | `waaseyaa:spec-maintenance` | — |

When the mapping is not obvious, search under `docs/specs/` (e.g. `rg -n "TopicOrSymbol" docs/specs/`) or load `skills/waaseyaa/spec-maintenance/SKILL.md`.

## Layer Architecture

| Layer | Name | Packages |
|---|---|---|
| 0 | Foundation | analytics, cache, database-legacy, error-handler, foundation, geo, http-client, i18n, ingestion, mail, mercure, oauth-provider, plugin, queue, scheduler, state, typed-data, validation |
| 1 | Core Data | entity, entity-storage, access, user, config, field, auth, oidc, testing |
| 2 | Content Types | node, taxonomy, media, path, menu, note, relationship, groups, engagement, messaging |
| 3 | Services | workflows, search, seo, notification, billing, github, northcloud |
| 4 | API | api, bimaaji, routing |
| 5 | AI | ai-agent, ai-observability, ai-pipeline, ai-schema, ai-vector |
| 6 | Interfaces | cli, admin-surface, graphql, mcp, ssr, genealogy, telescope, deployer, inertia, debug |

**Rule:** Packages can only import from their own layer or lower. Upward communication via DomainEvents.

**Enforcement:** `bin/check-package-layers` validates every `packages/*/composer.json` `require` edge `waaseyaa/*` against this table (metapackages `cms`, `core`, `full` skipped). Runtime `require` only — `require-dev` may pull test fixtures from higher layers. Use `bin/audit-require-dev-layers` for a warn-only audit report of upward dev-only edges. Historical GitHub **#315** (foundation → path) and **#316** (validation → entity) are closed at the manifest level; re-run scripts after editing internal dependencies.

**Exemption:** The `Kernel/` classes in Foundation (`AbstractKernel`, `HttpKernel`, `ConsoleKernel`) are application bootstrappers that wire all layers together. They intentionally import from all layers. This is acceptable because kernels are entry-point orchestrators, not reusable library code — no other package imports from them.

**Auth and OIDC HTTP routes:** Route registration (RouteBuilder / WaaseyaaRouter) for `waaseyaa/auth` and `waaseyaa/oidc` is implemented in `Waaseyaa\Routing\AuthOidcRouteServiceProvider` ([packages/routing](packages/routing)) so L1 auth/oidc packages do not `use` Layer 4 routing types. Service bindings stay in their respective L1 `ServiceProvider` classes; only route wiring is lifted to L4.

## Operation Checklists

**Adding an entity type:**
1. Define `EntityType` with id, label, entity keys, entity class
2. Create entity class extending `EntityBase` — constructor takes `(array $values)`, hardcodes `entityTypeId` and `entityKeys`
3. Register in `EntityTypeManager` via service provider's `register()` method
4. Create storage schema via `SqlSchemaHandler` — define columns, `_data` blob is automatic
5. Add `AccessPolicyInterface` (+ `FieldAccessPolicyInterface` if field-level control needed)
6. Add API routes in `RouteBuilder`, wire controller, set route access options (`_gate` for entity access)
7. Test: use `InMemoryEntityStorage` or `DBALDatabase::createSqlite()` for in-memory testing

**Adding an access policy:**
1. Create class implementing `AccessPolicyInterface` (add `FieldAccessPolicyInterface` if field access needed — same class, intersection type)
2. Register via `#[PolicyAttribute(entityType: 'entity_type_id')]` attribute on the class
3. Implement `access()` returning `AccessResult` — use `::allowed()`, `::neutral()`, `::forbidden()`
4. For field access: implement `fieldAccess()` — Neutral = accessible (open-by-default), only Forbidden restricts
5. Test with anonymous classes implementing both interfaces (PHPUnit `createMock()` can't mock intersection types)
6. Run `waaseyaa optimize:manifest` (or restart dev server) to pick up the new policy

**Adding an API endpoint:**
1. Add route in `RouteBuilder` with access options (`_public`, `_authenticated`, `_session`, `_permission`, `_role`, or `_gate`)
2. Implement controller method following `JsonApiController` CRUD patterns
3. Wire access via route options — `AccessChecker` evaluates them from the matched route
4. For entity endpoints: use `ResourceSerializer` with paired nullable `?EntityAccessHandler` + `?AccountInterface`
5. Add to `SchemaPresenter` if JSON Schema output is needed — set `x-access-restricted` for view-only fields

**Adding middleware:**
1. Implement `HttpMiddlewareInterface` (or `JobMiddlewareInterface`)
2. Add `#[AsMiddleware(priority: N)]` attribute — higher priority runs first (outer onion layer)
3. Middleware is auto-discovered by `PackageManifestCompiler` via attribute scanning
4. Follow handler naming: `{Type}HandlerInterface` for handler, `{Type}MiddlewareInterface` for middleware

**Adding a service provider:**
1. Create class extending `ServiceProvider` in the package's root namespace
2. `register()` — bind interfaces to implementations, register entity types, set up factories
3. `boot()` — subscribe to events, register routes, warm caches (after all providers registered)
4. Add `extra.waaseyaa.providers` to the package's `composer.json` for auto-discovery

## Workflow (Spec Kitty–first)

Substantive work is driven by **[Spec Kitty](https://github.com/Priivacy-ai/spec-kitty)** missions and work packages (`.kittify/`, `spec-kitty next`, dashboard); **GitHub** is the PR/CI/releases surface and optional issue visibility. Full rules: `docs/specs/workflow.md` (versioning, semantic milestones, Track mirror, PR traceability).

**The 5 rules (summary — see `docs/specs/workflow.md` for nuance):**

1. **Substantive work begins in Spec Kitty** — active mission/WP or `spec-kitty next`; M11 filings may still require a GitHub issue as the audit front door (link it).
2. **GitHub issues (when used) get a Track milestone** — `bin/check-milestones` surfaces gaps; issues are not mandatory for every change.
3. **Roadmap intent** — semantic v1.x table + mission state; GitHub Tracks mirror themes for issue-only readers.
4. **PRs must be traceable** — `#N` and/or Spec Kitty mission/WP reference per `docs/specs/workflow.md` and `.github/pull_request_template.md`.
5. **Session context** — prefer Spec Kitty state when under a mission; read `bin/check-milestones` when touching GitHub issues.

## Agent context and Spec Kitty

- **Constitution (this file):** Session-hot rules — orchestration table, layer graph, checklists, gotchas.
- **Specialist skills:** `skills/waaseyaa/*` — load on demand for a subsystem; each skill lists related specs.
- **Cold specs:** `docs/specs/*.md` — read directly from disk when you need contracts, file maps, and edge cases (no spec MCP server).

Install the CLI: `pip install spec-kitty-cli` or `uv tool install spec-kitty-cli`. Run `spec-kitty upgrade` in the repo after upgrading the CLI. The CLI may create `.claude/skills/` symlinks to your global Spec Kitty skill pack — that directory is gitignored here because paths are machine-specific; re-run `spec-kitty init` (or upgrade) on a fresh clone after installing the CLI.

**Workflow precedence:** **Spec Kitty** owns mission/work-package execution and structured review. **GitHub** owns merge mechanics, CI, releases, and optional issues. **`docs/specs/`** owns subsystem contracts — read from disk, update when behaviour changes.

Design docs in `docs/plans/` are session artifacts (implementation history). Specs in `docs/specs/` are enduring architectural knowledge (kept current). When refactoring a subsystem, update its spec — run `tools/drift-detector.sh` to find stale specs.

## Commands

**Testing** (do NOT use `-v` flag, PHPUnit 10.5 rejects it):
- `./vendor/bin/phpunit` — run all tests
- `./vendor/bin/phpunit --testsuite Unit` — unit tests only
- `./vendor/bin/phpunit --testsuite Integration` — integration tests only
- `./vendor/bin/phpunit --filter Phase10` — run tests matching a pattern
- `./vendor/bin/phpunit packages/mail/tests/` — run a single package's tests

**Code quality:**
- `composer cs-check` — check code style (dry-run PHP-CS-Fixer)
- `composer cs-fix` — auto-fix code style
- `composer phpstan` — static analysis (PHPStan 2.x, level 5)
- `composer check-composer-policy` — enforce codified Composer manifest policy
- `bin/check-package-layers` — enforce internal `waaseyaa/*` dependency layers (see Layer Architecture)
- `bin/audit-require-dev-layers` — warn-only report for upward `require-dev` `waaseyaa/*` edges

**Development:**
- `composer dev` — start dev server (PHP built-in server + admin SPA)
- `bin/waaseyaa` — CLI entry point (SQLite + file config)
- `bin/waaseyaa optimize:manifest` — rebuild attribute-discovery manifest

## Code Style
- PHP 8.4+, `declare(strict_types=1)` in every file
- Namespace pattern: `Waaseyaa\PackageName\` (e.g., `Waaseyaa\Entity\`, `Waaseyaa\AI\Schema\`)
- Test namespace: `Waaseyaa\PackageName\Tests\Unit\` or `Waaseyaa\Tests\Integration\PhaseN\`
- PHPUnit 10.5 attributes: `#[Test]`, `#[CoversClass(...)]`, `#[CoversNothing]` for integration tests
- Symfony 7.x components (Console, EventDispatcher, Routing, Validator, Uid, Yaml, Messenger)
- Named constructor parameters: `new EntityType(id: 'node', label: 'Content', ...)`
- `final class` by default for concrete implementations
- Admin SPA: Nuxt 3 + Vue 3 + TypeScript. Composables in `packages/admin/app/composables/`, i18n in `packages/admin/app/i18n/en.json`
- Brand color: Deep Teal (`#0d4f4f` → `#0f766e` → `#14b8a6`). Chosen to be distinct from Drupal (blue), Laravel (red), Django/Nuxt (green), Strapi (purple). Auth CSS tokens and AdminShell `--color-primary` use this palette.
- Frontend entry point: `public/index.php` (PHP built-in server front controller)

## Architecture Gotchas
- **Entity subclass constructors**: User, Node etc. only accept `(array $values)` and hardcode entityTypeId/entityKeys. SqlEntityStorage uses reflection to detect constructor shape.
- **Dual-state bug pattern**: When data can come from two sources (e.g., attribute vs registry), always use one canonical source. Found repeatedly in ComponentRenderer, Pipeline, entity values.
- **DBAL fetch mode**: DBALDatabase uses `fetchAssociative()` to return associative arrays (equivalent to FETCH_ASSOC).
- **_data JSON blob**: SqlSchemaHandler adds a `_data` TEXT column. SqlEntityStorage::splitForStorage() puts non-schema values into it as JSON; mapRowToEntity() merges them back on load.
- **PascalCase conversion**: Use `str_replace('_', '', ucwords($name, '_'))` not `ucfirst()`.
- **InMemoryEntityStorage** (`Waaseyaa\Api\Tests\Fixtures\`) — use for tests. SqlEntityStorage for real storage.
- **EntityTypeManager** takes `(EventDispatcherInterface, ?\Closure $storageFactory = null)` where factory receives `EntityTypeInterface $definition`.
- **EntityEvent uses public properties**: `$event->entity` and `$event->originalEntity` are public readonly — no getter methods. Common mistake: `$event->getEntity()`.
- **DatabaseInterface vs DBALDatabase**: `DatabaseInterface` does NOT have `getConnection()`. If the DBAL `Connection` is needed, type-hint `DBALDatabase` directly. Prefer using query builder (`select()`, `insert()`, `delete()`) over raw DBAL when possible.
- **LIKE wildcard escaping**: `DBALSelect` appends `ESCAPE '\'` for LIKE/NOT LIKE operators. When building LIKE patterns in `SqlEntityQuery`, escape `%` and `_` in user input with `str_replace(['%', '_'], ['\\%', '\\_'], $value)`.
- **JSON symmetry**: Always pair `json_encode(..., JSON_THROW_ON_ERROR)` with `json_decode(..., JSON_THROW_ON_ERROR)`. Asymmetric usage causes silent `null` on corrupt data.
- **Best-effort side effects**: Event listeners for non-critical operations (broadcasting, logging, cache invalidation) should wrap in try-catch and log via `LoggerInterface` to avoid crashing the primary request.
- **Final classes can't be mocked**: PHPUnit `createMock()` fails on `final class`. Use real instances with temp directories (e.g., `sys_get_temp_dir() . '/waaseyaa_test_' . uniqid()`) instead.
- **Atomic file writes**: Cache files must use write-to-temp-then-rename (`file_put_contents($tmp)` then `rename($tmp, $target)`) to prevent serving partial writes.
- **No psr/log**: Project does not use `psr/log`. Use `Waaseyaa\Foundation\Log\LoggerInterface` for structured logging. Accept `?LoggerInterface $logger = null` in constructors and default to `NullLogger`. Reserve `error_log()` only for last-resort fallbacks inside the logging infrastructure itself.
- **Middleware interface naming**: Handler interfaces follow `{Type}HandlerInterface` pattern (HttpHandlerInterface, EventHandlerInterface, JobHandlerInterface). Middleware follows `{Type}MiddlewareInterface`.
- **Entity enforceIsNew()**: When creating entities with pre-set IDs (e.g., `new User(['uid' => 2])`), call `$entity->enforceIsNew()` before `save()`. Otherwise `isNew()` returns false, SqlEntityStorage tries UPDATE instead of INSERT, and silently affects 0 rows.
- **Layer discipline for imports**: Foundation (layer 0) must never import from higher layers. When cross-layer attribute scanning is needed, use string constants instead of `::class` references (e.g., `private const POLICY_ATTRIBUTE = 'Waaseyaa\\Access\\Gate\\PolicyAttribute'`). `ReflectionClass::getAttributes()` accepts string class names.
- **Avoid circular package deps**: Access owns `AccountInterface`; User owns `AnonymousUser`. Access must not depend on User. Middleware needing an account should type-hint `AccountInterface`, not concrete `AnonymousUser`.
- **php://input is single-read**: `HttpRequest::createFromGlobals()` consumes `php://input`. For subsequent body reads, use `$httpRequest->getContent()`, not `file_get_contents('php://input')`.
- **Backward-compatible cache evolution**: When adding new properties to cached manifests/configs, make them optional in deserialization (use `$data['key'] ?? []`) to avoid breaking old cached files.
- **Avoid double `$storage->create()` in access checks**: When checking field access before persisting a new entity, create once and reuse for both the access check and the save. Don't create a throwaway temp entity.
- **Paired nullable parameters**: `ResourceSerializer::serialize()` and `SchemaPresenter::present()` accept `?EntityAccessHandler` + `?AccountInterface`. Both must be non-null or both null — only two of four states are meaningful. The guard pattern is `if ($handler !== null && $account !== null)`.
- **SchemaPresenter `x-access-restricted`**: JSON Schema extension marking fields viewable but not editable. The admin SPA reads this to show disabled widgets instead of hiding the field. Distinct from system `readOnly` (id, uuid) which hides the field from forms entirely.
- **GraphQL `totalCount` = full dataset**: `totalCount` in list queries reflects the full storage count, not the access-filtered subset. `items` contains only entities the caller can access. This matches Relay/Apollo/Hasura conventions, ensures stable pagination, and avoids leaking content (only existence). Do not "fix" this to return filtered counts — it is intentional (see #436).
- **Stale specs cause bad code**: When refactoring a subsystem, update the relevant `docs/specs/` file. Stale specs cause agents to generate code conflicting with recent changes. Run `tools/drift-detector.sh` to find affected specs.
- **Response flow is return-based**: `HttpKernel::handle()` and `ControllerDispatcher::dispatch()` return Symfony `Response` objects. `public/index.php` calls `$response->send()`. No `exit` in framework internals.
- **Account sentinel IDs**: `AnonymousUser` uses `id: 0`, `DevAdminAccount` uses `PHP_INT_MAX`. Never use `1` or other low integers for non-real accounts — they collide with auto-increment UIDs.
- **`PackageManifestCompiler` prefers optimized autoloader**: `scanClasses()` tries `autoload_classmap.php` first, then falls back to PSR-4 directory scanning with a warning log. The classmap under default `composer install` has entries (Composer internals, polyfill stubs) but no `Waaseyaa\` classes — the fallback triggers on missing Waaseyaa entries, not an empty classmap. Run `composer dump-autoload --optimize` for faster, more reliable discovery.
- **Never put classes that extend dev-only deps under `autoload`**: Any class under a package's `src/` directory is reachable via PSR-4 in production consumer installs (`composer install --no-dev`). If such a class `extends PHPUnit\Framework\TestCase` or any other dev-only symbol, a consumer's `PackageManifestCompiler` class scan will Reflection-load it, fail to resolve the parent, and throw `Class "X" not found` — crashing kernel boot with "Application failed to boot." The fix pattern: put test-helper base classes in a top-level `testing/` directory (sibling to `src/` and `tests/`) and register `"Waaseyaa\\Foo\\Testing\\": "testing/"` under `autoload-dev` only. Consumers installing with dev deps still get them; production installs never see them. Caught in `waaseyaa/graphql` alpha.106 → alpha.107 via a production outage on minoo; applies to every package shipping test-helper base classes.
- **Dev-mode SAPI guard**: Use `PHP_SAPI === 'cli-server'` to gate dev-only behavior (e.g., `DevAdminAccount` in `index.php`). Classes with constructor guards must also allow `cli` SAPI for PHPUnit to instantiate them.
- **Dev fallback account requires three conditions**: `HttpKernel::shouldUseDevFallbackAccount()` needs ALL of: (1) `PHP_SAPI === 'cli-server'`, (2) `isDevelopmentMode()` (`APP_ENV=local`), (3) `config['auth']['dev_fallback_account'] === true` (via `WAASEYAA_DEV_FALLBACK_ACCOUNT=true` in `.env`). Missing any one silently disables the dev admin — the SPA shows a login page instead of auto-authenticating. The skeleton's `.env.example` sets this to `true` by default.
- **CORS origins configurable**: `HttpKernel::handleCors()` reads `cors_origins` from `config/waaseyaa.php`. Defaults to `localhost:3000` and `127.0.0.1:3000`. Mismatched origins are logged. If Nuxt dev server binds to a non-standard port, add it to the config array.
- **SchemaController field definitions**: `SchemaController::show()` passes `$entityType->getFieldDefinitions()` to `SchemaPresenter::present()`. Field definitions are registered per entity type via the `fieldDefinitions:` constructor param on `EntityType`.
- **`discoverAccessPolicies()` constructor heuristic**: `ConfigEntityAccessPolicy` takes `array $entityTypeIds` as a required constructor parameter (from `#[PolicyAttribute]`). The reflection-based heuristic in `AbstractKernel::discoverAccessPolicies()` that passes entity types to constructors with required params exists for this reason — do not remove it.
- **`toMachineName()` can return empty string**: Labels with only special characters (e.g. `"!!!"`) produce empty machine names after regex replacement and trim. `JsonApiController::store()` guards against this with a 422 response. Any caller of `toMachineName()` must validate the result.
- **Debug boot guard**: `AbstractKernel::boot()` throws `RuntimeException` if `isDebugMode()` is true and `isDevelopmentMode()` is false. Both methods and the boot guard use `resolveEnvironment()` as the single canonical source for `APP_ENV` resolution. Tests that boot with `debug => true` in config must also set `APP_ENV=local`.
- **Kernel boot flag ordering**: `AbstractKernel::boot()` sets `$this->booted = true` *after* all initialization steps succeed. Setting it before would create a zombie state where boot failure prevents retry. If adding new boot steps, add them before the flag assignment.
- **Migration system boot order**: `bootMigrations()` runs after `compileManifest()` (requires `PackageManifest`) and before `discoverAndRegisterProviders()`. It reuses the DBAL `Connection` from `DBALDatabase` (via `getConnection()`) — single connection, no duplication.
- **`MakeMigrationCommand` requires `$projectRoot`**: Constructor changed from no-arg to `(string $projectRoot)`. ConsoleKernel must pass `$this->projectRoot`. The `--package` flag is not yet implemented (see #464).
- **Migration CLI commands take `\Closure` providers**: `MigrateCommand`, `MigrateRollbackCommand`, `MigrateStatusCommand` all accept `(Migrator, \Closure $migrationsProvider)`. The closure defers filesystem scanning until the command runs. In ConsoleKernel: `fn () => $this->migrationLoader->loadAll()`.
- **Entity types without `uuid` key are config entities**: `SqlEntityStorage::save()` requires explicit non-empty string IDs for entities whose `EntityType` keys lack `'uuid' => 'uuid'`. Content entities with auto-increment IDs must include the uuid key even if they don't use UUIDs.
- **`entity_reference` field definitions need `target_entity_type_id`**: `EntityTypeBuilder` looks for `target_entity_type_id` or `targetEntityTypeId` in field definitions, not `target`. Using the wrong key causes silent fallback to String type with no reference resolution.
- **GraphQL reference fields keep storage field names**: A field defined as `author_id` with type `entity_reference` produces a GraphQL field named `author_id` (not `author`). It resolves to the nested entity object but the field name includes the `_id` suffix.
- **PHP 8.4 parameter defaults can't call static methods**: `SomeClass::create()` is not valid as a constructor parameter default. Use nullable + resolve in body: `?Foo $foo = null` then `$this->foo = $foo ?? Foo::create()`. Found when replacing `new EditorialWorkflowStateMachine()` (no-arg, valid default) with `EditorialWorkflowPreset::create()` (static call, invalid default).
- **Replacing self-contained defaults with empty generics breaks consumers**: When a no-arg constructor default (like `new EditorialWorkflowStateMachine()` which was always pre-populated) is replaced with a generic empty object (like `new Workflow()` with zero states), every consumer relying on the default silently gets a broken instance. Always audit all callers when changing constructor defaults.
- **AnthropicProvider cURL streaming**: `CURLOPT_WRITEFUNCTION` callbacks must not throw — wrap `json_decode(..., JSON_THROW_ON_ERROR)` in try-catch inside callbacks. Error handling in `httpPostStreaming` must match `httpPost` (parse error body, handle 429 with `RateLimitException`).
- **Browser `fetch` loses binding when stored**: Passing `fetch` as a default parameter (`private fetchFn = fetch`) detaches it from `window`, causing "illegal invocation" at call time. Wrap in an arrow function: `(...args) => fetch(...args)`.
- **Request attribute is `_account` not `account`**: SessionMiddleware sets `$request->attributes->set('_account', $account)`. Any code reading the authenticated account (controllers, surface hosts, middleware) must use `_account`. Reading `account` (no underscore) silently returns `null`.
- **Nuxt `$fetch` doesn't send cookies by default**: Admin SPA fetch calls to PHP endpoints need `credentials: 'include'` to send the PHPSESSID cookie. Without it, session-based auth fails silently.
- **Nuxt `[entityType]` catch-all matches single-segment paths**: In E2E tests, navigating to `/some-path` hits the dynamic `[entityType]/index.vue` route instead of showing a 404. Use multi-segment paths (`/no/such/deep/route`) to test error pages.
- **FTS5 `SELECT m.*` misses FTS5 columns**: When joining `search_index` (FTS5) with `search_metadata`, `m.*` only selects metadata columns. To get FTS5 content columns (title, body), explicitly select them: `si.title`, `si.body`. The `snippet()` function also requires column index references into the FTS5 table.
- **FTS5 query escaping must strip special chars**: FTS5 treats `*`, `^`, `{}`, `:`, `"` as operators in addition to `AND/OR/NOT/NEAR`. Quoting terms with `"..."` is not sufficient — strip special characters before quoting to prevent query injection.
- **`DefaultsSchemaRegistry` caches on first access**: To test `PAYLOAD_SCHEMA_LOAD_FAILED` in `PayloadValidator`, write a valid schema first so the registry builds a `SchemaEntry`, then corrupt the file before validation. Writing invalid JSON directly yields `PAYLOAD_SCHEMA_NOT_FOUND` (no entry created).
- **`ServiceProvider` has no `$dispatcher` property**: Event subscriber registration must resolve the dispatcher via `$this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class)` and check `instanceof Symfony\Component\EventDispatcher\EventDispatcherInterface` before calling `addSubscriber()`.
- **`EntityBase` lifecycle hooks**: `preSave(bool $isNew)`, `postSave(bool $isNew)`, `preDelete()`, `postDelete()` — no-op by default. Override in entity subclasses. Called by `EntityRepository` before/after events. Order: `preSave()` → PRE_SAVE event → persist → POST_SAVE event → `postSave()`.
- **`EntityRepository` auto-validation**: When `EntityValidator` is injected, `save()` validates against `EntityType::getConstraints()` and throws `EntityValidationException` on failure. Pass `validate: false` to bypass for migrations/bulk imports. `saveMany()` also respects the `validate` parameter.
- **`saveMany()`/`deleteMany()` use UnitOfWork**: Batch operations wrap all writes in a single transaction via `UnitOfWork`. Events are buffered and dispatched only after successful commit. Requires `$database` to be non-null (throws `LogicException` otherwise).
- **Kernel Bootstrap directory**: Extracted bootstrappers live in `packages/foundation/src/Kernel/Bootstrap/` — `DatabaseBootstrapper`, `ManifestBootstrapper`, `ProviderRegistry`, `AccessPolicyRegistry`. AbstractKernel delegates to these.
- **Admin plugin runs on ALL pages including public auth pages**: The async admin plugin (`packages/admin/app/plugins/admin.ts`) fetches `/_surface/session` on every page. It must skip auth check for all public auth paths (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`) via the `publicAuthPaths` array, otherwise 401 → redirect → 401 loop. `useRoute()` is unreliable in async plugin context — use `window.location.pathname` on client.
- **Nuxt `.env` changes require dev server restart**: HMR picks up source file changes but NOT `.env` changes. Runtime config from `.env` is read at server startup only. Clear `.nuxt/` cache if values seem stale after restart.
- **Git worktrees can't run Nuxt dev server**: Worktrees share source via symlinks but not `node_modules/.vite/` or `.nuxt/`. Vite module resolution fails with MIME type errors. Run E2E tests against the main repo's dev server, not from worktrees.
- **RateLimiter: check before hit**: Always call `tooManyAttempts()` BEFORE `hit()`. Calling `hit()` first counts the current request before checking, reducing the effective limit by 1.
- **PHPUnit void method mocking**: `createMock()` + `willReturn(null)` on void methods throws `IncompatibleReturnValueException`. Use `createStub()` for classes with void methods, or omit `willReturn()` entirely.
- **`database-legacy` package namespace is `Waaseyaa\Database`**: Despite the directory being `packages/database-legacy/`, the PHP namespace is `Waaseyaa\Database`, NOT `Waaseyaa\DatabaseLegacy`. Always check `composer.json` autoload for the canonical namespace. Renaming the Composer package was rejected for alpha stability — see `docs/adr/007-database-legacy-package-naming.md`.
- **Auth config in admin SPA**: `runtimeConfig.public.auth` provides `registration` (admin/open/invite) and `requireVerifiedEmail` (boolean). Cast as `Record<string, unknown>` in TypeScript to safely access nested keys. Controlled by `NUXT_PUBLIC_AUTH_REGISTRATION` and `NUXT_PUBLIC_AUTH_REQUIRE_VERIFIED_EMAIL` env vars.
- **DBAL empty IN/NOT IN returns no results**: `condition('id', [], 'IN')` and `condition('id', [], 'NOT IN')` both return empty results — DBAL silently produces no matches. Callers must guard against empty arrays before building IN conditions.
- **JsonApiResource::toArray() omits empty keys**: `attributes` and `relationships` are omitted from serialized output when empty, not set to `[]`. Tests should use `assertArrayNotHasKey` for empty fields, not `assertEmpty`.
- **Sparse fieldsets**: `index()` and `show()` filter both `attributes` and `relationships` via `SparseFieldsetApplicator` when `fields[type]` is present, matching JSON:API (`fields[type]` applies to sparse fieldsets for that resource type).
- **Mail stack**: Single path — **`MailerInterface::send(Envelope)`** with **`TransportInterface`** (SendGrid when API key + from address are set; else array or local per config). **`AuthMailer`** and **`MailChannel`** both use the mailer binding. (#798)
- **Nuxt async plugins can't call composables**: `defineNuxtPlugin(async () => ...)` runs outside the composable lifecycle. Use raw `$fetch` with explicit `baseURL: '/'` and `credentials: 'include'` in plugins. Composables like `useApi()` work only in `<script setup>`, composables, and middleware.

## Testing
- Integration tests in `tests/Integration/PhaseN/` — one directory per implementation phase
- GraphQL integration tests in `tests/Integration/GraphQL/` — full-stack tests with real SQLite via `DBALDatabase::createSqlite()`
- Unit tests in `packages/*/tests/Unit/`
- Use `CommandTester` from Symfony Console for CLI command tests
- Use `ArrayLoader` for Twig tests (no filesystem needed)
- All storage can be in-memory: MemoryStorage (config), MemoryBackend (cache), InMemoryEntityStorage (entities), DBALDatabase::createSqlite() (SQL with :memory:)
- Test cache file handling with corrupt files (`<?php throw new \RuntimeException("corrupt");`) and wrong return types (`<?php return "not an array";`) to verify recovery paths
- Contract tests in `packages/*/tests/Contract/` — abstract base classes verify interface compliance, concrete tests per implementation. Use `#[CoversNothing]` for contract tests.
- Test access policies with anonymous classes implementing intersection types (`AccessPolicyInterface & FieldAccessPolicyInterface`) — PHPUnit `createMock()` can't mock intersection types, so use real anonymous classes with inline logic
- Frontend tests: `cd packages/admin && npm test` — Vitest with `@nuxt/test-utils` nuxt environment
- Frontend build verification: `cd packages/admin && npm run build` — TypeScript compilation check
- Frontend E2E: `cd packages/admin && npm run test:e2e` — Playwright specs in `e2e/`; requires `nuxt dev` on port 3000

## Environment
- `APP_ENV` — Application environment: `local`, `dev`, `development`, `testing`, `staging`, `production` (default: `production`)
- `APP_DEBUG` — Debug mode toggle (default: `false`). Enables detailed error pages, debug toolbar, debug headers. **Kernel refuses to boot if `APP_DEBUG=true` in production.**
- `LOG_LEVEL` — Minimum log level for default handler: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency` (default: `warning`)
- `WAASEYAA_DB` — SQLite database path (default: `./storage/waaseyaa.sqlite`)
- `WAASEYAA_CONFIG_DIR` — config sync directory (default: `./config/sync`)

## Architectural Boundaries

Waaseyaa is the **framework layer**. It owns the entity system, storage engine, field types, ingestion envelope contract, GraphQL/REST API, access control, and SSR rendering.

**Waaseyaa does NOT own:**
- Minoo-specific entity types (those belong in Minoo's src/Entity/)
- Content classification or routing (that's North Cloud)
- Map UX, dialect logic, or community-specific features (that's Minoo)

**Import rules:**
- Waaseyaa must not import from Minoo — the dependency flows one way (Minoo → Waaseyaa)
- Waaseyaa must not reference North Cloud services or APIs
- Waaseyaa defines the ingestion envelope contract that external tools (Python harvesters) must follow
