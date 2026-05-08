# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Native CLI kernel replaces Symfony Console (mission `native-cli-kernel-01KR2NR7`, merge `cc36dfcd2`)** — `Waaseyaa\CLI\` is now the sole CLI runtime. `symfony/console` removed from runtime first-party requires (still pulled by `friendsofphp/php-cs-fixer` as a dev-only transitive). All ~74 shipped commands across 17 clusters (health/schema, migrate, make, optimize, queue, telescope, schedule+perf, entity/type, user/permission, ingest/search/semantic, config/cache/db/audit, bundle/fixture, scaffolds, misc, northcloud) ported to native `CommandDefinition` + `Handler` pattern behind `HasNativeCommandsInterface`. Public command names, argument signatures, option signatures, and `--help` output preserved byte-for-byte against the WP01 baseline (71 snapshot fixtures gate this in `packages/cli/tests/Integration/Snapshot/`). New `HelpRenderer` produces Symfony-equivalent output: declaration-order user options, kernel flags appended in fixed order (`--silent`, `-q/--quiet`, `-V/--version`, `--ansi|--no-ansi`, `-n/--no-interaction`, `-v|vv|vvv`), `[default: …]` before `(multiple values allowed)`, JSON-encoded array defaults (`["node"]`), no description wrapping when stdout is piped (`stream_isatty(STDOUT)` check). New `CliKernel` / `CliApplication` / `CliTester` replace Symfony `Application` / `CommandTester`. Legacy `WaaseyaaApplication`, `CliCommandRegistry`, `HasCommandsInterface`, and the dual-boot `LegacySymfonyCommandRegistrar` bridge are deleted. `ConsoleKernel` collapsed to a thin delegate to `CliApplication::run()`. `AbstractKernel::buildHandlerContainer()` provides PSR-11 handler resolution with explicit kernel bindings + reflection auto-wire. NFR-001 (wall-time ≤110% of pre-cut) and NFR-002 (memory ≤+4 MiB) both pass: `bin/waaseyaa list` and `bin/waaseyaa health:check --json` both at 0.01 s wall / 30336 KB peak (median of 10). New spec at `docs/specs/cli-kernel.md`. One known UX change: `bin/waaseyaa list` now exits "Unknown command: list" because `list` was a Symfony built-in, not a Waaseyaa-shipped command — operators should use `bin/waaseyaa --help` or `bin/waaseyaa` (no args). Locked by 7496 tests / 18114 assertions / 0 warnings.

### Fixed

- **`TelescopeServiceProvider` class hierarchy + `AccessPolicyRegistry` reflection heuristic + `AuditLogHandlerTest` teardown (commit `17a65ef6e`)** — `packages/telescope/src/TelescopeServiceProvider.php` now `extends ServiceProvider` and binds itself as a singleton in `register()`; previously it was registered in `composer.json` as a service provider but didn't extend the base, emitting `[warning] Class Waaseyaa\Telescope\TelescopeServiceProvider is not a ServiceProvider` on every kernel boot. Internal `$config` renamed to `$telescopeConfig` to avoid colliding with the parent's `protected $config`. `AccessPolicyRegistry::discoverAccessPolicies()` (`packages/foundation/src/Kernel/Bootstrap/`) reflection heuristic that blindly passed `string[]` of entity types to any required-param policy ctor now inspects the first param's type: `array` → pass entity types, anything else → log `[error] Failed to instantiate access policy …` once and skip (manual registration required). Fixes `Waaseyaa\Attachment\Policy\ParentDelegatedAccessPolicy::__construct(): Argument #1 ($entityTypeManager) must be of type Waaseyaa\Entity\EntityTypeManagerInterface, array given` runtime error. `AuditLogHandlerTest::tearDown` replaced `glob + unlink` with a recursive `removeDirectory()` helper to handle the nested `storage/framework/entity-audit.jsonl` written by `EntityAuditLogger`, eliminating 4 PHPUnit warnings that were strict-failing CI. `docs/specs/infrastructure.md` updated with the new policy-discovery semantics.

- **`Worker::run()` memory guard vs host RSS (#1397)** — `WorkerOptions::$memoryLimit` is now enforced as **additional** allocation since the start of each `Worker::run()` call (baseline `memory_get_usage(true)`), not total PHP process memory. Long-running PHPUnit processes (and other embedded hosts) often exceed the default 128 MiB before `run()` begins, which previously made `shouldContinue()` exit immediately: `QueueIntegrationTest::workerRunProcessesMultipleJobsThroughDbalTransport` and `workerRunMixesSuccessAndFailure` returned `$processed === 0` under the full suite while passing in isolation. `runNextJob()` unchanged (no loop guard).

### Changed

- **Routing + HTTP resolver wiring (`foundation-symfony-fallback-elimination-01KQZR1` WP03)** — `WaaseyaaRouter::match()` now wraps Symfony `UrlMatcher` failures as `Waaseyaa\Routing\Exception\RouteNotFoundException` / `RouteMethodNotAllowedException`, and `HttpKernel` catches those Waaseyaa types for JSON 404/405 instead of `Symfony\Component\Routing\Exception\*`. `HttpKernelServiceResolver` takes a `KernelServicesInterface` instance and resolves `DatabaseInterface` through it (same semantics as `ProviderRegistryKernelServices`, no duplicate if-chain). Call sites and tests that expected Symfony `ResourceNotFoundException` directly from `WaaseyaaRouter::match()` should expect the new routing exceptions instead.

- **`_controller` array defaults (`foundation-symfony-fallback-elimination-01KQZR1` WP04)** — `RouteBuilder::controller()` accepts `[FQCN, method]` and stores `FQCN::method`. `HttpKernel` applies `RouteBuilder::normalizeControllerDefault()` when merging route match parameters onto the request. `ControllerDispatcher` no longer normalizes array controllers (single locus in `waaseyaa/routing` + match handoff).

## [0.1.0-alpha.174] - 2026-05-07

### Fixed

- **CSRF for Inertia file uploads (mission `inertia-file-upload-csrf-01KQZJQJ`, merge `0edb89c`)** — `CsrfMiddleware` (`packages/user/src/Middleware/`) and `HttpKernel` (`packages/foundation/src/Kernel/`) now together expose the session CSRF token to JavaScript via an `XSRF-TOKEN` cookie set on text/html responses, and accept that cookie's URL-decoded value from an `X-XSRF-TOKEN` request header alongside the existing `_csrf_token` POST field and `X-CSRF-Token` header. Inertia's bundled axios client auto-forwards the `XSRF-TOKEN` cookie as `X-XSRF-TOKEN` on every state-changing request, so consumer apps using `forceFormData: true` for multipart file uploads now work against CSRF-protected routes with **zero application-level CSRF code**. Previously these requests were silently rejected with 403 Invalid Security Token because the CSRF middleware only exempted `application/json` (Inertia's default) and the token had no JS-reachable surface. Cookie attributes per the binding contract: `HttpOnly=false` (required to be JS-readable), `SameSite=Lax`, `Path=/`, no `Domain`, session lifetime, value `rawurlencode($_SESSION['_csrf_token'])`, `Secure` flag tracks `$request->isSecure()`. The cookie writer is invoked from `HttpKernel::serveHttpRequest()` after controller dispatch (`CsrfMiddleware::attachCookieIfHtml` static helper) so it sees the actual response, not the auth-pipeline pass-through. Token comparison uses `hash_equals` on every accepted source; `rawurldecode` runs exactly once on `X-XSRF-TOKEN`. JSON exemption logic is structurally unchanged. Locked by 23 new unit tests covering the full Content-Type × token-source matrix and every cookie attribute (`packages/user/tests/Unit/Middleware/CsrfMiddlewareTest.php` — 41 tests / 211 in package), one new integration test exercising the contract through the live `HttpKernel` pipeline (`tests/Integration/Phase13/InertiaMultipartCsrfIntegrationTest.php`), real cross-repo smoke against giiken's Ingestion UI (evidence in `kitty-specs/inertia-file-upload-csrf-01KQZJQJ/artifacts/`), and a developer-facing convention page at `docs/conventions/csrf-token-cookie.md`. Two known follow-ups: trusted-proxy / `X-Forwarded-Proto` registration is not yet wired in `HttpKernel`, so the `Secure` flag on TLS-terminated deployments depends on raw `$_SERVER['HTTPS']`; and the original instance-method `attachXsrfCookie` runs in-pipeline against a discarded auth-pipeline response and is now redundant with `attachCookieIfHtml` (cleanup pending).

## [0.1.0-alpha.173] - 2026-05-05

### Fixed

- **Implicit-array controller signature compatibility (#1390)** — `AppParameterBindingBuilder` (`packages/ssr/src/Http/AppController/`) added a hard rejection for unannotated `array` parameters in alpha.171/172, breaking every consumer using the historical canonical signature `function show(array $params, array $query, AccountInterface $account, Request $request)` — Minoo alone had 184 affected methods across 37 controller files. A name-keyed compatibility shim now restores the alpha.170 behaviour: unannotated `array $params` defaults to `#[MapRoute]`, unannotated `array $query` defaults to `#[MapQuery]`. Each shim hit emits one structured `LoggerInterface::notice` per `(controller_class, method, parameter_name)`, deduplicated within the binding-builder's lifetime via a `private array $emittedKeys` field; under FPM the effective envelope is once per triple per worker lifetime because `AppControllerMethodInvoker::$specCache` (`private static`) caches the built spec and bypasses the builder on subsequent requests for the same route. Payload keys are `channel`, `event`, `controller_class`, `method`, `parameter_name`, `recommended_attribute` so consumer tooling can inventory migration debt. Other unannotated `array` parameters now bind to `[]` and emit a single `implicit_array_unbound` notice with `recommended_attribute=''` (no exception), giving consumers a non-fatal migration signal that the parameter name does not disambiguate the binding. Mirrors the alpha.165 `tenancy:` migration ergonomics. Locked by ten new tests in `packages/ssr/tests/Unit/Http/AppController/AppParameterBindingBuilderTest.php` and `packages/ssr/tests/Integration/AppControllerImplicitArrayDispatchTest.php`.

## [0.1.0-alpha.172] - 2026-05-05

### Fixed

- **Kernel boot for `groups` and `taxonomy` consumers (#1388)** — `GroupsServiceProvider` and `TaxonomyServiceProvider` constructed core `FieldDefinition` instances on their config bundle entity types (`group_type`, `taxonomy_vocabulary`) without declaring `targetEntityTypeId`. The alpha.171 binding invariant — `FieldDefinitionRegistry::registerCoreFields()` requires `FieldDefinition::getTargetEntityTypeId() === $entityTypeId` — correctly rejected the bind, which prevented kernel boot in any consumer registering groups or taxonomy. Three call sites patched (`GroupsServiceProvider.php` description for `group_type`; `TaxonomyServiceProvider.php` description and weight for `taxonomy_vocabulary`). Discovered while upgrading Minoo to alpha.171 (mission `upgrade-waaseyaa-alpha-171-01KQTDC2` WP03). Locked by four new regression tests in `packages/groups/tests/Unit/`, `packages/taxonomy/tests/Unit/`, `packages/field/tests/Unit/FieldDefinitionRegistryInvariantTest.php`, and `tests/Integration/Phase27/FieldDefinitionInvariantTest.php` — the integration sweep walks every framework provider's entity types through a real `FieldDefinitionRegistry` to catch any future regression of this class.

### Changed

- **Spec hygiene** — `docs/specs/entity-system.md` now documents the alpha.171+ field-binding invariant explicitly: every `FieldDefinition` passed to `FieldDefinitionRegistry::registerCoreFields()` or `registerBundleFields()` must declare `targetEntityTypeId === $entityTypeId` (and `targetBundle === $bundle` for the bundle path), with the `\InvalidArgumentException` message format named for both code paths. Points readers at `packages/genealogy/src/GenealogyFieldDefinitions.php` as the canonical clean pattern.

### Notes

- **Migration aid for consumers upgrading from alpha.165 → alpha.171 → alpha.172.** Three reconciliations already shipped in alpha.171 may still bite consumers crossing that gap (restated for posterity; not regressions of alpha.172):
  - `EntityType` constructor named param renamed: `fieldDefinitions:` → `_fieldDefinitions:`.
  - `ServiceProvider::setKernelResolver()` removed; replaced by `setKernelServices(KernelServicesInterface)` plus `mergeChildProvider()`.
  - `Waaseyaa\Api\JsonResponseTrait` redesigned (single `jsonApiResponse()` returning `application/vnd.api+json`); previous `json()` / `jsonBody()` surface dropped.

## [0.1.0-alpha.171] - 2026-05-04

### Fixed

- **Published artifact resolves cleanly on Packagist** — root `composer.json` (published as `waaseyaa/framework`) had been shipping with 63 `"waaseyaa/*": "@dev"` constraints that consumers cannot resolve. alpha.170 broke skeleton-smoke when Packagist's transitive resolution failed to find a matching sibling; the failure is non-deterministic from the consumer's perspective. Replaced all 63 `waaseyaa/*` requires plus the one `require-dev` (`waaseyaa/testing`) with `self.version`. Composer resolves `self.version` to `dev-main` against local path repos and to the exact tag version when Packagist crawls the tag, giving consumers exact-matching siblings without a release-time rewrite step. Same pattern Symfony, Doctrine, and Sylius use for their root metapackages. (#1382, #1383)

### Changed

- **Composer policy expanded** — `bin/check-composer-policy` now codifies CP002 (`@dev` forbidden in root and `packages/*`), CP005 (skips `self.version` since exact match is strictly tighter than any range), and CP006 (`self.version` allowed only in root). Includes incidental fix for a worktree-filter bug that silently scanned zero files when the policy ran from inside a `.worktrees/` directory. (#1382, #1383)

## [0.1.0-alpha.170] - 2026-05-03

### Fixed

- **Packagist crawl unblocked** — root `composer.json` carried `"version": "1.1.0"` (added in PR #385 to source AboutCommand from a canonical place). Packagist refused every release tag from v0.1.0-alpha.145 onward with `Skipped tag v0.1.0-alpha.169, tag (0.1.0.0-alpha169) does not match version (1.1.0.0) in composer.json`, leaving the package frozen at alpha.144 since 2026-04-15. ~3 weeks of silent release outage. Removing the field; Composer now derives the version from the git tag (or `dev-main` for development checkouts) and AboutCommand reads the same source automatically. ConsoleKernelVersionTest still passes.
- **Dead-code check in `GenealogyContentAccessPolicy::viewAccess`** — second `isEntityPublic($entity)` call was provably true at the call site (line 95 already AND'd it into the `$published` gate). Replaced the redundant `if`/return with the unconditional return; `return AccessResult::neutral()` was unreachable and removed. phpstan now clean (the error was masked by result-cache reuse on prior pre-push runs).

### Changed

- **`Publish to Packagist` workflow** (replaces the manual `Trigger Packagist Update`) — now auto-fires on every `v*` tag push, dynamically discovers all `waaseyaa/*` packages from `packages/*/composer.json` (no hardcoded matrix to drift), runs the Packagist update API in a 6-wide matrix, and a verify job polls the p2 endpoint until the just-cut tag is visible. Stays red as a clear signal whenever Packagist crawls stall; goes green automatically once Packagist is healthy.

## [0.1.0-alpha.169] - 2026-05-03

### Changed

- **`skeleton-smoke` CI now pins to the exact upstream tag**: previously `composer create-project --stability=alpha` resolved to whatever Packagist had indexed, which lagged the just-cut tag by minutes — every auto-triggered run ran against the *previous* release. The workflow now resolves a target version (workflow_dispatch input or upstream sync-skeleton tag), runs `composer require waaseyaa/framework:<version>` with a 12×60s retry loop that bridges the Packagist crawl window, and verifies the installed version matches before running migrations and the entity round-trip. Verifies the smoke is actually exercising the release it claims to.

## [0.1.0-alpha.168] - 2026-05-03

### Fixed

- **Kernel boot crashed in Packagist installs that lack PHPStan**: `packages/entity/src/PhpStan/FieldAttributeRule.php` extended `PHPStan\Rules\Rule` (a dev-only dependency) but lived under the package's production PSR-4 autoload path. `PackageManifestCompiler::scanClasses()` reflectively loaded it during kernel boot and crashed with `Interface "PHPStan\Rules\Rule" not found`. Moved to `packages/entity/testing/PhpStan/` and registered under `autoload-dev` only — same fix as the alpha.106 → alpha.107 graphql incident on minoo. Surfaced by skeleton-smoke (#1315) immediately after the #1375 fix landed.

## [0.1.0-alpha.167] - 2026-05-03

### Fixed

- **Entity round-trip via repository → driver path** (#1375): `SqlStorageDriver::write()` now splits entity values into existing columns vs the `_data` JSON blob, and `read()` / `readMultiple()` (plus translation variants) re-merge `_data` keys back onto the row before returning. Previously, `EntityRepository::doSave()` passed raw entity values straight to `DBALInsert::execute()`, which crashed for any entity whose declarative `#[Field]` attributes lacked dedicated columns — `User` save fails with `table user has no column named mail` was the canonical reproducer. The legacy `SqlEntityStorage::save()` path already did this routing internally; this commit ports the same convention to the modern repository path. New regression test `tests/Integration/EntityStorage/RepositoryUserRoundTripTest.php`. First caught by the skeleton-smoke CI (#1315) against alpha.166.

## [0.1.0-alpha.166] - 2026-05-03

### Added

- **Packaged-form smoke CI** (#1315 Criterion B): new `.github/workflows/skeleton-smoke.yml` auto-fires after `Sync Application Skeleton` succeeds on each release tag. Runs `composer create-project waaseyaa/waaseyaa` against Packagist (with crawl-lag retry), executes migrations, then a User entity save/reload round-trip via `tools/skeleton-smoke/smoke.php`. Catches the alpha.148 → alpha.151 class of consumer-install regressions where the framework's source-tree test harness diverges from a real Packagist install.

## [0.1.0-alpha.165] - 2026-05-02

### Added

- **Schema evolution v2 (mission #529)** — closes the v1 surface. End-to-end pipeline for declarative schema diffs:
  - **SchemaDiff algebra** (`Waaseyaa\Foundation\Schema\Diff\*`): atomic ops, `CompositeDiff`, canonical-JSON serialization, SHA-256 checksums.
  - **SQLite compiler** (`Waaseyaa\Foundation\Schema\Compiler\Sqlite\*`): pure `compile(CompositeDiff): CompiledMigrationPlan`. Stable diagnostic codes `RENAME_COLUMN_UNSUPPORTED_SQLITE_LT_3_25`, `ALTER_COLUMN_UNSUPPORTED_SQLITE_V1`, `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`.
  - **Validation gates** (`Waaseyaa\Foundation\Schema\Compiler\Validation\*`): platform-neutral `ValidationDiagnosticCode` (`UNKNOWN_OP_KIND`, `DESTRUCTIVE_OP_BLOCKED`, `ILLEGAL_OP_ORDER`), `PlanPolicy` value type, `OrderingValidator`.
  - **Unified Migrator** (`Waaseyaa\Foundation\Migration\*`): one DAG over legacy `Migration` + new `MigrationInterfaceV2` instances. Q4 tie-break (`package ASC, id ASC`). Stable codes `MIGRATION_CYCLE`, `UNKNOWN_DEPENDENCY`.
  - **Ledger checksum + diff_hash** (WP09): `waaseyaa_migrations` gains nullable `checksum` + `diff_hash` columns. Production `CHECKSUM_MISMATCH` guard refuses silent re-apply with drifted source. Backfill ADR: `docs/adr/008-ledger-checksum-backfill.md` (null-tolerate).
  - **dry-run + verify CLI** (WP10): `bin/waaseyaa migrate --dry-run` previews compiled plans; `--verify` audits ledger checksums. Both support `--json`. Mutually exclusive (`INCOMPATIBLE_FLAGS`). Production output sanitisation strips filesystem paths.
  - **Composer manifest array form** (WP11): `extra.waaseyaa.migrations` now accepts an ordered array of namespace and/or path entries. Recommended for new packages where ordering matters. String path form remains supported indefinitely (Q9). New code `INVALID_MIGRATION_ENTRY`. Discovery rules: `docs/adr/009-migration-manifest-discovery.md`.
  - **Entity-storage diff factory** (WP07): `EntityDiffFactory` produces `EntityLevelDiff` + `BundleLevelDiff` from registered field definitions vs `SchemaSnapshot`. `SqlSchemaHandler::deriveDiffColumnSpec()` is the single source of truth for field-type → column mapping.
  - **SQLite capability matrix**: `docs/specs/sqlite-capability-matrix.md` documents per-version op support.
  - **Regression test pack** (WP08, GitHub #518): six end-to-end integration test files under `tests/Integration/Schema/` lock additive ops, rename semantics, destructive gates, bundle subtables, the K2 invariant, and idempotency including the WP09 checksum guard.

## [0.1.0-alpha.164] - 2026-04-28

### Fixed

- **Split Monorepo CI:** Five packages whose `composer.json` declared `name: waaseyaa/<X>` were missing from `.github/workflows/split.yml` matrix, causing `bin/check-release-tag-parity` to fail with `RP003` on every release tag since they were added: `ai-observability`, `attachment`, `genealogy`, `oidc`, `structured-import`. Added all five to the matrix in their respective layer sections. Created the three missing GitHub repos (`waaseyaa/ai-observability`, `waaseyaa/attachment`, `waaseyaa/structured-import`) so subtree pushes succeed. Second occurrence of split-matrix drift (#1137 was the first); a future refactor should derive the matrix dynamically from `packages/*/composer.json`.

## [0.1.0-alpha.163] - 2026-04-28

### Fixed

- **`ci/unit-tests` Integration step on Linux CI:** Two pre-existing bugs that masked each other in `packages/attachment/tests/Integration/SetActiveConcurrencyTest.php` (skipped on Windows due to `pcntl`, only surfaced on Linux). (1) Line 191 called `$select->fields(['id'])` but `DBALSelect::fields()` signature is `(string $tableAlias, array $fields = [])` — corrected to `->fields('attachment', ['id'])`. (2) Line 196 called `fetchAllAssociative()` on a `Generator` (since `DBALSelect::execute(): \Traversable` yields rows) — wrapped in `iterator_to_array(...)` to materialize the rows into an array for `assertCount()`. ([#1359](https://github.com/waaseyaa/framework/pull/1359), [#1360](https://github.com/waaseyaa/framework/pull/1360))
- **`tests/Integration/Phase4/FieldTypeDiscoveryTest`:** Added `'enum'` to the expected field-type id list and bumped the count from 16 → 17. The test wasn't updated when `EnumItem` was added in mission `field-type-enum-plugin-01KQ6SJG`. ([#1359](https://github.com/waaseyaa/framework/pull/1359))

## [0.1.0-alpha.162] - 2026-04-28 — Attribute-first entity definition (M1)

### Added

- **Single-Entity Work Surface** — six new primitives for downstream apps building per-entity editing workspaces. Full subsystem doc: `docs/specs/work-surface.md`. Mission: `single-entity-work-surface-01KQ7M1P`.
  - `Waaseyaa\Routing\EntityDeepLinkRouteBuilder` — deep-link route helper producing `GET /{segment}/{entityType}/{id}` with entity upcasting wired via `EntityParamConverter`.
  - `Waaseyaa\Field\Attribute\BundleTemplate` and `Waaseyaa\Field\Attribute\FieldTemplate` — declarative bundle field registration via PHP attributes. Property order = registration order. Uniqueness of field keys and normalized prompt aliases enforced at compile time.
  - `Waaseyaa\Field\BundleTemplateCompiler` — attribute-driven field discovery → `FieldDefinitionRegistry::registerBundleFields()`. Idempotent.
  - `PUT /api/{entityType}/{id}/field/{key}` — per-field auto-save endpoint (`FieldAutoSaveController`). Status code matrix: 200/401/403/404/415/422. Body size cap 65 536 bytes (NFR-002).
  - `Waaseyaa\Field\Form\FormDescriptorBuilder` — schema-driven form descriptor builder. Returns `list<FormFieldDescriptor>` in registry insertion order; no HTML emitted.
- **`waaseyaa/attachment`** — new package at Layer 2. `Attachment` content entity for files attached to a parent entity. `AttachmentRepository::setActive()` enforces the at-most-one-active invariant via a two-UPDATE transaction. `ParentDelegatedAccessPolicy` delegates view/update/delete decisions to the parent entity's registered policy.
- **`waaseyaa/structured-import`** — new package at Layer 3. `StructuredImporterInterface` + `GfmTableImporter` (in-house GFM 2-column table parser, no CommonMark dependency). Matches prompts to field keys via `promptAliases`; normalization is UTF-8 lowercase + whitespace collapse (no transliteration — C-012).

### Changed

- **`Waaseyaa\Field\FieldDefinition`** constructor gained two trailing optional parameters: `string $group = ''` and `array $promptAliases = []`. `FieldDefinitionInterface` gained `getGroup(): string` and `getPromptAliases(): array`. Custom `FieldDefinitionInterface` implementations must add the two new methods. See `UPGRADING.md` § "FieldDefinition constructor parameters added". Per `DIR-003`, no compatibility shim is provided. ([single-entity-work-surface-01KQ7M1P](kitty-specs/single-entity-work-surface-01KQ7M1P/spec.md))
- **`FieldTypeInferrer::isCompatible()`** is now a public seam (was private). Both the runtime inferrer and the `FieldAttributeRule` PHPStan extension delegate to it, so static analysis and runtime share a single source of truth — including the new asymmetric `int`/`?int`/`string`/`?string` → `entity_reference` override rule. The symmetric `compatibilityGroups()` seam is unchanged. Closes transitional gap #3 in `docs/specs/entity-system.md`. `Node.uid` and `Term.parent_id` are migrated from untyped + `@var int|null` PHPDoc to typed `public ?int $... = null;`. ([inferrer-entity-reference-compat-01KQ6SC0](kitty-specs/inferrer-entity-reference-compat-01KQ6SC0/spec.md))

---

- Charter directive **DIR-003 (Greenfield Removal Policy)** hoisted from
  a sub-bullet inside DIR-001 into its own top-level directive, so it
  surfaces in compact charter context loaded by every `/spec-kitty.specify`
  and `/spec-kitty.plan` invocation. No policy change — the alpha-phase
  greenfield removal rule was already charter law inside DIR-001. See
  `.kittify/charter/charter.md`. (mission `charter-greenfield-directive-01KQ7MN5`)

### Breaking changes

- `Waaseyaa\Entity\EntityType` constructor no longer accepts a `fieldDefinitions:` parameter. Field definitions must come from `#[Field]`-decorated entity properties via `EntityType::fromClass(MyEntity::class)`. Tests that need to inject raw field definitions for fixture entity types can use `Waaseyaa\Entity\Tests\Helper\TestEntityType::stub()`.
- `Waaseyaa\Entity\EntityTypeManager::assertClassMetadataMatchesEntityType()` removed. With a single source of truth (the entity class itself), the validator has no purpose.
- **`enum` field-type plugin (mission `field-type-enum-plugin-01KQ6SJG`):** `FieldTypeInferrer` now emits `type: 'enum'` for backed-enum-typed properties, replacing the transitional `type: 'string' + settings.enum_class` bridge. `FieldDefinitionConstraintBuilder` no longer recognises `settings.enum_class` on `'string'`-typed fields; the setting is honored only on `'enum'`-typed fields. The `enumClass` (camelCase) settings alias has been removed; use `enum_class` (snake_case). Explicit `type='string'` annotation on a backed-enum property is no longer accepted by the inferrer. The transitional bridge documented in `docs/specs/entity-system.md` §"Known Transitional Gaps" is closed.

### Added

- `Waaseyaa\Entity\Attribute\Field` — declare entity fields directly on typed PHP properties; `name:` / `type:` / `required:` / `default:` / `settings:` are all optional and fall through to inference from the property's PHP type.
- `Waaseyaa\Entity\EntityType::fromClass(string $class, ...$overrides): self` — static factory that builds an `EntityType` from a class's `#[ContentEntityType]` / `#[ContentEntityKeys]` / `#[Field]` attribute metadata. Named overrides after `$class` win over inferred slots.
- `Waaseyaa\Entity\Attribute\ContentEntityType` extended with `label` and `description` parameters so the human-facing strings live on the class alongside the id.
- `Waaseyaa\Entity\Attribute\FieldTypeInferrer` — maps PHP property types to canonical field types (`string`, `integer`, `boolean`, `datetime`, `json`, etc.) with override-friendly compatibility groups.
- `Waaseyaa\Entity\Tests\Helper\TestEntityType::stub()` — test-only helper for raw-shape `EntityType` construction (replaces direct use of the removed `fieldDefinitions:` parameter in fixtures).
- `enum` field-type plugin (`packages/field/src/Item/EnumItem.php`) for backed-enum-typed fields. Validates against the declared enum, emits JSON Schema with explicit `enum: [...]`, and surfaces case labels via the optional `LabeledCase` interface (`packages/field/src/Item/LabeledCase.php`).
- `Waaseyaa\Field\FieldTypeInterface::jsonSchemaFor(FieldDefinition $definition): array` and `schemaFor(FieldDefinition $definition): array` — per-definition schema resolution seams. Default implementations preserve the existing per-type behavior; plugins can override to specialize on `settings`.
- `Waaseyaa\Entity\PhpStan\FieldAttributeRule` — custom PHPStan rule that lints `#[Field]` attribute usage at static-analysis time, mirroring `FieldTypeInferrer::infer()` runtime checks. Detects non-public properties, un-inferable / union / intersection types, unknown type ids, incompatible type overrides, and `#[Field]` placed on classes that don't extend `ContentEntityBase`. Error messages string-equal the runtime `EntityMetadataException` text. Registered via `packages/entity/phpstan-rules.neon`; downstream apps opt in by adding `- vendor/waaseyaa/entity/phpstan-rules.neon` to their own `phpstan.neon` `includes:`. Two additive public helpers (`FieldTypeInferrer::compatibilityGroups()`, `FieldTypeInferrer::inferFromPhpTypeName()`) were exposed so the rule can reuse the same compatibility table the runtime consults — there is no parallel encoding to drift from.

### Migration

See `UPGRADING.md` for the migration recipe and `docs/specs/entity-system.md` for the canonical reference, including a *Known Transitional Gaps* section that documents M1 deferrals (per-process metadata cache, missing `timestamp` / `enum` field-type plugins, missing `#[Field]` `stored:` parameter, scalar→`entity_reference` inferrer gap, and the `EntityType::fromClass()` + `FieldDefinitionRegistry` interaction bug).

### Changed

- **SSR app controllers (`waaseyaa/ssr`):** `Class::method` actions now use **typed parameter injection** only (services, route scalars/enums, entities via `EntityTypeManager`, optional `#[MapRoute]` / `#[MapQuery]`). Legacy `($params, $query, $account, $httpRequest)` invocation is removed. `AppControllerRouter` maps binding/argument errors to **404 / 400 / 500** (JSON:API vs `_render` HTML). Descriptor metadata is cached per controller method + route fingerprint (`waaseyaa/routing` `RouteFingerprint`). See **`docs/specs/app-controller-invocation.md`**.
- **Entity:** class-level `#[ContentEntityType('id')]` + `ContentEntityTypeReader` for strict binding to route `entity:{id}` metadata.
- **Routing:** `RouteBuilder::bind($name, class-string)` stores `_waaseyaa_app_bindings` for post-load class checks.
- **MCP:** `McpEndpoint::handle` signature updated for the same dispatcher (`AccountInterface`, `Request`).

### Fixed

- **Skeleton (`waaseyaa/waaseyaa`):** `composer create-project` from Packagist (or any directory) no longer fails on a monorepo-only `path` repository, a stale `post-create-project-cmd` `chmod` for a removed `bin/waaseyaa` wrapper, or a drifted `bin/golden-public-index.php` vs `public/index.php`. The audit script preflight now checks the Composer CLI proxy at `vendor/bin/waaseyaa`. Local `../waaseyaa/packages/*` path overrides are documented in `composer.local.json.example` only.

### Changed

- **Telescope agent-context telemetry:** Canonical Prometheus series names are now `waaseyaa_agent_context_*`; `waaseyaa_cc_*` remain as deprecated duplicates in the same scrape for dashboard migration. Admin SPA and E2E call **`/api/telescope/agent-context/…`**; legacy **`/api/telescope/codified-context/…`** HTTP routes remain registered. Telescope `record.agent_context` overrides `record.codified_context` when set. See **`docs/specs/telescope-agent-context-telemetry.md`**.

- **Governance:** Document **Spec Kitty–first** workflow — missions and work packages are the primary execution ledger; GitHub is PR/CI/releases and optional issues (`docs/specs/workflow.md`, `CLAUDE.md`, `AGENTS.md`, PR template). `bin/check-milestones` remains supplementary GitHub hygiene. README **Contributing** and the PR template include an **Active Spec Kitty mission** line for traceability.

- **Contributor / agent workflow:** Retired the Node MCP spec-retrieval servers (`waaseyaa_list_specs`, `waaseyaa_get_spec`, `waaseyaa_search_specs`). Subsystem context is read from `docs/specs/` directly. Adopted [Spec Kitty](https://github.com/Priivacy-ai/spec-kitty) scaffold (`.kittify/`); see `CLAUDE.md` for workflow and CLI install. Spec Kitty’s `.claude/skills/` symlinks stay local (gitignored); install the CLI and run `spec-kitty init` after clone.

### Removed

- `tools/spec-retrieval/` and root `mcp/` spec MCP implementations.
- `skills/codified-context/SKILL.md` (superseded by `skills/waaseyaa/spec-maintenance/SKILL.md`).

## [0.1.0-alpha.157] - 2026-04-22

### Changed

- `foundation`: `HttpKernel` JSON boot-failure responses now include operator-safe `detail` text for known cases (debug enabled in production, missing production SQLite, PHPUnit reachable on production autoload) without echoing database filesystem paths. Full diagnostics remain in the critical boot log line.

### Added

- `foundation`: `HttpKernelBootFailureTest` coverage for the client-safe boot failure detail mapping.

## [0.1.0-alpha.153] - 2026-04-21

### Added

- `northcloud`: `NcSyncWorker` sync status JSON now includes bounded samples and skip reasons from the explain path so operators and status files can see what North Cloud returned each cycle.

### Changed

- `admin`: NC sync summary widget calls `/api/staff/nc-sync-status` and links “open dashboard” to `/staff/ingestion` (aligns with Minoo staff ingestion routes).

## [0.1.0-alpha.152] - 2026-04-20

Closes the first-deploy database-bootstrap gap: `DatabaseBootstrapper`'s production guard (correct for steady-state) had no sanctioned counterpart for first-run initialization, forcing downstream apps to either ship `APP_ENV=local` workarounds or pre-touch the sqlite file outside the framework. This release adds `bin/waaseyaa db:init` as the single sanctioned path through the guard.

### Added

- `cli`: `db:init` command (`Waaseyaa\CLI\Command\DbInitCommand`) — sanctioned first-deploy database initializer. Resolves the sqlite path from the same config chain the HTTP kernel uses, creates the file and parent directory if missing, applies all pending migrations via the standard `Migrator`, and is idempotent on re-run (safe to invoke on every deploy). Refuses to touch an existing database that lacks the `waaseyaa_migrations` table rather than guessing at a repair. Runs through `ConsoleKernel::shouldUseMinimalConsole()` so it can execute under `APP_ENV=production` without tripping the `DatabaseBootstrapper` production guard.
- `cli`: `db:init --dry-run` reports the target path, parent-directory state, and pending migration list without touching the filesystem or database.
- `cli`: `db:init` acquires an exclusive `flock` on `.db-init.lock` in the database's parent directory so concurrent invocations bail fast with a clear message instead of racing.
- `cli`: `db:init` reports parent-directory and file-creation permission failures with the offending path, process user name, and uid, so operators can fix ownership without guessing.
- `cli`: `DbInitCommandTest` covers fresh volume, idempotent re-run, partial-init refusal, all three `--dry-run` branches, permission failure messaging, concurrent-invocation bail, and `WAASEYAA_DB` env-var precedence.

### Changed

- `foundation`: `DatabaseBootstrapper` production-guard error message now names `bin/waaseyaa db:init` and states the command is idempotent and safe to run on every deploy. The guard itself is unchanged.

## [0.1.0-alpha.151] - 2026-04-19

Five-release postmortem: alpha.147→148→149→150 each closed an immediate bundle-substrate alarm. alpha.150 then surfaced a fifth bug — `SqlEntityQuery`'s newly-reachable bundle JOIN code calling `DBALDatabase::quoteIdentifier()` against a `^0.1` `database-legacy` constraint that resolved to a stale alpha.145 sibling in consumer installs (Minoo crashed at 13 call sites simultaneously). The bug was structural, not local: every cross-package constraint in the monorepo was bare `^0.X`, so each fix in the chain only resolved the *specific* permissive-constraint failure that consumer's code had reached. This release closes the *class* of defect.

### Fixed

- monorepo-wide: cross-package `waaseyaa/*` `composer.json` constraints tightened from `^0.1` to `^0.1.0-alpha.150` across 54 manifests (179 individual constraints in `packages/*/composer.json` and `skeleton/composer.json`). Prevents Composer from co-resolving stale sibling versions that pre-date methods callers now use — the alpha.150 `quoteIdentifier()` regression class. Closes #1311.

### Added

- `bin/check-composer-policy`: new rule **CP005 (`tight_internal_floor`)** rejects any cross-package `waaseyaa/*` constraint in `packages/*` or `skeleton/composer.json` that lacks a pre-release-anchored floor (`alpha`/`beta`/`rc`/`dev` tag). Existing CP003 hint message updated to reflect the new floor convention. Future PRs introducing a bare `^0.X` cross-package constraint fail policy gate before merge.
- `entity-storage`: `DatabaseInterfaceCompositionTest` — local regression gate that asserts (1) every method called on `$this->database` in entity-storage source is declared on `Waaseyaa\Database\DatabaseInterface`, and (2) entity-storage's own `composer.json` anchors sibling waaseyaa/* constraints to a pre-release floor. Either invariant failing reproduces the alpha.150 class of defect at the unit-test layer instead of in production.

### Notes

- **Release-arc retrospective**: chain length 5. Each release closed a specific alarm; the class wasn't closed until treated as a class. Process-debt category: per-PR contract tests in PR #1307 ran against the source tree where co-resolution was always-fresh — they did not exercise packaged-form artifacts against constraint-floor dependencies. Filed as separate test-infrastructure follow-up to avoid scope creep here.

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
