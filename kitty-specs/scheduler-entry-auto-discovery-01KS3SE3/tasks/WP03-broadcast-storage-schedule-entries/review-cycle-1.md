# WP03 Review (cycle 1) — REJECTED

Reviewer: claude:opus-4-7:reviewer:reviewer
Commit reviewed: 225d0312c
Lane: kitty/mission-scheduler-entry-auto-discovery-01KS3SE3-lane-a (head of lane after WP01 + WP02 + WP03)

## What is good

- `PackageManifestCompiler::scanPsr4Classes()` adds a unified `/testing/` filter applied to ALL discovery surfaces (lines 849-858) — not band-aided into `scanScheduleEntryClasses()`. Correct placement.
- `PackageManifestCompilerTest`: 30/30 green (was fataling pre-fix).
- `BroadcastStorageScheduleEntries` correctly `implements ScheduleEntriesInterface`, returns `array<string, ScheduledTask>` from `register()`, cron `0 2 * * *`, retention 7 days configurable via `schedule.broadcast_log_retention_days`.
- Verified `BroadcastStorage::prune(int $retentionDays = 7): void` — signature matches the call site `$broadcastStorage->prune($retentionDays)`.
- `BroadcastStorage` is `final`, justifying the real-SQLite test approach.
- `BroadcastStorageScheduleEntriesTest`: 3/3 green using real `DBALDatabase::createSqlite(':memory:')`.
- `bin/check-package-layers` OK — api (L4) → scheduler (L0) is downward.
- `composer cs-check` clean, `composer phpstan` no errors.
- `packages/api/tests/`, `packages/foundation/tests/`, `packages/scheduler/tests/` — 1385/1385 pass.

## Blocking issues

Integration suite regressed vs `main` (which is clean for the same 13 tests):

1. **SurfaceMap regression — owned here**. `tests/Integration/SurfaceMap/PublicSurfaceVerificationTest::every_public_element_has_a_disposition` fails because `Waaseyaa\Scheduler\ScheduleEntriesInterface` (added in WP01) has no entry in `docs/public-surface-map.php`. Only `ScheduleInterface` is mapped. Add the disposition for the new interface in this WP or hand it off to WP05 wrap-up.

2. **8 SSR Phase13 regressions** introduced by WP01/WP02 boot wiring on lane-a (all `tests/Integration/Phase13/SsrHttpKernelIntegrationTest` cases — `rendersNodeHtmlWithFormattersAndTemplateOverride`, `resolvesPathAliasAndRendersSameEntity`, `supportsTeaserAndFullViewModesViaQueryParameter`, `unknownPathReturns404Html`, `unpublishedWorkflowStatesAreHiddenFromPublicSsr`, `unauthenticatedPreviewQueryDoesNotBypassVisibility`, `entity_save_invalidates_render_cache_for_subsequent_http_request`, `previewRequestDoesNotWriteOrReadPublicRenderCache`). All pass on `main`. Likely caused by the WP02 fail-closed boot assertion firing during SSR test bootstrap.

3. **4 OIDC regressions** (`OidcAuthorizeIntegrationTest::anonymousGetAuthorizeRedirectsToLogin`, `OidcDiscoveryIntegrationTest::discoveryEndpointReturnsOidcMetadataWithConfiguredIssuer`, `OidcJwksIntegrationTest::jwksEndpointReturnsRsaJwkDerivedFromConfiguredPublicKey`, `OidcTokenIntegrationTest::tokenEndpointExchangesCodeForSignedIdToken`). Same lane-only failure pattern, also passes on main.

## Required actions before re-review

- Add `'Waaseyaa\Scheduler\ScheduleEntriesInterface' => '<disposition>'` to `docs/public-surface-map.php` and update `.md` companion.
- Investigate SSR + OIDC boot regressions — almost certainly the new fail-closed assertion in the kernel discovery path (WP02) is rejecting valid empty test fixtures or the test bootstrap is producing a manifest that lacks the new key. Either gate the assertion, or ensure test fixtures register at least one `ScheduleEntriesInterface` implementor.
- Re-run `vendor/bin/phpunit tests/Integration/` — must match main's clean state.

WP03's own code is solid. Block reason is integration-level fallout from earlier WPs on the lane that landed in this lane head; WP03 is the first WP whose review surfaces them.
