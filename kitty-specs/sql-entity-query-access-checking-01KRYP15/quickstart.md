# Quickstart: SqlEntityQuery Access Checking

A walkthrough for the changes this mission delivers. Operators and developers should be able to read this top-to-bottom and understand what's different, what to do for the common cases, and what the explicit opt-out looks like.

## What's different after this mission lands

Today (v0.1.0): `SqlEntityQuery::accessCheck()` is a no-op stub. **Every query returns every candidate row, regardless of who's asking.** That's the security gap this mission closes.

After: queries default to `accessCheck(true)`. Every row passes through the existing `EntityAccessHandler::check($entity, 'view', $account)` pipeline before it's returned. Rows whose policy returns `forbidden` are dropped. Allowed and neutral rows are kept (open-by-default at entity level).

## The common path — listing entities for the current user

Before this mission, code looked like this:

```
$ids = $storage->getQuery()
    ->condition('status', 'published')
    ->range(0, 25)
    ->execute();
$entities = $storage->loadMultiple($ids);
```

…and silently returned every published row, even ones the user wasn't allowed to see.

After this mission, **bind the account** before `execute()`:

```
$ids = $storage->getQuery()
    ->setAccount($request->attributes->get('_account'))   // NEW
    ->condition('status', 'published')
    ->range(0, 25)
    ->execute();
$entities = $storage->loadMultiple($ids);
```

The `_account` request attribute is set by `SessionMiddleware` (constitution gotcha: read `_account`, not `account`). Every controller that already reads `_account` for capability gates already has the account in scope.

## The system-context bypass

Some queries legitimately need to see every row: index warmers, purge jobs, migrations, internal validators. For those, the explicit `accessCheck(false)` is preserved:

```
$ids = $storage->getQuery()
    ->accessCheck(false)   // explicit system-context bypass
    ->execute();
```

`accessCheck(false)` is the named, audited opt-out. The mission's `docs/security/sql-entity-query-access-check-bypass-audit.md` enumerates every existing site and classifies each as "legitimate" or "fix in WP-03".

## What goes wrong if you forget to bind the account

```
$storage->getQuery()
    ->condition('status', 'published')
    ->range(0, 25)
    ->execute();
//                          ^^^^^^^^^^
// throws MissingQueryAccountException:
// "Cannot execute SqlEntityQuery for entity type 'node': access checking is enabled
//  but no account is bound. Call setAccount() before execute(), or call
//  accessCheck(false) for system contexts."
```

This is the security-critical default. Silent bypass on missing account is the bug this mission deliberately rejects.

## How does this interact with the agent runtime?

The agent runtime (mission `agent-executor-01KRWPK7`) sets the initiator's account on `AgentContext`. Every tool that touches entities — `EntityListTool`, `EntitySearchTool`, `EntityReadTool` — receives that account and threads it through to `getQuery()->setAccount($account)`. After this mission, an agent running as user X cannot see entities X is forbidden from viewing, even if the LLM tries to construct a clever query.

## How does this interact with GraphQL?

GraphQL's `EntityResolver` currently constructs queries with `accessCheck(false)` — the workaround for the missing implementation. After this mission, `EntityResolver` calls `setAccount($context->user)` instead, and drops the `accessCheck(false)`. Two query-count call sites and one main-query call site are touched.

## How does this interact with the admin SPA?

Admin SPA listings flow through `JsonApiController` (e.g. `GET /api/{entity-type}`). The controller reads `_account` from the request, threads it through `getQuery()->setAccount($account)`. Listings now reflect only the entities the admin user is allowed to view. Bypass-capability holders (e.g. `agent.run.bypass_ownership` holders for AgentRun) see the full set because their policy says `allowed`.

## How does count work?

```
$query = $storage->getQuery()->setAccount($account);
$total = $query->count();   // post-filter cardinality when accessCheck(true)
```

Pagers should call `count()` on the filtered query. Callers needing the raw cardinality (rare; usually only system contexts) must explicitly opt out:

```
$rawTotal = $storage->getQuery()->accessCheck(false)->count();
```

## What still doesn't filter

Field-level access (`FieldAccessPolicyInterface`) — out of scope here. It's enforced later in the serialization pipeline by `EntityAccessHandler::filterFields()`. After this mission, entity-level filtering at the query layer + field-level filtering at the serializer layer cover the two axes.

## Where to look for surprises

After the mission lands, listings / GraphQL / agent queries that previously over-returned will now return fewer rows. That is the **correct** outcome. If you see a regression that "fewer rows are returned", check whether those rows are actually access-allowed for the current account first. The bypass audit doc names the call sites that the mission deliberately changed; anything outside that list is a legitimate access denial.
