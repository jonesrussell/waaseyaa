---
work_package_id: WP07
title: 'F3: Per-field auto-save endpoint'
dependencies:
- WP01
- WP02
requirement_refs:
- FR-005
- FR-006
- FR-007
- FR-008
- FR-016
- FR-017
- FR-018
- NFR-001
- NFR-002
- NFR-005
- NFR-007
- NFR-009
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T029
- T030
- T031
- T032
- T033
agent: "claude:opus-4-7:reviewer:reviewer"
shell_pid: "24892"
history:
- date: '2026-04-27'
  note: Generated from plan.md + research.md Q4 + contracts/ F3.
authoritative_surface: packages/api/src/Controller/FieldAutoSaveController.php
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- packages/api/src/Controller/FieldAutoSaveController.php
- packages/api/src/Exception/PayloadTooLargeException.php
- packages/api/src/JsonApiRouteProvider.php
- packages/api/tests/Integration/FieldAutoSaveTest.php
tags: []
---

# WP07 — F3: Per-field auto-save endpoint

## Objective

Ship the per-field auto-save endpoint at `PUT {basePath}/{entityType}/{id}/field/{key}` with body `{"value": "<string>"}`. Idempotent, configurable size cap (NFR-002 default 64 KiB), runs entity policy + field policy, returns precise status codes per contracts/README.md F3.

## Context (read first)

- **spec.md** FR-005, FR-006, FR-007, FR-008, FR-018, NFR-001, NFR-002.
- **research.md** Q4 — register the route from inside `JsonApiRouteProvider::register()`'s entity-type loop.
- **contracts/README.md** F3 — exact status code matrix and request/response shapes.
- **`packages/api/src/JsonApiController.php`** — reference for entity loading, access checking, response shape.
- **`packages/access/src/EntityAccessHandler.php`** — entity-level + field-level access enforcement.
- **`packages/field/src/FieldDefinitionRegistry.php`** — `bundleFieldsFor()` to validate field key against bundle.

## Branch Strategy

- **Planning base**: `main` (after WP01 + WP02 land)
- **Final merge target**: `main`
- Lane via `finalize-tasks`. Use `spec-kitty agent action implement WP07 --agent <name> --mission single-entity-work-surface-01KQ7M1P`.

## Subtasks

### T029 — `FieldAutoSaveController::update()`

**File**: `packages/api/src/Controller/FieldAutoSaveController.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Api\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\Api\Exception\PayloadTooLargeException;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Field\FieldDefinitionRegistryInterface;

final class FieldAutoSaveController
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityAccessHandler $accessHandler,
        private readonly FieldDefinitionRegistryInterface $fieldRegistry,
        private readonly int $maxBodyBytes = 65536,
    ) {}

    public function update(Request $request, string $entityType, string $id, string $key): Response
    {
        // 1. Content-type negotiation (415).
        if (!$this->isJsonContentType($request)) {
            return $this->error(415, 'unsupported_media_type', 'Content-Type must be application/json');
        }

        // 2. Body size guard (422). Check Content-Length first; fallback to careful read.
        $contentLength = (int) $request->headers->get('Content-Length', '0');
        if ($contentLength > $this->maxBodyBytes) {
            return $this->error(422, 'payload_too_large', "Body exceeds maximum {$this->maxBodyBytes} bytes");
        }

        // 3. Parse body (422 on malformed).
        try {
            $body = json_decode($request->getContent(), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->error(422, 'malformed_json', 'Body is not valid JSON');
        }
        if (!is_array($body) || !array_key_exists('value', $body) || !is_string($body['value'])) {
            return $this->error(422, 'malformed_body', 'Body must be {"value": "<string>"}');
        }

        // 4. Load entity (404 if missing).
        $storage = $this->entityTypeManager->getStorage($entityType);
        if ($storage === null) {
            return $this->error(404, 'entity_type_not_found', "Unknown entity type '$entityType'");
        }
        $entity = $storage->load($id);
        if ($entity === null) {
            return $this->error(404, 'entity_not_found', "Entity '$entityType/$id' not found");
        }

        // 5. Validate field key against bundle (404 if not registered).
        $bundle = $entity->bundle();
        $fields = $this->fieldRegistry->bundleFieldsFor($entityType, $bundle);
        if (!isset($fields[$key])) {
            return $this->error(404, 'field_not_registered', "Field '$key' not registered for $entityType:$bundle");
        }

        // 6. Account from request (set by SessionMiddleware as '_account').
        $account = $request->attributes->get('_account');
        if (!$account instanceof AccountInterface) {
            return $this->error(401, 'unauthenticated', 'Authentication required');
        }

        // 7. Entity-level access (403).
        $entityAccess = $this->accessHandler->access($entity, 'update', $account);
        if (!$entityAccess->isAllowed()) {
            return $this->error(403, 'forbidden', "Update denied for $entityType/$id");
        }

        // 8. Field-level access (403). Use isForbidden semantics per CLAUDE.md.
        $fieldAccess = $this->accessHandler->fieldAccess($entity, $key, 'update', $account);
        if ($fieldAccess->isForbidden()) {
            return $this->error(403, 'field_forbidden', "Update denied for field '$key'");
        }

        // 9. Persist.
        $entity->set($key, $body['value']);
        $storage->save($entity);

        // 10. 200 response.
        return new JsonResponse([
            'data' => [
                'id' => $id,
                'type' => $entityType,
                'attributes' => [$key => $entity->get($key)->value],
            ],
        ]);
    }

    private function isJsonContentType(Request $request): bool
    {
        $type = strtolower((string) $request->headers->get('Content-Type', ''));
        return str_starts_with($type, 'application/json');
    }

    private function error(int $status, string $code, string $title): JsonResponse
    {
        return new JsonResponse(
            ['errors' => [['status' => (string) $status, 'code' => $code, 'title' => $title]]],
            $status,
        );
    }
}
```

**Adjustments**:
- `EntityInterface::set()` may not exist on `EntityBase`; only `ContentEntityBase` exposes mutation. Confirm via inspection. If not, use `EntityRepository::save` after constructing a new entity with merged values.
- Field accessor (`$entity->get($key)->value`) reflects the typical `FieldItemList` API; adjust to actual.

### T030 — Body-size guard before full-body read

**Concern (NFR-002)**: do not buffer a 10 MiB body just to reject it.

**Approach**:
1. Trust `Content-Length` header for the 422 short-circuit.
2. If header is missing or zero (some clients send chunked), use a streaming check: open `php://input` via `fopen()`, read up to `maxBodyBytes + 1`, abort with 422 if more is available.
3. For Symfony `Request`, `getContent()` reads the full input — call it only after the size check passes.

Add a small helper:

```php
private function readBoundedBody(Request $request, int $max): string|false
{
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        return $request->getContent();   // fallback
    }
    $buf = stream_get_contents($stream, $max + 1);
    fclose($stream);
    if ($buf === false || strlen($buf) > $max) {
        return false;   // signal "too large"
    }
    return $buf;
}
```

Note: CLAUDE.md gotcha — `php://input` is single-read. If `HttpRequest::createFromGlobals()` already consumed it, this returns empty. Prefer `$request->getContent()` after the Content-Length check; fall back to streaming only when header is absent.

### T031 — Edit `JsonApiRouteProvider`

**File**: `packages/api/src/JsonApiRouteProvider.php`

Add a `registerFieldAutoSave` method invoked from the existing entity-type iteration loop:

```php
foreach ($this->entityTypeManager->getEntityTypes() as $entityType) {
    $this->registerCrud($router, $entityType);
    $this->registerFieldAutoSave($router, $entityType);   // NEW
}

private function registerFieldAutoSave(WaaseyaaRouter $router, $entityType): void
{
    $route = RouteBuilder::create($this->basePath . '/' . $entityType->id() . '/{id}/field/{key}')
        ->controller(FieldAutoSaveController::class . '::update')
        ->methods('PUT')
        ->build();
    $router->addRoute('jsonapi.' . $entityType->id() . '.field_autosave', $route);
}
```

The entity-type id, controller wiring, and access option setup mirror existing CRUD route registration. Inspect `registerCrud` (or whatever the existing method is named) and follow the same idiom.

### T032 — `PayloadTooLargeException`

**File**: `packages/api/src/Exception/PayloadTooLargeException.php`

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Api\Exception;

final class PayloadTooLargeException extends \DomainException
{
    public function __construct(int $maxBytes)
    {
        parent::__construct("Payload exceeds maximum $maxBytes bytes.");
    }
}
```

If `packages/api/src/Exception/` already has an `ApiException` parent class, extend that instead of `\DomainException`. Inspect first.

### T033 — Integration test

**File**: `packages/api/tests/Integration/FieldAutoSaveTest.php`

**Setup**: minimal kernel boot with `node` entity type, a registered bundle template (`#[BundleTemplate]` fixture from WP02), SQLite storage, `EntityAccessHandler`, fixture access policy.

**Cases** (each is one test method):
- 200 happy path: PUT `/api/node/1/field/title` with `{"value": "Hello"}` → response status 200, response body matches contract, persisted state has `title=Hello`.
- 401: PUT without an authenticated account → 401 with `unauthenticated` code.
- 403 entity policy: PUT with account that has no `update` on the node → 403 with `forbidden` code.
- 403 field policy: PUT with account allowed `update` on entity but field policy returns Forbidden → 403 with `field_forbidden` code.
- 404 entity: PUT `/api/node/999/field/title` (entity doesn't exist) → 404 with `entity_not_found`.
- 404 field key: PUT `/api/node/1/field/nonexistent` → 404 with `field_not_registered`.
- 415: PUT with `Content-Type: text/plain` → 415.
- 422 oversize: PUT with body > 64 KiB → 422 with `payload_too_large`. Body is **not** fully read into memory (verify by sending an artificially large body and timing — optional).
- 422 malformed JSON: PUT with body `{not json` → 422 with `malformed_json`.
- 422 missing value: PUT with body `{}` → 422 with `malformed_body`.
- Idempotency: two identical PUTs leave the entity in the same state; the second response shape matches the first.

## Definition of Done

- [ ] `FieldAutoSaveController::update()` implemented with the 10-step happy-path + 8 error branches per contracts/README.md F3.
- [ ] Body-size guard runs before `getContent()` to honor NFR-002.
- [ ] Content-Type negotiation produces 415 for non-JSON.
- [ ] Field-key validation against `FieldDefinitionRegistry::bundleFieldsFor()` produces 404 for unregistered keys.
- [ ] `JsonApiRouteProvider` registers the route for every entity type via the existing iteration loop.
- [ ] `PayloadTooLargeException` exists.
- [ ] Integration test covers all status codes + idempotency.
- [ ] p95 latency ≤ 50 ms server-side under nominal load against SQLite (NFR-001) — measure during integration test, log p95 as informational.
- [ ] `composer phpstan`, `composer cs-check`, full PHPUnit suite pass.
- [ ] No code changes outside `owned_files`.

## Risks

| Risk | Mitigation |
|---|---|
| `EntityInterface::set()` not available on all entity classes | Use `ContentEntityBase::set()` if the entity is a content entity; reject other entity types with a 4xx (or pass through to `EntityRepository::save` with merged values via reflection if the entity isn't `ContentEntityBase`). For first cut, only support `ContentEntityBase` entities and 422 on others. |
| Body-size guard interacts poorly with chunked transfer encoding | Honor Content-Length when present; fall back to streaming read with limit when missing. Document this behavior in the controller. |
| Existing `JsonApiRouteProvider` shape doesn't match the assumed `register()` loop | Read it first; adapt the call site. |
| Field-policy `isForbidden()` semantics differ from entity-policy `isAllowed()` semantics | CLAUDE.md gotcha "Access result semantics differ by level" explicitly states this. Use `!isForbidden()` for field, `isAllowed()` for entity. |

## Reviewer guidance

- Verify the body-size guard runs before any `$request->getContent()` call.
- Verify the field-key validation uses `FieldDefinitionRegistry::bundleFieldsFor($entityType, $bundle)`, with `bundle` resolved from the loaded entity (not from the URL).
- Verify entity-level access uses `isAllowed()` (deny by default) and field-level access uses `!isForbidden()` (allow by default) — this asymmetry is intentional per CLAUDE.md.
- Verify the route registration goes through the existing `JsonApiRouteProvider` iteration, not a separate provider.
- No CHANGELOG edit (WP10).

## Implementation command

```bash
spec-kitty agent action implement WP07 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

Depends on WP01 + WP02.

## Activity Log

- 2026-04-27T16:52:23Z – claude:sonnet-4-6:implementer:implementer – shell_pid=32616 – Started implementation via action command
- 2026-04-27T17:22:40Z – claude:sonnet-4-6:implementer:implementer – shell_pid=32616 – F3 auto-save endpoint; access enforced (check + checkFieldAccess); Content-Length size cap before body read; 12 integration tests pass (all F3 status codes + idempotency); PHPStan level 5 clean; 373 API tests pass
- 2026-04-27T17:23:04Z – claude:opus-4-7:reviewer:reviewer – shell_pid=24892 – Started review via action command
