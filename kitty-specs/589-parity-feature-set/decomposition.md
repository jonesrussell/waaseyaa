# Decomposition — parity-feature-set

Date: 2026-04-29 (Pass 2 WP01 output)

## Mission summary

Mission #589 is a Track 3 ("Parity & performance") aggregator anchored on issue `#589` ("feat: add Redis and Memcached cache backends") and absorbing twelve other parity issues spanning OAuth/OIDC, scheduling, notifications, factories, Form API, content moderation, webhooks, and four extraction tickets (Mercure, flash, upload, engagement, messaging). The grouping label is real (`track-3-parity-perf`), but the issues themselves are independent feature requests. Anchor `#589` is **CLOSED** as of 2026-04-30 (the prompt's claim that "anchor #589 is open" is wrong — verify with `gh issue view 589 --json state`).

**Mode: mechanical-with-architectural-asterisk (1257 pattern), with severe drift.** All thirteen issues were closed by the 2026-04-30 triage pass, but the live source shows that **eleven of the thirteen are already substantially or fully implemented** as packages on disk. The mission is not a "build new framework features" mission. It is a "verify-what-exists, fill-the-gaps, document-the-conventions" mission. Any agent that picks this up and starts implementing OAuth, scheduling, notifications, messaging, engagement, mercure, flash, or upload from scratch will collide with shipping code.

## Absorbed issues + open anchor

All issues are CLOSED (2026-04-30T01:04:xxZ) under the `track-3-parity-perf` label. The third column is the live-source disposition discovered during this decomposition.

| Issue | Title | Live source disposition |
|---|---|---|
| #589 (anchor) | feat: add Redis and Memcached cache backends | **GAP**. `packages/cache/src/Backend/` exists but no `RedisCacheBackend.php` or `MemcachedCacheBackend.php` symbol surfaces in the tree. Real work remaining. |
| #590 | feat: add OAuth2/OIDC provider support | **DONE**. `packages/oauth-provider/` with `GoogleOAuthProvider.php` and `GitHubOAuthProvider.php`. `packages/oidc/` with `Authorize/`, `Token/TokenRequestValidator.php`, `Repository/DatabaseAuthorizationCodeRepository.php`, `Entity/OidcClient.php`, `ClientRegistry/OidcClientSeeder.php`. |
| #591 | feat: add task scheduling / cron system | **DONE**. `packages/scheduler/` exists with `dragonmantank/cron-expression` dependency. |
| #592 | feat: add notification system (multi-channel) | **DONE**. `packages/notification/` ships `NotificationInterface`, `NotifiableInterface`, `NotificationDispatcher`, `Channel/MailChannel.php`, `Channel/DatabaseChannel.php`, `Job/SendNotificationHandler.php` (queue-async), `NotifiableTrait`. |
| #593 | feat: add factory/seeder framework | **PARTIAL**. `packages/testing/src/Factory/EntityTypeFixtureValues.php` exists. No `EntityFactory` base class. No `bin/waaseyaa db:seed`. No Faker dependency in root composer (verify). |
| #594 | feat: add Form API or form handling abstraction | **GAP**. No `FormInterface`, no form package, no `Form*.php` symbol in `packages/foundation`, `packages/api`, `packages/routing`, `packages/ssr`. Real work remaining. |
| #595 | feat: add content moderation / editorial workflow | **DONE**. The audit reference's "13 LOC" claim is stale. `packages/workflows/` ships `ContentModerationState`, `ContentModerator`, `EditorialWorkflowPreset`, `EditorialWorkflowService`, `EditorialTransitionAccessResolver`, `EditorialVisibilityResolver`, `WorkflowState`, `WorkflowTransition`, `WorkflowVisibility`, `WorkflowVisibilityFilter`, `AuthoringRoleMatrix`, `DomainValidationListener`. |
| #628 | feat: webhook handling abstraction | **PARTIAL/AMBIGUOUS**. `packages/billing/src/WebhookHandler.php` exists (Stripe-shaped). No framework-level `WebhookHandlerInterface`, `WebhookSignatureVerifier`, `WebhookRouteMiddleware`, or `WebhookPayloadNormalizer` in `routing` or `foundation`. The framework-general abstraction is a gap; the billing-specific handler is not a substitute. |
| #694 | Add Mercure publisher package (waaseyaa/mercure) | **DONE**. `packages/mercure/src/MercurePublisher.php` + `MercureServiceProvider.php`. |
| #697 | Add flash messaging to framework | **DONE**. `packages/ssr/src/Flash/Flash.php`, `Flash/FlashMessageService.php`, `Twig/FlashTwigExtension.php`. |
| #698 | Add UploadService to waaseyaa/media | **DONE**. `packages/media/src/UploadHandler.php`. |
| #701 | Add engagement entities package (waaseyaa/engagement) | **DONE**. `packages/engagement/src/{Comment,Follow,Reaction,EngagementAccessPolicy,EngagementServiceProvider}.php`. |
| #702 | Add messaging infrastructure package (waaseyaa/messaging) | **PARTIAL**. `packages/messaging/src/{MessageThread,ThreadMessage,ThreadParticipant,MessagingServiceProvider}.php` exist. **`UserBlock` is missing** (the issue named it). The `social` vs `messaging` package question from the issue body was resolved by inclusion in `messaging`, but `UserBlock` is unaccounted for. |

Net real gaps: **#589 (Redis/Memcached cache backends)**, **#594 (Form API)**, **#628 framework webhook surface**, **#593 EntityFactory base class + seeder CLI**, **#702 UserBlock placement**. Everything else is documentation/verification work.

## Conventions to ratify

This is a mechanical-with-architectural-asterisk mission. The "architectural asterisks" are not new contracts being proposed — they are existing public surfaces that landed without a ratification pass. WP02 must inventory them.

### K1 — Verification-before-build is the mission's primary discipline

The triage pass that closed these issues did so on the assumption that they were absorbed by a mission. The live source shows they were absorbed by **prior work** (the packages already exist) but never reconciled with the parity audit. Before any new code lands under #589, each absorbed issue's acceptance criteria must be checked against the shipping symbol surface. The mission's WP02 produces a verification matrix, not a design.

### K2 — `ScheduleInterface` / `Scheduler` public surface (existing, unratified)

`packages/scheduler/` ships today with whatever surface `WP01` of its now-merged scaffold defined. It is not in the orchestration table of `CLAUDE.md`. The `waaseyaa-scheduler` shape (interfaces, attributes, locking strategy, CLI command name) needs a one-paragraph entry in `docs/specs/infrastructure.md` (per the orchestration table mapping `packages/scheduler/*` → `waaseyaa:infrastructure`). Spec-drift detection.

### K3 — Notification public surface (existing, unratified)

Same shape as K2 for `packages/notification/`. The orchestration table maps it to `waaseyaa:infrastructure` and `docs/specs/infrastructure.md`. The notification package has its own `via()`/channel/notifiable trait shape that is a public framework contract today; if `infrastructure.md` does not document it, agents writing notifications guess the shape from source code.

### K4 — OAuth provider surface (existing, unratified)

`packages/oauth-provider/` ships `OAuthProviderInterface`, `OAuthStateManager`, `OAuthToken`, `OAuthUserProfile`, `Provider/{GoogleOAuthProvider,GitHubOAuthProvider}.php`. None of this appears in the orchestration table or in any `docs/specs/` document. The `Waaseyaa\OAuth` namespace and provider-registration pattern are an undocumented public contract.

### K5 — OIDC server surface (existing, unratified)

`packages/oidc/` ships `Authorize/`, `Token/TokenRequestValidator.php`, `Token/TokenRequest.php`, `Repository/DatabaseAuthorizationCodeRepository.php`, `Entity/OidcClient.php`, `ClientRegistry/OidcClientSeeder.php`, `Keys/`, `Http/`, `Access/`. This is a full OIDC provider implementation. The orchestration table mentions `packages/auth/*` and `packages/oidc/*` under `waaseyaa:access-control`, but the linked spec `docs/specs/access-control.md` will not cover an OIDC server. A dedicated entry (`docs/specs/oidc.md` or expanded section) is owed.

### K6 — Workflows package layer placement

`packages/workflows/` has thirteen real files now (not 13 LOC). The orchestration table puts it at L3 (Services) with no spec doc. The mission must produce one.

### K7 — `UserBlock` package placement (#702)

The #702 body explicitly raised the question: does `UserBlock` belong in `messaging` or in a separate `social` package? `messaging` shipped without it. The decision needs to be made now, not deferred. Recommendation: ratify `UserBlock` into `messaging` (consumer count is small; avoid package proliferation) **or** create `packages/social` only if a second social entity emerges.

### K8 — `EntityFactory` shape (#593, the genuine architectural choice)

The acceptance criteria call for an `EntityFactory` base with `definition()` method, Faker, and a `db:seed` CLI. Today's `EntityTypeFixtureValues` is a fixtures helper, not a factory. The architectural questions:

- (a) Where does it live? `packages/testing` (testing-only, but the issue says "factories usable in both tests and development seeding") or `packages/entity` (production-shipped)? Recommendation: `packages/testing` for the base + Faker integration; production seeding uses the same factories via the CLI command in `packages/cli`.
- (b) Faker dependency: `fakerphp/faker` is the maintained fork. Add as `require-dev` of the `testing` package (so production installs don't pull it).
- (c) `bin/waaseyaa db:seed` lives in `packages/cli/src/Command/` and depends on `testing` only when the consumer opts in.

### K9 — Form API scope (#594)

Ratification needed: does `FormInterface` produce HTML for SSR (Drupal Form API analog) **or** is it a JSON-schema-driven contract that the admin SPA already consumes (so this issue is functionally closed by `SchemaPresenter` + admin SPA widgets)? The issue body wants SSR HTML. The current admin SPA infrastructure does not satisfy that. **Recommendation: scope this to a follow-up mission and close the spec gap with an explicit "SSR Form API not provided; admin SPA renders forms via JSON Schema" entry in `docs/specs/ssr.md` or `docs/specs/admin-spa.md`.** Splitting a Form API design out of #589 keeps this mission small.

### K10 — Webhook framework surface (#628)

Ratification needed: keep `packages/billing/src/WebhookHandler.php` as the only webhook code (consumer-specific) **or** extract a framework-level surface (`WebhookHandlerInterface`, `WebhookSignatureVerifier`, `WebhookRouteMiddleware`) into `packages/foundation/src/Http/Webhook/` or a new `packages/webhook/`. Recommendation: extract into foundation (signature verification is a foundation concern; HMAC-SHA256 has no domain coupling). Defer to a small follow-up mission unless the Claudriel #549 dependency is blocking — flag explicitly.

## PROPOSED CONTRACT — needs ratification (the one architectural exception)

### C1 — Redis and Memcached cache backends extend the existing CacheBackendInterface

The cache package ships `CacheBackendInterface`, `CacheFactoryInterface`, `TagAwareCacheInterface`, `CacheTagsInvalidatorInterface`. **Ratify**: `RedisCacheBackend` and `MemcachedCacheBackend` implement `CacheBackendInterface` AND `TagAwareCacheInterface`, are added under `packages/cache/src/Backend/`, and are wired via `CacheConfigResolver` reading new keys (`cache.backends.redis.dsn`, `cache.backends.memcached.servers`) from `config/waaseyaa.php`. Both ship integration tests gated by `WAASEYAA_TEST_REDIS_DSN` / `WAASEYAA_TEST_MEMCACHED_SERVERS` env vars (skip when unset). No new public interface; only new implementations.

This is the only net-new code surface in the mission. Everything else is documentation, verification, and small fills.

## SPLIT vs NO-SPLIT decision

**NO-SPLIT.**

Justification:

1. The bulk of the mission is verification and spec-doc updates against existing live source. Splitting verification work across multiple missions multiplies decomposition overhead without adding parallelism — every WP touches the same `docs/specs/` directory and the same orchestration table in `CLAUDE.md`.
2. The genuinely new code (#589 cache backends) is one focused WP. Splitting one focused WP into its own mission produces empty mission overhead.
3. The remaining "real gap" candidates (#594 Form API, #628 webhook framework, #593 factory base) are independent enough to defer to follow-up missions. Pulling each into its own mission today risks scope creep and decomposes work that hasn't been designed yet. Deferral, not splitting.
4. `UserBlock` (#702) is a one-file decision. Not a mission, not even a WP — a single ratification line.
5. SPLIT requires 2+ independent contract clusters with member issues to assign. Member issues are all closed. Re-opening them as work in independent missions would invert the absorption choice the triage pass made.

## Proposed WP roster

| WP | Title | Outcome | Issues | Depends on |
|---|---|---|---|---|
| WP02 | Verification matrix + spec drift inventory | Produce a single `docs/audits/2026-04-30-track-3-parity-audit.md` (or update spec.md) listing each absorbed issue, the live-source symbols that satisfy it, and the spec doc that documents it. Mark each row DONE / GAP / DEFERRED. | All 13 | — |
| WP03 | Cache backends (Redis + Memcached, the real gap) | `RedisCacheBackend` + `MemcachedCacheBackend` under `packages/cache/src/Backend/`, wired via `CacheConfigResolver`. Tag support. Integration tests gated by env. Update `docs/specs/infrastructure.md`. | #589 | WP02 (C1 ratified) |
| WP04 | Subsystem spec authoring | Add or extend: scheduler section in `docs/specs/infrastructure.md` (K2), notification section in `docs/specs/infrastructure.md` (K3), OAuth provider section in `docs/specs/access-control.md` or new `docs/specs/oauth.md` (K4), OIDC section in new `docs/specs/oidc.md` or expanded `docs/specs/access-control.md` (K5), workflows in new `docs/specs/workflows.md` (K6), mercure in `docs/specs/infrastructure.md`, flash in `docs/specs/ssr.md`, upload in `docs/specs/infrastructure.md` or `docs/specs/media.md`, engagement in new spec, messaging in new spec. Update `CLAUDE.md` orchestration table for any new specs created. | #590, #591, #592, #594 (deferral note), #595, #694, #697, #698, #701, #702 | WP02 |
| WP05 | UserBlock placement (#702 finishing pass) | Either add `Waaseyaa\Messaging\UserBlock` entity + storage migration + access policy, OR document in `docs/specs/messaging.md` why it's intentionally out of scope and reroute to a future package. | #702 | WP02 (K7 ratified) |
| WP06 | Factory base class + seeder CLI (#593 fill) | Add `Waaseyaa\Testing\Factory\EntityFactory` abstract base, Faker integration, `bin/waaseyaa db:seed` command in `packages/cli`. Factory definitions for `node`, `user`, `media`, `taxonomy_term`. | #593 | WP02 (K8 ratified) |
| WP07 | Deferral writeups (#594 Form API, #628 webhook framework) | Spec entries that explicitly mark these as deferred-to-follow-up missions, with cross-link issues filed for each (these will be NEW issues, not re-opening closed ones). Records the architectural decision now so future agents don't re-implement Form API badly. | #594, #628 | WP02 (K9, K10 ratified) |

WP01 was the decomposition (this file).

Six WPs after decomposition. All are independently mergeable. WP04 is the largest by line count but lowest by risk (spec text only).

## Acceptance for the mission as a whole

- WP02's verification matrix exists and every absorbed issue has a recorded disposition.
- `RedisCacheBackend` and `MemcachedCacheBackend` ship with tag support and gated integration tests.
- Every "DONE" row in WP02 has at least one corresponding paragraph in a `docs/specs/*.md` file.
- `CLAUDE.md` orchestration table is updated for any new spec docs created.
- `UserBlock` is either implemented (WP05) or documented as out-of-scope.
- `EntityFactory` + `db:seed` ship.
- Form API and framework webhook surface are documented as deferred with concrete follow-up issues filed.
- All 13 absorbed issues remain closed; no re-opens.
- `bin/check-package-layers`, `composer phpstan`, `composer cs-check`, `composer check-composer-policy` all stay green.

## Drift flags

1. **The prompt is wrong about `#589` being open.** `gh issue view 589 --json state` returns `CLOSED`. All 13 issues are closed. Decomposition methodology must adapt: this is a "verify and document closed work" mission, not a "design new public contracts" mission.

2. **#595's "13 LOC" claim is stale by orders of magnitude.** `packages/workflows/src/` has thirteen substantial files now. The audit reference in the issue body predates the editorial-workflow build-out. WP02 must record this and not let an agent "fix" the package based on the obsolete description.

3. **#590 is functionally complete but undocumented.** OAuth provider implementations for Google and GitHub exist; OIDC server is also there. Closing this issue without spec docs creates exactly the kind of stale-spec problem `tools/drift-detector.sh` is supposed to catch.

4. **#702 shipped without `UserBlock`.** The issue body explicitly named it as part of scope. The closure was premature. WP05 closes the gap.

5. **#594 was closed but never built.** No Form API exists. Closing this issue without a fill or an explicit deferral is a documentation lie. WP07 documents the deferral and files a follow-up.

6. **#628 was closed but only partially built.** A billing-specific `WebhookHandler` is not the framework-general abstraction the issue body specified. Same problem as #594 — deferred, not done. WP07 records this honestly.

7. **#593 was closed but only partially built.** `EntityTypeFixtureValues` exists; `EntityFactory` base + `db:seed` CLI do not. WP06 fills.

8. **None of these issues should have been absorbed by another mission and were not mis-routed.** They're all parity-against-Drupal/Laravel concerns. The 824 / 619 / 1257 / 1107 missions own different surfaces. No re-routing recommended.

9. **#702's "extract Mercure first (#694)" sequencing was satisfied by prior work.** The dependency is no longer live; messaging already imports the shipped `MercurePublisher`.

10. **No conflict with already-ratified contracts.** `KernelServicesInterface` (824), C1-C10 from 619, K1-K7+C1 from 1257, C1-C5 from 1107 — none intersect with cache backends, schedulers, notifications, OAuth, OIDC, workflows, mercure, flash, upload, messaging, or engagement. Clean room.

## Risks

1. **An implementer takes the issue bodies at face value and rebuilds shipping packages.** Severity: high. Mitigation: WP02 produces the verification matrix before any other WP enters implement, and the mission spec.md leads with "the live source is ground truth, the issue bodies are stale parity-audit references."

2. **Spec drift continues to compound.** The orchestration table in `CLAUDE.md` already maps these packages to specs that don't exist or don't cover them. WP04 is bulky text work; an under-resourced WP04 leaves the drift in place. Severity: medium. Mitigation: WP04 must enumerate every package in the absorbed list and have a checkbox per spec entry in its acceptance.

3. **Cache backend integration tests are environment-dependent.** Redis and Memcached integration tests skip without env vars. CI may not run them, leaving the backends untested in the merge gate. Severity: medium. Mitigation: provide a docker-compose snippet or GitHub Actions job that boots redis/memcached containers and sets the env vars; document opting in.

4. **`UserBlock` is a small surface but touches access policy.** Adding `BlockAccessPolicy` (named in the original Minoo extraction analysis) ripples into engagement filtering. Severity: low. Mitigation: WP05 either ships the policy or documents the absence; the policy can land in a follow-up.

5. **Factory pattern collides with `EntityTypeFixtureValues`.** Two patterns for fixture/factory data can confuse contributors. Severity: low. Mitigation: WP06 deprecates `EntityTypeFixtureValues` (log-once warning) and migrates internal callers.

6. **Form API and webhook deferrals look like punts.** External readers of the closed issues may interpret "closed under #589" as "shipped." Severity: low. Mitigation: WP07 files explicit follow-up issues with cross-link comments on the closed issues, and the deferral spec entries name them.

7. **Charter underspecification.** `spec.md` was scaffolded by the triage pass without subsystem-specific acceptance criteria. WP02 produces them; the spec must be updated before WP03+ enter implement. Severity: medium if skipped, low if enforced.
