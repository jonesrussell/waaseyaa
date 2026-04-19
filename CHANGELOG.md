# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0-alpha.150] - 2026-04-19

Closes the bundle-substrate four-release discovery arc: alpha.148 shipped the bundle API, alpha.149 wired `FieldDefinitionRegistry` but missed the kernel-path enumerator, the alpha.149 fix wired the enumerator but exposed a missing `getQuery()` registry forward, and this release closes query forwarding while introducing explicit storage-hint semantics. See #26 for the cross-package version-constraint tightening that would have shortened this arc.

### Fixed

- `entity-storage`: `SqlEntityStorage::getQuery()` now forwards `$this->fieldRegistry` into `SqlEntityQuery`. Previously the registry was silently dropped, so `getQuery()->condition($bundleField, $value)` either returned wrong rows (no JOIN) or threw `UnknownFieldException` once strict routing landed. Bundle-scoped queries on registry-aware storage now work end-to-end.

### Added

- `field`: `FieldStorage` backed enum (`Column`, `Data`) and `FieldDefinition::getStored()` accessor expressing the canonical persistence target for a field. `FieldDefinitionRegistry::synthesizeCoreField()` reads `'stored'` from EntityType field-definition metadata. Defaults to `FieldStorage::Column` so existing call sites are unchanged.
- `entity-storage`: `SqlEntityQuery::routeFields()` resolves `FieldStorage::Data` core fields via `json_extract(_data, ...)` instead of throwing `UnknownFieldException`, so registry-aware queries can target `_data`-backed fields without forcing a column to exist. `SqlEntityStorage::splitForStorage()` honors the same hint on save: `FieldStorage::Data` values land in `_data` even when a legacy column happens to exist (deferred follow-up: column-vs-data drift diagnostic).
- `entity-storage`: `SqlSchemaHandler` skips column emission for `FieldStorage::Data` fields in `buildBundleSubtableSpec()` and `ensureBundleSubtable()` so subtable layouts match the registered storage hints.
- `groups`: `Group` now ships universal lifecycle core fields (`status`, `created_at`, `updated_at`) registered with `stored: FieldStorage::Data`. The bundle-fields surface stays consumer-defined; only the universals are pre-registered so registry-aware queries can resolve them.

## [0.1.0-alpha.149] - 2026-04-19

### Fixed

- `entity-storage`: `SqlSchemaHandler::shouldProcessBundles()` no longer requires an explicit `bundleEnumerator`. The enumerator becomes an optional escape hatch; when absent, `registeredBundlesFor()` falls back to `FieldDefinitionRegistry::bundleNamesFor()` — the same source `SqlEntityStorage` uses for save-time partitioning, so schema materialization and write-path routing agree on the "known bundles" set. Closes the alpha.148 kernel-path gap where `AbstractKernel`-booted apps with `addBundleFields()`-registered bundles silently skipped subtable creation and crashed at runtime with `no such table: {base}__{bundle}`. PR #1306.

### Added

- `foundation`: `KernelBundleSubtableMaterializationTest` — kernel-boot integration test that boots `AbstractKernel`, registers bundle fields via `EntityTypeManager::addBundleFields()`, triggers `getStorage()`, and asserts the `{base}__{bundle}` subtable physically materializes in the database. The assertion gap that allowed alpha.148 to ship with the kernel path broken.

## [0.1.0-alpha.148] - 2026-04-19

### Added

- `groups`: new layer-2 content-type package `waaseyaa/groups` (`Group` extends `ContentEntityBase`, `GroupType` extends `ConfigEntityBase`). Ships with zero pre-registered bundles — consumers register `GroupType` config entities and bundle-scoped fields via `EntityTypeManager::addBundleFields()`. Closes #1296.
- `entity-storage`: bundle-scoped storage substrate. Per-bundle subtables follow the `{base_table}__{bundle}` naming invariant, carry a `PRIMARY KEY (entity_id)` + `FOREIGN KEY ... REFERENCES {base}(id) ON DELETE CASCADE`, and partition field storage by bundle. `SqlSchemaHandler::ensureBundleSubtable()` provisions subtables; `SqlEntityStorage` routes per-bundle writes via `persistBundleRow()` (SELECT-then-INSERT-or-UPDATE) and merges bundle columns on read via `mergeBundleSubtableRow()`.
- `field`: `FieldDefinitionRegistry` partitions field definitions into core and per-bundle buckets. `EntityTypeManager::addBundleFields()` accepts an array of `FieldDefinition` objects keyed by entity type + bundle. `BundleAmbiguousFieldException` and `UnknownFieldException` surface registry lookup failures with explicit diagnostics.
- `access`: `#[AccessPolicy(bundles: [...])]` attribute parameter binds a policy to specific bundles of an entity type; `EntityAccessHandler` consults the bundle-scoped registry when resolving policies.
- `foundation`: subtable-aware schema drift diagnostics. `HealthChecker` emits `MISSING_BUNDLE_SUBTABLE` (registered fields but no subtable), `ORPHAN_BUNDLE_SUBTABLE` (subtable but no registered bundle; SQLite-only detection until #1300), and `FK_ENFORCEMENT_DISABLED` (SQLite `PRAGMA foreign_keys = OFF` or MySQL session override — bundle `ON DELETE CASCADE` silently becomes a no-op when FK enforcement is disabled).

### Changed

- `ci`: `.github/workflows/split.yml` matrix now includes `packages/groups` under Layer 2 so the split-deploy pipeline publishes `waaseyaa/groups` to its own repo and packagist.

## [0.1.0-alpha.147] - 2026-04-18

### Fixed

- `northcloud`: `NorthCloudServiceProvider` now wires `allow_insecure` through to `NorthCloudClient`. Reads the flag from config, honors `NORTHCLOUD_ALLOW_INSECURE` env var, and auto-permits `http://localhost` / `http://127.0.0.1` loopback URLs so dev stacks with `NORTHCLOUD_BASE_URL=http://localhost:8050` no longer crash at CLI boot. Production https URLs stay strict. Removes the need for consumer apps to duplicate `shouldAllowInsecureNorthCloudUrl()` helpers across providers and scripts.

## [0.1.0-alpha.146] - 2026-04-18

### Added

- `oidc`: `/authorize` now propagates the OIDC `nonce` query parameter through the authorization code repository. `AuthorizationCodeRepositoryInterface::issue()` accepts `?string $nonce = null`, `AuthorizationCode` exposes it as a public field, and `DatabaseAuthorizationCodeRepository` stores it in a new nullable `nonce` column (added lazily via `ALTER TABLE` for tables provisioned before this change). Required by OIDC Core §3.1.3.6 so `/token` can embed `nonce` in the issued ID token. Closes #1289.
- `oidc`: `POST /token` endpoint implementing the OAuth 2.1 / OIDC authorization_code grant (ADR-006 §7, OIDC Core §3.1.3). Exchanges a consumed authorization code for an RS256-signed ID token and an opaque access token. Supports public clients via PKCE S256 alone, and confidential clients via HTTP Basic or `client_secret_post` per RFC 6749 §2.3.1. Enforces byte-exact `redirect_uri` match, `client_id` binding, and PKCE verifier check (RFC 7636). ID token claims: `iss`, `sub`, `aud`, `exp` (10 min), `iat`, `auth_time`, `nonce` (when issued). Error responses follow RFC 6749 §5.2. Route is CSRF-exempt (client authentication happens inside the handler). Non-goals: refresh tokens, `/userinfo`, consent screen, `at_hash` claim, JWT access tokens. Closes #1292.

### Fixed

- `cli`: `waaseyaa serve` now starts PHP's built-in server with `public/index.php` as the router script instead of only setting the document root. This restores dynamic front-controller routing for local app development, so routes like `/submissions/1` and `/cohorts/1` resolve correctly under `composer run dev`.

## [0.1.0-alpha.145] - 2026-04-16

### Changed

- `EnvLoader::load()` now populates `$_ENV` and `$_SERVER` in addition to `putenv()`, each guarded independently against overwriting a preset value. Consumer entry points (`public/index.php`, CLI bins) can drop their own `Symfony\Component\Dotenv` blocks — the kernel already owns `.env` loading. See ADR-005.

### Fixed

- CLI: `vendor/bin/waaseyaa` now resolves project root via `getcwd()` instead of walking up from `__DIR__`. Previously, `__DIR__` canonicalized through symlinks, so a consumer with `vendor/waaseyaa/cli` symlinked to the framework monorepo (standard local-dev workflow) booted the kernel with the framework repo as `$projectRoot`, read `.env` from the wrong place, and fell through to `APP_ENV=production`. See ADR-005.
- CLI: running the binary from outside a project root now errors with a clear message (`must be run from a project root (directory containing composer.json)`) instead of silently resolving to the wrong location.

### Removed

- `packages/framework` metapackage (ADR-004 partial). Its declared name (`waaseyaa/framework`) collided with the monorepo's own repo and was the root cause of the 2026-04-16 release-pipeline incident that reduced `origin/main` to a single `composer.json`. The invariant "there is exactly one composer.json declaring `waaseyaa/framework`, and it is the monorepo root" is now enforced by construction. The `replace`-block rewrite described in ADR-004 §5 is deferred — `PackageManifestCompiler` and other discovery paths rely on `vendor/composer/installed.json` entries that `replace` semantics omit. A follow-up ADR will fix discovery before the rewrite lands.
- `skeleton/bin/waaseyaa` wrapper. Consumer apps use `./vendor/bin/waaseyaa` via Composer's auto-generated proxy. The wrapper previously existed to work around the CLI bootstrap bugs now fixed by ADR-005.

## [0.1.0-alpha.144] - 2026-04-16

### Added

- `northcloud`: observable dry-run diagnostics for sync runs.

### Changed

- **BREAKING (entity-storage):** `EntityStorageDriverInterface::write()` now returns `string` (the effective id of the persisted row) instead of `void`. `SqlStorageDriver` captures the auto-increment pk via `lastInsertId` and `EntityRepository::doSave()` back-fills the entity before `POST_SAVE` dispatches, so listeners observe the real id on new rows. Removes the need for downstream reload-by-uuid workarounds (waaseyaa/giiken#57).

### Fixed

- `northcloud`: map `NcHitSupportDiagnosticsInterface` as public and add missing public surface dispositions (#1265).
- Root `composer.json`: require `waaseyaa/northcloud` so the package resolves in monorepo installs.

## [0.1.0-alpha.121] - 2026-04-10

### Fixed

- `inertia` `RootTemplateRenderer`: emit `data-page="app"` so the Inertia client
  (`getInitialPageFromDOM`) resolves the initial page from the JSON script tag.
  The previous `data-page="true"` value did not match the default app id and
  yielded a null initial page.

### Changed

- Project skeleton `composer.json`: set `process-timeout` to `0` so long-running
  Composer scripts (e.g. dev servers) are not stopped after 300 seconds.

## [0.1.0-alpha.120] - 2026-04-10

### Added

- `HydratableFromStorageInterface` on `User` and `Node` with `make()`, `fromStorage()`,
  and `duplicateInstance()` preserving field definitions where required. (#1222)
- Integration tests for shipped-entity `duplicate()` / `with()` re-entry and for
  `EntityInstantiator` hydration of `User` / `Node`. (#1222)
- `make:entity-type` scaffolds content entities with widened constructors and
  hydratable stubs. (#1222)

### Changed

- All shipped `ContentEntityBase` / `ConfigEntityBase` subclasses (engagement,
  media, menu, messaging, node, note, path, relationship, taxonomy, user,
  workflows, `Pipeline`, etc.) accept optional `entityTypeId`, `entityKeys`, and
  `fieldDefinitions` so `duplicate()` / `with()` / `withValues()` no longer hit
  `ArgumentCountError`. (#1222)
- `Node` and `Media`: `datetime_immutable` casts with Unix storage for
  created/changed fields; SSR `DateFormatter` accepts `DateTimeInterface`.
  (#1222)
- `User`: `email_verified` bool cast; `status` remains int for validation.
  (#1222)
- `Term` accessors route through `get()` / `set()` only. (#1222)
- `entity-system.md` and related specs: P3 constructor arity / hydratable
  notes. (#1222)

### Fixed

- `phpstan-baseline.neon`: drop stale `MakeEntityTypeCommand` ignores after
  boolean cast on `--content`. (#1222)

## [0.1.0-alpha.119] - 2026-04-10

### Added

- Derive Symfony validation constraints from `EntityType` field definitions
  (`EntityTypeValidationConstraints`). (#1216)
- Pluggable entity clock and timestamp population on save. (#1218)
- `ContentEntityBase::duplicate()`, `with()`, and `EntityValuesSnapshot` for
  snapshot/immutable-style workflows. (#1187, #1217)
- Bridge typed-data coercion with `ValueCaster` at the entity boundary.
  (#1185, #1219)
- Value-object field casts: `CastDefinition`, `ValueCaster` pipeline updates,
  and related `entity` / `typed-data` support. (#1184, #1220)
- `waaseyaa/testing`: `EntityTypeFixtureValues` and
  `EntityFactory::defineFromEntityType()` for fixtures and seeds. (#1186,
  #1221)

### Fixed

- Root `composer.lock`: resolve `waaseyaa/access` to `dev-main` so monorepo
  `composer install` satisfies internal `^0.1` constraints (restores CI and
  release `composer install`).

## [0.1.0-alpha.116] - 2026-04-09

### Fixed

- `waaseyaa/ssr` / `waaseyaa/foundation`: `SsrPageHandler::dispatchAppController()`
  now accepts `InertiaPageResultInterface`, matching `ControllerDispatcher`:
  full HTML via `InertiaFullPageRendererInterface` on first visit, JSON with
  Inertia headers when `X-Inertia: true`, and a JSON error if the full-page
  renderer is not registered. App routes registered via
  `ServiceProvider::routes()` that return `InertiaResponse` no longer fall
  through to a generic 500 HTML body. `HttpKernel::getInertiaFullPageRenderer()`
  exposes the shared renderer; `SsrServiceProvider::configureHttpKernel()`
  injects it into `SsrPageHandler`. (#1179)

## [0.1.0-alpha.107] - 2026-04-06

### Fixed

- `waaseyaa/graphql`: move `AbstractGraphQlSchemaContractTestCase` out of the
  production autoload. The class lived at `src/Testing/` and was reached by
  the PSR-4 classmap under `Waaseyaa\GraphQL\`, but it `extends
  PHPUnit\Framework\TestCase` — a dev-only dependency. In consumer production
  installs (`composer install --no-dev`) the `PackageManifestCompiler` class
  scan hit this file via Reflection and threw `Class
  "PHPUnit\Framework\TestCase" not found`, crashing kernel boot with
  "Application failed to boot." Moved the file to a top-level `testing/`
  directory registered under `autoload-dev` only. Consumers installing with
  dev deps still get it via `Waaseyaa\GraphQL\Testing\`; production installs
  never see it. Discovered via a production outage on waaseyaa/minoo after
  bumping to alpha.106.

- `waaseyaa/cli`: ship `bin/waaseyaa` entrypoint with the package.
  Previously the package declared commands (`serve`, `migrate`, `install`,
  etc.) and a `WaaseyaaApplication` console class but no bootstrap script, so
  `vendor/bin/waaseyaa` could not be generated for consumers. Added
  `packages/cli/bin/waaseyaa` and declared it in the package's composer.json
  `bin` array. Zero-config `vendor/bin/waaseyaa serve` now works in any
  consumer project.

- `waaseyaa/user`: `UserServiceProvider` no longer throws a `RuntimeException`
  during boot when `config.app.url` is absent. Falls back to the `APP_URL`
  environment variable, then to `http://localhost:8000`. `app.name` falls
  back to `APP_NAME` then to `Waaseyaa`. Restores zero-config boot for
  convention-following apps.

- `waaseyaa/foundation`: `ControllerDispatcher` now normalizes Symfony-style
  array callables (`['_controller' => [Foo::class, 'bar']]`) to `Class::method`
  string form before the domain router chain runs. Previously, routes declared
  with the array form crashed inside `str_contains()`/`str_starts_with()` in
  `JsonApiRouter::supports()`, `EntityTypeLifecycleRouter::supports()`, and
  other domain routers that match against `_controller`. `JsonApiRouter`
  additionally gets a defensive `match()` so misrouted arrays cannot explode.

## [v0.1.0-alpha.36] — 2026-03-20

### Added

- `ServiceProvider::setKernelResolver()` — kernel-level fallback resolver for
  cross-layer DI. Singleton closures can now resolve `EntityTypeManager`,
  `DatabaseInterface`, `EventDispatcherInterface`, and cross-provider bindings
  without manual wiring. Set automatically by `AbstractKernel` during boot.

## [v0.1.0-alpha.35] — 2026-03-20

### Fixed

- `SsrPageHandler::resolveControllerInstance()` now checks if the controller
  class itself is registered via the service resolver before falling back to
  reflection-based parameter resolution. Fixes controllers with ambiguous
  constructor types (e.g., `EntityRepositoryInterface`) that are pre-wired as
  singletons in service providers.

---

## [1.0.0] — 2026-03-13

This release consolidates all v1.0 milestone work including eight pre-release
review sprints. It is the first stable, production-ready release of the
Waaseyaa framework.

### RC Verification (2026-03-13)

Playwright MCP live browser smoke test passed on `release/v1.0-rc`:

| Test | Result |
|------|--------|
| Dashboard renders (header, sidebar, language switcher) | ✅ |
| Login page renders correctly | ✅ |
| Auth middleware: unauthenticated `/` → `/login` | ✅ |
| Auth middleware: all protected routes redirect | ✅ |
| Login error handling shows alert | ✅ |
| SSR homepage renders (dark theme, hero, cards) | ✅ |
| SSR admin link → `http://localhost:3000` | ✅ |
| SSR footer uses `<footer>` element | ✅ |
| JSON:API `/api/node` returns valid response | ✅ |
| SSR 404 page with path interpolation | ✅ |
| Admin SPA production build (2.23 MB / 545 kB gzip) | ✅ |
| PHP test suite (4352 tests) | ✅ |
| TypeScript test suite (78 tests) | ✅ |

**Known issue:** Vue hydration mismatch warning on auth-protected pages (#380,
post-v1.0). Non-blocking — page corrects itself after hydration.

### Added

- **Admin Auth UI** (`packages/admin`, `packages/user`) — Login page, `useAuth`
  composable with `checkAuth()` deduplication, global auth middleware with SSR
  guard, and backend `AuthController` with `/auth/login`, `/auth/logout`,
  `/auth/me` endpoints (PR #374, closes #330).
- **Access policies** for `menu`, `path_alias`, and `relationship` entity types
  with anonymous-account test coverage (PR #375, closes #327).
- **`RenderController::renderServerError()`** — 500 response with `500.html.twig`
  template fallback; bundled `500.html.twig` and `403.html.twig` templates
  (PR #377, closes #322/#334).
- **Top-level exception handler** — `HttpKernel` catches boot failures and returns
  a structured JSON:API error response without crashing the process (PR #376,
  closes #329).
- **Package READMEs** — every one of the 40 framework packages now ships a
  `README.md` with accurate class names and usage examples (PR #378, closes #333).
- **Admin SPA stabilization** — fixed SsrPageHandler DI resolution for
  service-provider-registered controllers (#407), corrected bootstrap URL to
  `/admin/bootstrap` with dev proxy rule (#408), switched admin to client-only
  SPA (`ssr: false`) removing experimental `viteEnvironmentApi` flag (#406),
  fixed error page i18n plugin dependency (#409), `composer dev` auto-sets
  `APP_ENV` and `WAASEYAA_DEV_FALLBACK_ACCOUNT` (#410).
- **Admin bridge package** (`waaseyaa/admin-bridge`) — PHP bridge providing
  bootstrap controller, value objects, and service provider for the admin SPA
  host contract, published to Packagist via monorepo split.
- **i18n infrastructure** — `Translator` and `TranslationTwigExtension` with
  Twig dependency for server-side localization.
- **Telescope codified context observability** — real-time spec/CLAUDE.md diff
  view, context health scores, and drift detection in the admin SPA (#353).
- **SSR `InteractsWithRenderer` test trait** — assertion helpers for template
  rendering in integration tests (#313).
- **Base SSR layout template** and framework Twig extension (#312, #311).
- **`waaseyaa/mail` package** — basic mailer abstraction (#307).
- **MCP codified context server** — exposes subsystem specs to AI tooling (#212).
- **`waaseyaa/note` package** — `core.note` default content type with full RBAC
  and field-level ACL (#195, #199).
- **Operator diagnostics** — `DiagnosticCode` enum, `DiagnosticEmitter`,
  health CLI commands, schema drift detection (#227, #256–#260).
- **Ingestion pipeline defaults** — envelope format, strict validation, logging,
  canonical error codes (#225, #246–#250).
- **Schema registry** — `DefaultsSchemaRegistry`, `schema:list` CLI (#229).
- **Entity type lifecycle** — enable/disable with audit log and CLI (#198).
- **RBAC** — full role-based access control via `#[PolicyAttribute]` (#199).
- **Entity write audit trail** — `EntityAuditLogger` and listener (#208).
- **`waaseyaa/search` package** — DTOs, `SearchProviderInterface`, Twig extension
  (#193).
- **Security defaults** — route-level auth enforcement, CI secrets check (#234).
- **Application Directory Convention v1.0** in skeleton and docs (#276).

### Fixed

- **SSR hydration errors** — `useLanguage` replaced `localStorage` with
  `useCookie()` + `useState()` for SSR-safe persistent locale (PR #370, closes
  #325).
- **SSR homepage admin link** — `/admin` link updated to `http://localhost:3000`
  (the SPA dev server URL); broken 404 resolved (PR #367, closes #328).
- **`Content-Type` header in outer catch** — corrected from `application/json` to
  `application/vnd.api+json`; added stack trace to `error_log()` (PR #376, closes
  #329).
- **Auth dead ternary** — `me()` returned identical branches regardless of account
  type; simplified to single `getRoles()` call (PR #374, closes #330).
- **Auth `findUserByName`** — added `.condition('status', 1)` to exclude disabled
  accounts from login (PR #374, closes #330).
- **Logout security** — replaced `unset($_SESSION['waaseyaa_uid'])` with
  `session_destroy()` + `session_regenerate_id(true)` (PR #374, closes #330).
- **`MenuAccessPolicy` reason string** — misleading message corrected to
  `'View access not granted to unauthenticated users.'` (PR #375, closes #327).
- **SSR integration tests** — updated to expect HTTP 403 for unpublished nodes and
  unauthenticated preview (PR #377, closes #322).
- **`home.html.twig` footer element** — `<div>` corrected to `<footer>` (PR #377).
- **Foundation→Path layer violation** — removed cross-layer import (#357, closes
  #315).
- **Validation→Entity layer violation** — removed cross-layer import (#360, closes
  #316).
- **Stored XSS** — HTML sanitized in `HtmlFormatter` (#355, closes #318).
- **CSRF exemption** — JSON:API `application/vnd.api+json` and MCP endpoint
  correctly bypass CSRF validation (#354, #359, closes #317, #320).
- **Single-tenant enforcement** — multi-tenant registration blocked for v1.0
  (#358, closes #321).
- **`NoteInMemoryStorage::create()` return type** (#356, closes #314).

### Changed

- `SsrHttpKernelIntegrationTest` — forbidden-access assertions updated from 404
  to 403 to match intentional PR #371 behaviour change.
- `ControllerDispatcher` — replaced `array_filter()` with explicit `isset()`
  payload construction; null-guarded body parameter.
- `useAuth` middleware — uses `checkAuth()` instead of `fetchMe()` to avoid
  redundant network requests on every navigation.
- `login.vue` — removed SSR-time redirect (caused hydration mismatch); redirect
  now happens client-side only.

### Security

- Logout now invalidates the full PHP session (`session_destroy()`) and regenerates
  the session ID to prevent session fixation attacks.
- HTML output sanitized in `HtmlFormatter` to prevent stored XSS.
- CSRF token checked for all non-exempt content types.
- MCP endpoint explicitly exempted from CSRF (machine-to-machine).

---

## [0.4.0] — 2025-xx-xx

Previous stable milestone. See git history for details.

## [0.2.0] — 2025-xx-xx

See git history for details.

## [0.1.0] — 2025-xx-xx

Initial public release.

[1.0.0]: https://github.com/waaseyaa/framework/compare/v0.4.0...release/v1.0-rc
[0.4.0]: https://github.com/waaseyaa/framework/compare/v0.2.0...v0.4.0
[0.2.0]: https://github.com/waaseyaa/framework/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/waaseyaa/framework/releases/tag/v0.1.0
