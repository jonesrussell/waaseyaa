# Tasks / work packages — 589-parity-feature-set

WP01 was the Pass-2 decomposition (`decomposition.md`). WP02 produces the verification matrix (hard precondition). WP03-WP07 may run in parallel after WP02.

| WP | Title | Outcome | Status |
|----|-------|---------|--------|
| WP01 | mission-decomposition | `decomposition.md`. Mode = mechanical-with-architectural-asterisk. NO-SPLIT. 13 issues classified DONE/PARTIAL/GAP. 10 conventions (K1-K10) + 1 contract (C1) surfaced. 10 drift flags raised. | done |
| WP02 | verification-matrix-and-spec-drift-inventory | K1-K10 + C1 already ratified in `spec.md` (2026-04-30). WP02 produces new file `docs/audits/2026-04-30-track-3-parity-audit.md`. One row per absorbed issue with: live-source symbols, spec-doc location (or gap), disposition (DONE / PARTIAL / GAP / DEFERRED), pointer to the WP that closes it. The matrix is the canonical source for "what shipped, what didn't, what's documented." | unscheduled |
| WP03 | cache-backends-redis-and-memcached | `Waaseyaa\Cache\Backend\RedisCacheBackend` and `Waaseyaa\Cache\Backend\MemcachedCacheBackend` implementing `CacheBackendInterface` + `TagAwareCacheInterface`. Wired via `CacheConfigResolver` reading `cache.backends.redis.dsn` and `cache.backends.memcached.servers` from `config/waaseyaa.php`. Integration tests gated by `WAASEYAA_TEST_REDIS_DSN` / `WAASEYAA_TEST_MEMCACHED_SERVERS`. Update `docs/specs/infrastructure.md`. | unscheduled |
| WP04 | subsystem-spec-authoring | Author or extend: scheduler section in `docs/specs/infrastructure.md` (K2), notification section in `docs/specs/infrastructure.md` (K3), OAuth provider section per K4, OIDC server section per K5, workflows in new `docs/specs/workflows.md` (K6), mercure section in `docs/specs/infrastructure.md`, flash section in `docs/specs/ssr.md`, upload section in `docs/specs/infrastructure.md` or `docs/specs/media.md`, engagement in new spec, messaging in new spec. Update `CLAUDE.md` orchestration table for every new spec doc created. | unscheduled |
| WP05 | userblock-placement | Per K7 (a) ratified: ship `Waaseyaa\Messaging\UserBlock` entity + storage migration + `BlockAccessPolicy`. Tests cover the access-filter ripple into engagement (low-risk; mitigation in spec). | unscheduled |
| WP06 | entity-factory-and-db-seed-cli | `Waaseyaa\Testing\Factory\EntityFactory` abstract base per K8.a (in `packages/testing`). `fakerphp/faker` as `require-dev` of `testing` per K8.b. `bin/waaseyaa db:seed` command in `packages/cli` per K8.c. Factory definitions for `node`, `user`, `media`, `taxonomy_term`. Deprecate `EntityTypeFixtureValues` with log-once warning; migrate internal callers. | unscheduled |
| WP07 | deferral-writeups-form-api-and-webhook-framework | Per K9 (b) ratified: document Form API deferral in new `docs/specs/form-api.md`. Per K10 (b) ratified: document webhook framework deferral in `docs/specs/infrastructure.md` (new "Webhook handling — deferred" section). File two NEW follow-up issues (NOT re-opening closed ones), referencing this mission's merged commits and the original closed `#594` / `#628`. | unscheduled |

**Review gate:** Each WP runs through Spec Kitty implement → review per `docs/specs/workflow.md`. WP02 must reach `approved` before WP03-WP07 enter implement.

## Dependencies (between WPs)

- WP02 → WP03, WP04, WP05, WP06, WP07 (verification-matrix-first, hard precondition)
- WP03 ⟂ WP04 ⟂ WP05 ⟂ WP06 ⟂ WP07 (parallel after WP02; touch disjoint files)

## Per-WP gating notes

- **WP02** is the discipline gate. Without the verification matrix, an implementer may pick up #590 (OAuth) and rebuild a package that already ships. WP02 produces a single audit doc that future agents read first.
- **WP03** integration tests are env-gated. CI must wire docker-compose containers (or a GitHub Actions job) to actually run them. If CI cannot, ship the code path with a doc note in `docs/specs/infrastructure.md` that the backends are implemented but not yet CI-verified.
- **WP04** is bulky text work. The risk is that it gets under-resourced and leaves drift in place. WP04 acceptance has a checkbox per spec entry; partial completion is rejected at review.
- **WP05** scope depends on K7. If (a), WP05 is ~3 files (entity + migration + policy) plus tests. If (b), WP05 is ~1 paragraph in a spec doc.
- **WP06** must coexist with `EntityTypeFixtureValues`. Migration plan: log-once warning on instantiation; internal callers move to `EntityFactory`; removal scheduled in next minor.
- **WP07** scope depends on K10. If (b) (default), WP07 is documentation + 2 new GitHub issues filed. If (a), WP07 is ~6 files (interfaces + verifier + middleware + normalizer + tests + spec) — a much larger WP.
