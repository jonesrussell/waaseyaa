# Mission spec: 589-parity-feature-set

**Charter:** Reconcile the Track 3 parity backlog. Verify what's already shipped (11 of 13 issues describe code that already exists on disk), fill the genuine gaps (Redis/Memcached cache backends; EntityFactory base + db:seed CLI; UserBlock placement decision), document the public surfaces that landed without spec entries (OAuth, OIDC, scheduler, notification, workflows, mercure, flash, upload, engagement, messaging), and honestly defer what remains unbuilt (Form API, framework webhook surface) with explicit follow-up issues. The mission's primary discipline is verification-before-build.

**Milestone:** Track 3 — Parity & performance

**Origin:** Pass 1 architect-mode triage (2026-04-30). Anchor `#589` is **CLOSED**; all 13 absorbed issues are closed. The mission is a reconciliation pass, not a design pass.

**Decomposition artifact:** `decomposition.md` in this directory.

---

## Mission shape — verification, not design

Live-source disposition of the 13 absorbed issues, established during decomposition:

| Disposition | Count | Issues |
|-------------|-----:|--------|
| **DONE** (shipped, needs spec doc only) | 8 | #590 OAuth, #591 scheduler, #592 notification, #595 workflows, #694 mercure, #697 flash, #698 upload, #701 engagement |
| **PARTIAL** (real fill needed) | 3 | #589 cache backends, #593 EntityFactory + seeder, #702 UserBlock |
| **GAP** (closed but not built) | 2 | #594 Form API, #628 framework webhook surface |

The triage pass that closed these issues did so on the assumption they'd be absorbed by this mission. The live source shows most were absorbed by **prior work** (packages already exist). Closing the GAP issues without a fill or an honest deferral is a documentation lie — WP07 records the deferrals and files explicit follow-up issues.

**Mode: mechanical-with-architectural-asterisk (1257 pattern).** No new public contracts proposed apart from C1 (cache-backend implementations). Most work is documentation and verification.

---

## Decision: NO-SPLIT (6 WPs after decomposition)

Verification work converges on one set of files (`docs/specs/`, `CLAUDE.md` orchestration table, the audit doc). The genuinely new code (#589 cache backends) is one focused WP. Form API and framework webhook surface are honest deferrals to follow-up missions, not splits.

| WP | Title | Outcome | Issues |
|----|-------|---------|--------|
| WP02 | verification-matrix-and-spec-drift-inventory | Single audit doc listing each absorbed issue, live-source symbols satisfying it, and the spec doc that documents it. Mark each row DONE / PARTIAL / GAP / DEFERRED. | All 13 |
| WP03 | cache-backends-redis-and-memcached | `RedisCacheBackend` + `MemcachedCacheBackend` under `packages/cache/src/Backend/`, wired via `CacheConfigResolver`. Tag support. Integration tests gated by env vars. | #589 |
| WP04 | subsystem-spec-authoring | Add or extend spec docs for OAuth, OIDC, scheduler, notification, workflows, mercure, flash, upload, engagement, messaging. Update `CLAUDE.md` orchestration table for any new specs. | #590-#702 (the DONE rows) |
| WP05 | userblock-placement | Either ship `Waaseyaa\Messaging\UserBlock` entity + storage migration + access policy, OR document why it's intentionally out of scope and route to a future package. K7 ratification determines. | #702 |
| WP06 | entity-factory-and-db-seed-cli | `Waaseyaa\Testing\Factory\EntityFactory` abstract base + Faker integration (`fakerphp/faker` as `require-dev`). `bin/waaseyaa db:seed` command in `packages/cli`. Factory definitions for `node`, `user`, `media`, `taxonomy_term`. Deprecate `EntityTypeFixtureValues` with log-once warning. | #593 |
| WP07 | deferral-writeups-form-api-and-webhook-framework | Spec entries explicitly mark these as deferred. New follow-up issues filed for each (NOT re-opening closed issues; new issues that reference both this mission and the original closed ones). | #594, #628 |

**Sequencing.** WP02 first (everything else depends on the verification matrix). WP03, WP04, WP05, WP06, WP07 may run in parallel after WP02 — they touch disjoint files.

Per-WP detail in `tasks.md`.

---

## Ratified conventions (K1-K10) — approved 2026-04-30

K1-K6 batch-ratified as accepted conventions (verification discipline + spec authoring for shipped-but-undocumented packages). K7-K10 individually ratified below. Choices recorded inline.

### K1 — Verification-before-build is the mission's primary discipline — RATIFIED

The mission spec.md leads with: *"the live source is ground truth; the issue bodies are stale parity-audit references."* No agent picks up an absorbed issue and rebuilds a package that already ships. WP02 produces the verification matrix as a hard precondition for any code WP.

### K2 — Scheduler public surface authored in `docs/specs/infrastructure.md` — RATIFIED

`packages/scheduler/` shipped without an entry in the orchestration table or `docs/specs/infrastructure.md`. WP04 authors. Spec-drift fill, not a contract change.

### K3 — Notification public surface authored in `docs/specs/infrastructure.md` — RATIFIED

Same shape as K2. `NotificationInterface`, `NotifiableInterface`, `NotificationDispatcher`, channel pattern, queue-async behavior — all currently undocumented public contracts. WP04 authors.

### K4 — OAuth provider surface authored in new `docs/specs/oauth.md` or expanded `access-control.md` — RATIFIED

`packages/oauth-provider/` ships `OAuthProviderInterface`, `OAuthStateManager`, `OAuthToken`, `OAuthUserProfile`, plus Google and GitHub providers. WP04 authors. Decision needed: new file (`oauth.md`) or extend `access-control.md`?

### K5 — OIDC server surface authored in new `docs/specs/oidc.md` — RATIFIED

`packages/oidc/` ships a full OIDC provider implementation. The orchestration table mentions `packages/oidc/*` under `waaseyaa:access-control` linked to `access-control.md`, but `access-control.md` does not cover an OIDC server. New `docs/specs/oidc.md` recommended; cross-reference from `access-control.md`. WP04 authors.

### K6 — Workflows package spec authored in new `docs/specs/workflows.md` — RATIFIED

`packages/workflows/` ships 13 substantial files (the audit reference's "13 LOC" claim is stale). Editorial workflow, content moderation states, transitions, visibility filters, role matrix, domain validation listener. Currently undocumented. WP04 authors.

### K7 — UserBlock package placement — RATIFIED option (a)

#702 explicitly named `UserBlock` as part of scope. `messaging` shipped without it.

**Decision: Option (a).** WP05 ships `Waaseyaa\Messaging\UserBlock` entity + storage migration + `BlockAccessPolicy`. Avoids package proliferation. A future `packages/social` may emerge if a second social entity is needed; until then, `messaging` owns user-blocking semantics.

### K8 — EntityFactory shape (#593) — RATIFIED option (a)

**Decision: Option (a).** All three sub-points locked:

- **K8.a** `Waaseyaa\Testing\Factory\EntityFactory` abstract base in `packages/testing`. CLI in `packages/cli` references it via soft dep (consumer must pull `testing` as a dev dep to use seeding).
- **K8.b** `fakerphp/faker` added as `require-dev` of `packages/testing`. Production installs never pull Faker.
- **K8.c** `bin/waaseyaa db:seed` lives in `packages/cli/src/Command/`. Soft dependency on `testing`; emits a clear error if consumer hasn't pulled `testing` as dev dep. Factory definitions for `node`, `user`, `media`, `taxonomy_term` ship in WP06.

### K9 — Form API scope (#594) — RATIFIED option (b)

**Decision: Option (b).** WP07 documents the deferral in `docs/specs/form-api.md` (new file) explaining: "SSR Form API not provided; admin SPA renders forms via JSON Schema." WP07 files a new GitHub issue titled "feat: SSR Form API design (deferred from #594 / mission #589)" with explicit scope, referencing this mission's merged commits AND the original closed `#594`. Closed issue `#594` stays closed.

### K10 — Framework webhook surface (#628) — RATIFIED option (b)

**Decision: Option (b).** WP07 documents the deferral in `docs/specs/infrastructure.md` (new section: "Webhook handling — deferred"). Keeps `packages/billing/src/WebhookHandler.php` as the only webhook code; states explicitly that the framework-general abstraction is unbuilt. WP07 files a new GitHub issue titled "feat: framework webhook surface (deferred from #628 / mission #589)" with proposed `WebhookHandlerInterface` + `WebhookSignatureVerifier` + `WebhookRouteMiddleware` + `WebhookPayloadNormalizer` shapes, referencing this mission and the original closed `#628`. Closed issue `#628` stays closed.

---

## Ratified contract (C1) — approved 2026-04-30

### C1 — Redis and Memcached cache backends (#589) — RATIFIED

The cache package ships `CacheBackendInterface`, `CacheFactoryInterface`, `TagAwareCacheInterface`, `CacheTagsInvalidatorInterface`. **Decision:** `RedisCacheBackend` and `MemcachedCacheBackend` implement `CacheBackendInterface` AND `TagAwareCacheInterface`, ship under `packages/cache/src/Backend/`, are wired via `CacheConfigResolver` reading new keys (`cache.backends.redis.dsn`, `cache.backends.memcached.servers`) from `config/waaseyaa.php`. Both ship integration tests gated by `WAASEYAA_TEST_REDIS_DSN` / `WAASEYAA_TEST_MEMCACHED_SERVERS` env vars (skip when unset). No new public interface; only new implementations.

This is the only net-new code surface in the mission. WP03 implements.

---

## Drift flags

| # | Flag | Resolution |
|---|------|------------|
| D1 | Anchor `#589` is CLOSED, not OPEN | Acknowledged. Mission is reconciliation, not design. Spec language reframed. |
| D2 | #595's "13 LOC" claim is stale | `packages/workflows/` ships 13 substantial files. WP02 records reality. WP04 authors spec. |
| D3 | #590 (OAuth) is functionally complete but undocumented | WP04 authors spec. |
| D4 | #702 shipped without `UserBlock` (named in body) | WP05 closes via K7 ratification. |
| D5 | #594 closed but never built | WP07 documents deferral; files follow-up issue. |
| D6 | #628 closed but only billing-specific built | WP07 documents deferral; files follow-up issue. |
| D7 | #593 closed but only partially built (`EntityTypeFixtureValues` exists; `EntityFactory` base + `db:seed` do not) | WP06 fills. |
| D8 | None of the 13 issues should have been routed to another mission | Confirmed clean room. No re-routing. |
| D9 | #702's "extract Mercure first (#694)" sequencing is satisfied | `messaging` already imports shipped `MercurePublisher`. Acknowledged. |
| D10 | No conflict with prior ratified contracts (824/619/1257/1107) | Confirmed clean room. |

---

## Acceptance

The mission accepts when ALL of:

1. WP02's verification matrix exists and every absorbed issue has a recorded disposition.
2. `RedisCacheBackend` and `MemcachedCacheBackend` ship with tag support and gated integration tests.
3. Every "DONE" row in WP02 has at least one corresponding paragraph in a `docs/specs/*.md` file.
4. `CLAUDE.md` orchestration table updated for any new spec docs created (oidc.md, oauth.md if separated, workflows.md, etc.).
5. `UserBlock` is either implemented (WP05 + K7 (a)) or documented as out-of-scope (WP05 + K7 (b)).
6. `EntityFactory` + `db:seed` ship per K8 ratification.
7. Form API and framework webhook surface are documented as deferred with concrete follow-up issues filed (NOT re-opening closed issues).
8. All 13 absorbed issues remain closed; no re-opens.
9. `bin/check-package-layers`, `composer phpstan`, `composer cs-check`, `composer check-composer-policy` all stay green.

---

## Risks

1. **An implementer takes the issue bodies at face value and rebuilds shipping packages.** Severity: high. Mitigation: WP02's verification matrix is a hard precondition; spec.md leads with the live-source-is-ground-truth framing.
2. **Spec drift continues to compound.** The orchestration table maps several packages to specs that don't exist or don't cover them. WP04 is bulky text work; an under-resourced WP04 leaves the drift in place. Severity: medium. Mitigation: WP04 acceptance has a checkbox per spec entry.
3. **Cache backend integration tests are environment-dependent.** Redis and Memcached integration tests skip without env vars. CI may not run them, leaving the backends untested at the merge gate. Severity: medium. Mitigation: docker-compose snippet or GitHub Actions job that boots the containers and sets env vars.
4. **`UserBlock` (K7 (a)) touches access policy.** `BlockAccessPolicy` ripples into engagement filtering. Severity: low. Mitigation: WP05 ships the policy or documents the absence; can land in follow-up.
5. **Factory pattern collides with `EntityTypeFixtureValues`.** Two patterns confuse contributors. Severity: low. Mitigation: WP06 deprecates `EntityTypeFixtureValues` (log-once warning); migrates internal callers.
6. **Form API and webhook deferrals look like punts.** External readers of the closed issues may interpret "closed under #589" as "shipped." Severity: low. Mitigation: WP07 files explicit follow-up issues with cross-link comments on the closed issues; deferral spec entries name the new issues.
7. **Charter underspecification.** Original `spec.md` was scaffolded by triage without subsystem-specific acceptance criteria. THIS spec is the expansion. Severity: medium if WP02 spec-lock is skipped, low if enforced.
