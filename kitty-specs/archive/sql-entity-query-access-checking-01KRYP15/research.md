# Phase 0 Research

This document records the Phase 0 archaeology that busted four spec
assumptions and locked the implementation contract. Each item carries
a decision, rationale, and the alternatives that were considered.

---

## R-001 — Which access surface and is there a batch API?

**Question:** The spec named `AccessChecker::checkMultiple()` as the batch entry point. Does it exist? If not, what's the right surface?

**Findings (`packages/access/src/`):**
- `AccessChecker.php:41` defines `check(Route $route, AccountInterface $account): AccessResult` — **route-oriented, not entity-oriented**. This is the access check used inside `AuthorizationMiddleware` for HTTP routes.
- `EntityAccessHandler.php:82` defines `check(EntityInterface $entity, string $operation, AccountInterface $account, ?array $context = null): AccessResult` — **the actual entity-level surface**.
- No `checkMultiple()` exists on either class.

**Decision:** Use `EntityAccessHandler::check($entity, 'view', $account)` per row. The spec's reference to `AccessChecker::checkMultiple()` was a mis-aimed pointer.

**Rationale:** Per-row in-memory iteration is acceptable performance because policies are typically constant-time (account-id comparison plus a capability lookup). The original NFR-001 "O(1) extra DB queries per `execute()`" is preserved: the per-row check doesn't hit the database unless a specific policy chooses to (and no current policy does).

**Alternatives considered:**
- Add `EntityAccessHandler::checkMultiple(EntityInterface[], string, AccountInterface)` in this mission. **Rejected** as v1.x optimization scope; introduces a new contract surface for a benefit that won't materialize until a policy is observed hitting the DB. Documented as a follow-up.
- Encode the access policy as a SQL `WHERE` clause for pre-filter pushdown. **Rejected** as v2.x optimization. Policies can be arbitrarily complex PHP (e.g. read `account->getRoles()` against a permission table); cannot be statically lifted to SQL.

---

## R-002 — Does `EntityStorage::loadMultiple()` have an existing access check to mirror?

**Question:** The spec said "parity with `EntityStorage::loadMultiple()` semantics". What are those semantics today?

**Findings (`packages/entity-storage/src/SqlEntityStorage.php`):**
- `grep -n "access\|AccessChecker\|accessCheck" packages/entity-storage/src/SqlEntityStorage.php` → 0 hits.
- `loadMultiple()` performs no access filtering. It loads the requested entities and returns them.

**Decision:** This mission *establishes* entity-level access enforcement at the query layer. There is no precedent in the framework. The spec's "mirror" framing was a wrong premise.

**Rationale:** Naming "parity with loadMultiple" in the spec was an overgeneralisation. There is no existing implementation to copy; the mission stands on its own design.

**Alternatives considered:**
- Also enforce access at `loadMultiple()`. **Rejected** as out-of-mission scope. `loadMultiple()` is called from many internal contexts (hydration helpers, reference resolution) where access enforcement could break behaviour in ways that aren't security-relevant. Listing controllers / GraphQL / agent tools all go through `getQuery()::execute()` → IDs → `loadMultiple()` to materialise. Filtering at `getQuery()::execute()` time catches the security surface; filtering at `loadMultiple()` time would double-filter and complicate paginators.
- Filter only at the controller / serializer level. **Rejected**; that's what the agent runtime + ADR-019 was supposed to enforce, and the per-controller pattern is impossible to audit across the framework. The query layer is the chokepoint that makes the security posture mechanical.

---

## R-003 — `execute()` return shape

**Question:** What does `SqlEntityQuery::execute()` return today, and how do we filter without breaking the contract?

**Findings:**
- `EntityQueryInterface::execute(): array` (line in `packages/entity/src/Storage/EntityQueryInterface.php`).
- Usage pattern across consumers is `$ids = $storage->getQuery()->...->execute();` — i.e. **returns entity IDs, not hydrated entities**.
- `EntityAccessHandler::check()` takes `EntityInterface`, not an ID. To run the check we need the row hydrated.

**Decision:** Hydrate the candidate page first (load entities for the page's IDs via the existing storage), then run access check, then return surviving IDs. `execute()` keeps its `: array` (of IDs) return type — backward-compatible.

**Rationale:** The interface contract `execute(): array` is consumed by ~20 call sites; changing it would cascade. Internal hydrate-then-filter-then-return-IDs is invisible to consumers. The performance cost is "we load the page's entities", which is what consumers were going to do next anyway — many call sites immediately follow `execute()` with `loadMultiple($ids)`. Future optimisation: a follow-up could short-circuit by returning entities directly via a new method (`executeEntities()`) on the interface, so consumers skip the second load.

**Alternatives considered:**
- Change `execute(): array` to `execute(): EntityInterface[]`. **Rejected** — breaks every existing consumer.
- Add a parallel `executeEntities(): EntityInterface[]` method. **Deferred** to a future optimisation; not in this mission's scope (FRs don't require it). Consumers that want the entities can call `$storage->loadMultiple($query->execute())` as today.

---

## R-004 — Call-site sweep and existing `accessCheck(false)` audit

**Question:** Where are all the `getQuery()` consumers, and how many existing `accessCheck(false)` opt-outs are there?

**Findings (`grep -rn -- "->getQuery()" packages/ --include="*.php"` filtered to `src/`):**

**Packages affected (7):**
| Package | Consumers |
|---|---|
| `oidc` | `OidcClientSeeder.php:123`, `OidcClientLookup.php:28` |
| `relationship` | `RelationshipValidator.php:272`, `RelationshipDeleteGuardListener.php:36`, `:41` |
| `ai-vector` | `SemanticIndexWarmer.php:282` ⚠️, `SearchController.php:173` ⚠️, `:303` |
| `graphql` | `EntityResolver.php:65` ⚠️, `:81` ⚠️, `:211` |
| `genealogy` | `Ssr/GenealogySsrController.php:152`, `:160`, `Service/GenealogyFamilyService.php:27`, `Service/GenealogyPedigreeService.php:31`, `:49`, `:225` |
| `workflows` | `DomainValidationListener.php:133` |
| `api` | `JsonApiController.php:52`, `:63`, `:450` |

**Existing `accessCheck(false)` opt-outs (5 production call sites — to audit in WP-03):**
| Site | Initial classification | Notes |
|---|---|---|
| `ai-vector/SemanticIndexWarmer.php:282` | ✅ Legitimate bypass | Background index warm; runs as system; needs to see all rows to build the index. KEEP `accessCheck(false)`. |
| `ai-vector/SearchController.php:173` | ⚠️ Likely bug | User-facing search controller; should respect user's access. INVESTIGATE in WP-03; default action is REMOVE the bypass. |
| `ai-vector/SearchController.php:303` | ⚠️ Likely bug | Same controller, second call site. Same disposition expected. |
| `graphql/EntityResolver.php:65` | ⚠️ Likely bug | GraphQL count query — counts ALL entities, not user-visible ones. Today this leaks cardinality information. Should respect access. |
| `graphql/EntityResolver.php:81` | ⚠️ Likely bug | Main GraphQL query — same concern as count. Filters away after-the-fact only if a serializer redacts. We're closing the gap at source. |

**Decision:** WP-03 sweeps every consumer and either binds the account (default) or — for legitimate system-context bypasses — keeps `accessCheck(false)` with a one-line code comment + an entry in `docs/security/sql-entity-query-access-check-bypass-audit.md`.

**Rationale:** The mission's true risk surface is the consumer sweep, not the core filter. Some existing opt-outs are correct (background jobs, system internals); some are concealed bugs. Every site needs a per-call review.

**Alternatives considered:**
- Make the mission tunnel-visioned on `SqlEntityQuery` and let consumer fixes follow in follow-up issues. **Rejected** — this is the WHOLE point of the mission (filling the security gap). Leaving live bypass call sites in place would deliver less than the spec promises and would surface as a security finding in any later audit.
- Use a config-driven allow-list for `accessCheck(false)` (e.g. `config.access.query_bypass_allowed_callers`). **Rejected** as overengineering. Code-level review + an inline comment + a check-in to the audit doc is enough for v1.

---

## Outstanding items resolved this phase

| Spec assumption | Status | Resolved by |
|---|---|---|
| `AccessChecker::checkMultiple()` exists | **Wrong class** | R-001 |
| `EntityStorage::loadMultiple()` already has access check | **False** | R-002 |
| `execute()` return needs to change | **Not necessary** | R-003 |
| Call-site sweep is 4 packages | **Actually 7** | R-004 |

All four issues are now grounded in observed code, not assumption. Phase 1 proceeds with these facts locked.
