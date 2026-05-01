# Access Control

<!-- Spec reviewed 2026-05-01 - Auth README added under packages/auth/ (skeleton only — purpose, layer, key classes); no AuthManager/RateLimiter/TwoFactorManager contract change. Reaffirms WP05 paired-nullable invariants and AccessChecker placement (mission #824 WP09 surface F, closes #849) -->
<!-- Spec reviewed 2026-04-25 - packages/user: #[ContentEntityType]/#[ContentEntityKeys] alignment with EntityTypeManager registration parity; no gate or policy semantics change -->
<!-- Spec reviewed 2026-04-24 - Auth HTTP routes moved to Waaseyaa\Routing\AuthOidcRouteServiceProvider; AuthServiceProvider is DI-only; auth controllers and access semantics unchanged (Layer 1 audit remediation) -->
<!-- Spec reviewed 2026-04-11 - User/UserBlock: widened constructors (optional entityTypeId, entityKeys, fieldDefinitions) for ContentEntityBase::duplicateInstance re-entry; no change to gate or policy semantics (#alpha-119) -->
<!-- Spec reviewed 2026-04-08 - LoginController: removed session_write_close() after successful JSON login so Set-Cookie is emitted with the response (#813); no change to gate/session access semantics -->
<!-- Spec reviewed 2026-04-07 - packages/auth composer.json: waaseyaa/* requires use ^0.1 for split/Packagist consumers (#1138); no access API change -->
<!-- Spec reviewed 2026-04-03c - auth controller review fixes: JSON_THROW_ON_ERROR, session guard, AccountInterface null check (#571) -->
<!-- Spec reviewed 2026-04-08 - composer manifest policy normalization for packages/access, packages/auth, packages/user; no runtime access-control behavior change -->
<!-- Spec reviewed 2026-04-08b - restored packages/access symfony/routing floor from ^7.3 back to ^7.0 to avoid unnecessary downstream constraint tightening -->
<!-- Spec reviewed 2026-05-01 - AccessChecker canonical placement: source lives at packages/access/src/AccessChecker.php with namespace Waaseyaa\Access; routing depends on access (downward); package tables, file/namespace headers, and dir-tree visualization corrected (mission #824 WP05 surface A, closes #832) -->

Waaseyaa's access control system spans three packages: `packages/access/` (core primitives), `packages/routing/` (route-level checks), and `packages/user/` (session resolution, password reset). This document covers entity-level and route-level access. For field-level access, see `docs/specs/field-access.md`.

## Public Surface

Authoritative dispositions are in `docs/public-surface-map.php`, verified by `PublicSurfaceVerificationTest`.

**Public API** (stable, semver-protected):

| Package | Interfaces/Classes |
|---------|-------------------|
| access | `AccountInterface`, `AccessPolicyInterface`, `FieldAccessPolicyInterface`, `PermissionHandlerInterface`, `GateInterface` |

**`@internal`** (implementation details, may change without notice):

| Package | Interface/Class | Reason |
|---------|----------------|--------|
| access | `ErrorPageRendererInterface` | Error page rendering detail, not a consumer contract |
| auth | `AuthTokenRepositoryInterface` | Token storage internals |
| auth | `RateLimiterInterface` | Auth-specific rate limiter, distinct from Foundation's public `RateLimiterInterface` |

## Packages

| Package | Path | Provides |
|---------|------|----------|
| access | `packages/access/src/` | AccessPolicyInterface, AccessResult, AccessStatus, EntityAccessHandler, AccountInterface, FieldAccessPolicyInterface, PermissionHandler, Gate, EntityAccessGate, AuthorizationMiddleware, **AccessChecker** (route-level access) |
| auth | `packages/auth/src/` | Controllers and services for login/register/reset/verify; **HTTP route registration** is in `packages/routing` (`AuthOidcRouteServiceProvider`), not in `AuthServiceProvider` |
| routing | `packages/routing/src/` | `AuthOidcRouteServiceProvider` wires auth and OIDC HTTP routes (AccessChecker is owned by `waaseyaa/access`, not routing — mission #824 WP05 surface A) |
| user | `packages/user/src/` | SessionMiddleware (account resolution), UserServiceProvider (user entity type registration) |

## Core Interfaces

### AccessPolicyInterface

**File:** `packages/access/src/AccessPolicyInterface.php`
**Namespace:** `Waaseyaa\Access`

```php
interface AccessPolicyInterface
{
    public function access(
        EntityInterface $entity,
        string $operation, // 'view', 'update', or 'delete'
        AccountInterface $account,
    ): AccessResult;

    public function createAccess(
        string $entityTypeId,
        string $bundle,
        AccountInterface $account,
    ): AccessResult;

    public function appliesTo(string $entityTypeId): bool;
}
```

- `access()` checks an existing entity for a given operation.
- `createAccess()` checks whether an entity of the given type/bundle can be created.
- `appliesTo()` scopes which entity types this policy governs. EntityAccessHandler skips policies that return `false`.

### AccountInterface

**File:** `packages/access/src/AccountInterface.php`
**Namespace:** `Waaseyaa\Access`

```php
interface AccountInterface
{
    public function id(): int|string;
    public function hasPermission(string $permission): bool;
    public function getRoles(): array; // string[]
    public function isAuthenticated(): bool;
}
```

**Critical:** `AccountInterface` lives in the `access` package, not `user`. The `User` entity and `AnonymousUser` live in `packages/user/`. Access must never depend on User to avoid circular package dependencies. Middleware needing an account should type-hint `AccountInterface`, not concrete `AnonymousUser`.

## Access Result Semantics

**File:** `packages/access/src/AccessResult.php`
**Namespace:** `Waaseyaa\Access`

AccessResult is a `final readonly class` with three states defined in the `AccessStatus` enum:

```php
enum AccessStatus: string
{
    case ALLOWED = 'allowed';
    case NEUTRAL = 'neutral';
    case FORBIDDEN = 'forbidden';
}
```

### Factory Methods

```php
AccessResult::allowed(string $reason = ''): AccessResult
AccessResult::neutral(string $reason = ''): AccessResult
AccessResult::forbidden(string $reason = ''): AccessResult
AccessResult::unauthenticated(string $reason = ''): AccessResult
```

`$reason` is a non-nullable string (`''` default) — callers needing a fallback message must use `!== ''` rather than `??`; the null-coalesce is dead code and PHPStan flags it as `nullCoalesce.property`.

### State Checks

```php
$result->isAllowed(): bool   // status === ALLOWED
$result->isNeutral(): bool   // status === NEUTRAL
$result->isForbidden(): bool // status === FORBIDDEN
```

### Combination Logic

**`orIf()`** -- OR logic, used by EntityAccessHandler to combine policy results:

- Forbidden wins over everything (short-circuit)
- Either Allowed yields Allowed
- Both Neutral yields Neutral

**`andIf()`** -- AND logic, used by AccessChecker to combine route requirements:

- Forbidden wins over everything (short-circuit)
- Both must be Allowed for Allowed
- At least one Neutral yields Neutral

### Entity-Level Evaluation Pattern

Entity access uses **deny-by-default** with `isAllowed()`:

```php
// EntityAccessHandler::check() starts with Neutral, combines via orIf().
// Controller checks: $result->isAllowed()
// Neutral means "no policy granted" = denied.
```

This is intentionally asymmetric with field-level access, which uses `!isForbidden()`. See `docs/specs/field-access.md`.

## Entity Access Handler

**File:** `packages/access/src/EntityAccessHandler.php`
**Namespace:** `Waaseyaa\Access`

Orchestrates policy evaluation. Not a `final class` (can be extended).

```php
class EntityAccessHandler
{
    public function __construct(array $policies = []) // AccessPolicyInterface[]
    public function addPolicy(AccessPolicyInterface $policy): void

    public function check(
        EntityInterface $entity,
        string $operation,       // 'view', 'update', 'delete'
        AccountInterface $account,
    ): AccessResult;

    public function checkCreateAccess(
        string $entityTypeId,
        string $bundle,
        AccountInterface $account,
    ): AccessResult;

    public function checkFieldAccess(
        EntityInterface $entity,
        string $fieldName,
        string $operation,       // 'view' or 'edit'
        AccountInterface $account,
    ): AccessResult;

    public function filterFields(
        EntityInterface $entity,
        array $fieldNames,       // string[]
        string $operation,       // 'view' or 'edit'
        AccountInterface $account,
    ): array; // string[] — fields not forbidden
}
```

### Evaluation Algorithm

For `check()` and `checkCreateAccess()`:

1. Start with `AccessResult::neutral('No policy provided an opinion.')`.
2. Iterate registered policies. Skip those where `appliesTo($entityTypeId)` returns false.
3. Call `$policy->access(...)` or `$policy->createAccess(...)`.
4. Combine results with `orIf()` (any Allowed grants access).
5. Short-circuit on Forbidden -- nothing can override it.
6. Return final result.

For `checkFieldAccess()` and `filterFields()`, see `docs/specs/field-access.md`.

### Policy Registration

Policies are passed to the constructor or added via `addPolicy()`. In the current post-M10 boot flow, `AccessPolicyRegistry` builds the handler from `PackageManifest::$policies`, while the kernel still exposes the resulting gate to `AccessChecker` during boot:

```php
$accessHandler = new EntityAccessHandler([
    new NodeAccessPolicy(),
    new TermAccessPolicy(),
    new ConfigEntityAccessPolicy(entityTypeIds: ['node_type', 'taxonomy_vocabulary', ...]),
]);
$gate = new EntityAccessGate($accessHandler);
$accessChecker = new AccessChecker(gate: $gate);
```

### Bundle-scoped policies

Multi-bundle entity types (e.g. `group`) may need different access rules per bundle. The `#[AccessPolicy]` attribute carries a `bundles` parameter for this:

```php
#[AccessPolicy(id: 'group_team', entityTypes: ['group'], bundles: ['team'])]
final class TeamAccessPolicy implements AccessPolicyInterface { ... }
```

- `bundles: []` (the default) — policy applies to every bundle of the named entity types. All pre-existing single-bundle policies retain their prior semantics without edits.
- `bundles: ['alpha', 'beta']` — policy applies only when the entity being checked has one of those bundles.

`EntityAccessHandler` keeps a parallel `$bundleFilters` array, populated from the attribute at registration time via `resolveBundles()` (reflection over `#[AccessPolicy]`). The filter is applied at every gate the handler exposes: `check()`, `checkCreateAccess()`, and `checkFieldAccess()`. A policy whose `bundles` list is non-empty is skipped when the resolved bundle does not match; a policy with an empty list is always considered. No ordering or combinator changes — the filter runs before `appliesTo($entityTypeId)`, and the rest of the evaluation algorithm is unchanged.

For the storage-side contract this surfaces (how bundle membership is resolved from per-bundle subtables and field registration), see `docs/specs/bundle-scoped-fields.md §Access`.

## Gate System

The Gate is a separate access mechanism from EntityAccessHandler. It resolves policies by entity type and delegates ability checks to method calls.

### GateInterface

**File:** `packages/access/src/Gate/GateInterface.php`
**Namespace:** `Waaseyaa\Access\Gate`

```php
interface GateInterface
{
    public function allows(string $ability, mixed $subject, ?object $user = null): bool;
    public function denies(string $ability, mixed $subject, ?object $user = null): bool;
    public function authorize(string $ability, mixed $subject, ?object $user = null): void;
        // throws AccessDeniedException
}
```

### Gate (Implementation)

**File:** `packages/access/src/Gate/Gate.php`
**Namespace:** `Waaseyaa\Access\Gate`

```php
final class Gate implements GateInterface
{
    public function __construct(private readonly array $policies = [])
}
```

Policy resolution strategy:
1. Check for `#[PolicyAttribute(entityType: '...')]` on the policy class.
2. Fall back to naming convention: `NodePolicy` maps to entity type `node` (PascalCase to snake_case).

Ability delegation: `$gate->allows('update', $node)` calls `$policy->update($user, $node)`. If the method does not exist, ability is denied.

### EntityAccessGate (Adapter)

**File:** `packages/access/src/Gate/EntityAccessGate.php`
**Namespace:** `Waaseyaa\Access\Gate`

```php
final class EntityAccessGate implements GateInterface
{
    public function __construct(private readonly EntityAccessHandler $handler)
}
```

Adapter that bridges `GateInterface` to `EntityAccessHandler`, reusing existing `AccessPolicyInterface` policies. Translation logic:

- `allows($ability, EntityInterface $subject, AccountInterface $user)` → `$handler->check($subject, $ability, $user)->isAllowed()`
- `allows('create', string $entityTypeId, AccountInterface $user)` → `$handler->checkCreateAccess($entityTypeId, '', $user)->isAllowed()`
- String subject + non-`create` ability → `false` (instance required for view/update/delete)
- Non-`AccountInterface` user or unsupported subject type → `false` with `error_log()` diagnostic

Wired in `public/index.php`: wraps `EntityAccessHandler` and is passed to `AccessChecker(gate: $gate)`. Policy exceptions are caught, logged, and treated as denial.

### PolicyAttribute

**File:** `packages/access/src/Gate/PolicyAttribute.php`
**Namespace:** `Waaseyaa\Access\Gate`

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PolicyAttribute
{
    public function __construct(
        public readonly string $entityType,
    ) {}
}
```

### AccessPolicy (Plugin Discovery Attribute)

**File:** `packages/access/src/Attribute/AccessPolicy.php`
**Namespace:** `Waaseyaa\Access\Attribute`

Extends `WaaseyaaPlugin`. Used for attribute-based plugin discovery (distinct from `PolicyAttribute` for the Gate).

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AccessPolicy extends WaaseyaaPlugin
{
    public function __construct(
        string $id,
        public readonly array $entityTypes = [],
        public readonly array $bundles = [],  // see bundle-scoped-fields.md §Access
        string $label = '',
        string $description = '',
    ) {}
}
```

The optional `bundles:` parameter scopes a policy to specific bundles of the listed entity types. An empty array (default) preserves existing semantics — the policy applies to every bundle. See [`bundle-scoped-fields.md`](./bundle-scoped-fields.md#access) for the full contract.

### AccessDeniedException

**File:** `packages/access/src/Gate/AccessDeniedException.php`

```php
final class AccessDeniedException extends \RuntimeException
{
    public function __construct(
        public readonly string $ability,
        public readonly mixed $subject,
        string $message = '',
    ) {}
}
```

## Route Access Control

**File:** `packages/access/src/AccessChecker.php`
**Namespace:** `Waaseyaa\Access`

The class lives in the access package because route-level access checking is the routing-time consumer of the access subsystem (gates, policies, account context). The routing package depends on access, never the other way around.

```php
final class AccessChecker
{
    public function __construct(private readonly ?GateInterface $gate = null)

    public function check(Route $route, AccountInterface $account): AccessResult

    public static function applyGateToRoute(
        Route $route,
        string $ability,
        mixed $subject = null,
    ): void
}
```

### Route Options

Routes declare access requirements via Symfony Route options. Multiple requirements combine with AND logic (all must pass).

| Option | Type | Behavior |
|--------|------|----------|
| `_public` | `true` | Always allow (no auth required) |
| `_authenticated` | `true` | Require non-anonymous identity; returns `AccessResult::unauthenticated()` (401) if anonymous. Short-circuits before other checks. |
| `_session` | `true` or `string[]` | Require active session. When array, requires specific session keys to be present. |
| `_permission` | `string` | Require specific permission via `$account->hasPermission()` |
| `_role` | `string` | Require role (comma-separated for multiple); checks `$account->getRoles()` |
| `_gate` | `array{ability: string, subject?: mixed}` | Require gate ability check |

If no access requirements are present on the route, returns `AccessResult::neutral()`. AuthorizationMiddleware treats Neutral as passthrough (open-by-default at the route level).

### Evaluation

1. Check `_authenticated` first (short-circuit: returns `unauthenticated` immediately if anonymous).
2. Check `_session` (short-circuit: returns `forbidden` if session requirements not met).
3. Start with `AccessResult::allowed()`.
4. For each remaining requirement present (`_public`, `_permission`, `_role`, `_gate`), compute its result and combine via `andIf()`.
5. If no requirements found, return `AccessResult::neutral()`.
6. Return combined result.

## Permission Handler

**File:** `packages/access/src/PermissionHandler.php`
**Namespace:** `Waaseyaa\Access`

```php
final class PermissionHandler implements PermissionHandlerInterface
{
    public function registerPermission(string $id, string $title, string $description = ''): void
    public function getPermissions(): array // array<string, array{title: string, description: string}>
    public function hasPermission(string $permission): bool
}
```

Permissions are declared in `composer.json` under `extra.waaseyaa.permissions` and collected into the package manifest by `PackageManifestCompiler`.

```json
{
  "extra": {
    "waaseyaa": {
      "permissions": {
        "access content": { "title": "Access published content" },
        "create article": { "title": "Create Article content" }
      }
    }
  }
}
```

## Authorization Pipeline

**Entry point:** `public/index.php`

The authorization pipeline is a pair of HTTP middleware executed in order:

```
Request -> SessionMiddleware -> AuthorizationMiddleware -> Final Handler -> Response
```

### SessionMiddleware

**File:** `packages/user/src/Middleware/SessionMiddleware.php`
**Namespace:** `Waaseyaa\User\Middleware`

```php
final class SessionMiddleware implements HttpMiddlewareInterface
{
    public function __construct(
        private readonly EntityStorageInterface $userStorage,
        private readonly ?AccountInterface $devFallback = null,
        ?LoggerInterface $logger = null,
        private readonly ?array $sessionCookieOptions = null,
        private readonly array $trustedProxies = [],
    ) {}

    public function process(Request $request, HttpHandlerInterface $next): Response
}
```

Behavior:
1. Reads `$_SESSION['waaseyaa_uid']` (via `$request->attributes->get('_session')` or `$_SESSION`).
2. Loads User entity via `$this->userStorage->load($uid)`.
3. Falls back to `AnonymousUser` if: no UID in session, load fails, or loaded entity is not `AccountInterface`.
4. Sets `$request->attributes->set('_account', $account)`.
5. Calls `$next->handle($request)`.
6. Creates `NativeSession` with `$trustedProxies` so session cookie secure flag respects proxy trust.

**Trusted proxy guard:** Both `NativeSession::isSecureConnection()` and `SessionMiddleware::isHttpsRequest()` only trust `X-Forwarded-Proto` when `REMOTE_ADDR` matches a configured trusted proxy IP. The header comparison is case-insensitive (`HTTPS`, `Https`, `https` all match). Both methods return `false` early when `REMOTE_ADDR` is empty or missing, preventing accidental matches against empty-string entries in the trusted list. Without trusted proxies configured, the header is ignored. Only exact IP addresses are supported (no CIDR notation). Configure via `'trusted_proxies' => ['127.0.0.1']` in `config/waaseyaa.php`.

Does not handle login/logout. Only resolves "who is making this request."

Lives in the `user` package because it depends on `User`, `AnonymousUser`, and entity storage.

### AuthorizationMiddleware

**File:** `packages/access/src/Middleware/AuthorizationMiddleware.php`
**Namespace:** `Waaseyaa\Access\Middleware`

```php
final class AuthorizationMiddleware implements HttpMiddlewareInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
    ) {}

    public function process(Request $request, HttpHandlerInterface $next): Response
}
```

Behavior:
1. Reads matched `Route` from `$request->attributes->get('_route_object')`. If null, passes through.
2. Reads `AccountInterface` from `$request->attributes->get('_account')`. If missing/invalid, returns 403 JSON:API error.
3. Delegates to `$this->accessChecker->check($route, $account)`.
4. If Forbidden: returns 403 JSON:API response with `$result->reason`.
5. If Neutral (no requirements on route): passes through (open-by-default).
6. If Allowed: calls `$next->handle($request)`.

Requires SessionMiddleware to run first. Enforced by middleware priority ordering.

### 403 Response Format

```json
{
  "jsonapi": { "version": "1.1" },
  "errors": [{
    "status": "403",
    "title": "Forbidden",
    "detail": "The 'administer site' permission is required."
  }]
}
```

Content-Type: `application/vnd.api+json`.

## Discovery

Policies and permissions are discovered at build time via `PackageManifestCompiler`:

- **Policy discovery:** `#[AccessPolicy]` attribute is scanned during class scanning. Discovered policies stored as `array<string, string>` (entity type ID => FQCN) in the manifest.
- **Permission discovery:** `composer.json` `extra.waaseyaa.permissions` collected into `PackageManifest::$permissions`.

Layer discipline: Foundation (layer 0) uses string constants for attribute class names to avoid importing from higher layers. `ReflectionClass::getAttributes()` accepts string class names.

## User/Auth HTTP Surfaces (post-M10 package ownership)

**Packages:** `packages/auth/`, `packages/user/`
**Registered by:** package service providers discovered from composer metadata. `AuthServiceProvider` owns all auth-related request surfaces: login, logout, me, registration, password-reset, and email-verification controllers. These controllers are callable objects (implementing `__invoke(Request): JsonResponse`) registered via `RouteBuilder::controller()`.

### Endpoint Access Requirements

#### AuthServiceProvider-owned routes

| Endpoint | Route option | Controller |
|----------|-------------|------------|
| `POST /api/auth/login` | `_public: true` | `LoginController` |
| `POST /api/auth/logout` | `_public: true` | `LogoutController` |
| `GET /api/user/me` | `_public: true` | `MeController` |
| `POST /api/auth/register` | `_public: true` | `RegisterController` |
| `POST /api/auth/forgot-password` | `_public: true` | `ForgotPasswordController` |
| `POST /api/auth/reset-password` | `_public: true` | `ResetPasswordController` |
| `POST /api/auth/verify-email` | `_public: true` | `VerifyEmailController` |
| `POST /api/auth/resend-verification` | `_authenticated: true` | `ResendVerificationController` |

`ResendVerificationController` requires an active authenticated session. `AccessChecker` short-circuits with `unauthenticated` (401) if the `_account` attribute on the request is anonymous. The other seven endpoints are public — no session required. `LoginController` applies its own rate limiting (5 attempts per IP per 60s).

All auth controllers accept an optional `?LoggerInterface $logger` (defaults to `NullLogger`). DevLog-mode verification/reset URLs and best-effort email failures are logged via this interface rather than `error_log()`.

**Configuration resolution:** `UserServiceProvider` registers `AuthMailer` with `MailerInterface` (from `MailServiceProvider`), `authEmailConfigured` when trimmed `mail.sendgrid_api_key` and `mail.from_address` are both non-empty, Twig from `SsrServiceProvider::getTwigEnvironment()`, `baseUrl` in precedence order: `$config['app']['url']`, then `APP_URL`, then `http://localhost:8000`, and `appName`: `$config['app']['name']` → `APP_NAME` → `Waaseyaa`. When mail is not configured, `AuthMailer::isConfigured()` is false and auth email sends no-op without hitting the transport. Consumer apps that set neither app URL config nor env var still boot with localhost defaults adequate for dev and CI. Production should set `APP_URL` (via `.env` or `config/waaseyaa.php`) so reset and verification links use the correct hostname.

### Rate Limiting

All auth endpoints apply rate limiting via `RateLimiterInterface` keyed on IP or user identity. Two implementations exist: `RateLimiter` (in-memory, resets per process) and `DatabaseRateLimiter` (SQLite-backed via `DatabaseInterface`, persists across restarts). `AuthServiceProvider` registers `DatabaseRateLimiter` by default, resolving `DatabaseInterface` from the container. `HttpKernel` also injects a `DatabaseRateLimiter` into `ControllerDispatcher` for the login endpoint. The in-memory `RateLimiter` remains as a fallback when no `RateLimiterInterface` is injected (e.g., in tests):

| Endpoint | Limit |
|----------|-------|
| `POST /api/auth/register` | 5 per IP per 15 min |
| `POST /api/auth/forgot-password` | 3 per email per 15 min, 10 per IP per hour |
| `POST /api/auth/reset-password` | 10 per IP per hour |
| `POST /api/auth/verify-email` | 10 per IP per hour |
| `POST /api/auth/resend-verification` | 3 per user per hour |

Rate limit responses return 429 with a `Retry-After` header.

### Anti-Enumeration

All user-facing responses from `ForgotPasswordController` and `RegisterController` are generic — the system never reveals whether an account exists for a given email. Constant-time comparisons are used where needed to prevent timing side-channels.

### AuthTokenRepository

Replaces `PasswordResetTokenRepository` (which used raw PDO). Uses `DatabaseInterface` (DBAL). Tokens are 64-char hex strings hashed with HMAC-SHA256 using `auth.token_secret` from config. Plain tokens are never persisted.

**Token types and default TTLs:**

| Type | Default TTL | Notes |
|------|-------------|-------|
| `password_reset` | 1 hour | Single-use; revokes previous tokens for same user |
| `email_verification` | 24 hours | Single-use; revokes previous tokens for same user |
| `invite` | 7 days | Single-use; `user_id` is NULL |

### Auth Configuration

Registered under `auth` key in `config/waaseyaa.php`:

```php
'auth' => [
    'registration' => 'admin',        // 'admin' | 'open' | 'invite'
    'require_verified_email' => false, // true = block unverified users from AdminShell
    'mail_missing_policy' => null,     // null = auto (dev-log in dev, fail in prod)
    'token_secret' => env('AUTH_TOKEN_SECRET', ''),
    'token_ttl' => [
        'password_reset' => 3600,
        'email_verification' => 86400,
        'invite' => 604800,
    ],
],
```

`mail_missing_policy` auto-resolves: `dev-log` when `APP_ENV` is `local`/`development`; `fail` in production. Explicit values `'dev-log'`, `'fail'`, and `'silent'` override the auto behavior.

## File Reference

```
packages/access/src/
    AccessPolicyInterface.php        - Entity access policy contract
    FieldAccessPolicyInterface.php   - Field access policy contract (see field-access.md)
    AccessResult.php                 - Tri-state value object (Allowed/Neutral/Forbidden)
    AccessStatus.php                 - Enum: ALLOWED, NEUTRAL, FORBIDDEN
    EntityAccessHandler.php          - Orchestrates policy evaluation
    AccountInterface.php             - User account contract (id, permissions, roles)
    PermissionHandler.php            - In-memory permission registry
    PermissionHandlerInterface.php   - Permission registry contract
    Attribute/
        AccessPolicy.php             - Plugin discovery attribute
    Gate/
        GateInterface.php            - Gate contract (allows/denies/authorize)
        Gate.php                     - Gate implementation with policy resolution
        EntityAccessGate.php         - Adapter bridging GateInterface to EntityAccessHandler
        PolicyAttribute.php          - Maps policy class to entity type
        AccessDeniedException.php    - Thrown by Gate::authorize()
    AccessChecker.php                - Route option access checks (_public, _authenticated, _session, _permission, _role, _gate)
    RedirectValidator.php            - Open-redirect prevention (isSafe/sanitize)
    ErrorPageRendererInterface.php   - Error page rendering contract (render -> ?Response) [@internal — not a public consumer contract]
    Middleware/
        AuthorizationMiddleware.php  - Route-level access enforcement

packages/user/src/
    Middleware/
        SessionMiddleware.php        - Resolves AccountInterface from session
        BearerAuthMiddleware.php     - JWT and API key authentication via Bearer tokens (priority: 40)

public/index.php                     - Front controller; wires the pipeline
```

---

## Parent-Delegated Policies

Added in mission `single-entity-work-surface-01KQ7M1P`. A **parent-delegated access policy** delegates access decisions for a child entity to the policy registered for its parent entity.

### Pattern

```php
#[PolicyAttribute('attachment')]
final class ParentDelegatedAccessPolicy implements AccessPolicyInterface
{
    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        $parentType = (string) $entity->get('parent_entity_type');
        $parentId = (string) $entity->get('parent_entity_id');

        if ($parentType === '' || $parentId === '') {
            return AccessResult::neutral('Attachment has no parent entity reference.');
        }

        $parent = $this->entityTypeManager->getStorage($parentType)->load($parentId);
        if ($parent === null) {
            return AccessResult::neutral('Parent entity not found.');
        }

        return $this->accessHandler->check($parent, $operation, $account);
    }
}
```

### Semantics

- `AccessResult::neutral()` (not `forbidden()`) is returned when the parent cannot be resolved. Under entity-level `isAllowed()` semantics, neutral effectively denies access without encoding an explicit Forbidden decision. This is intentional — orphaned child entities must not silently become accessible.
- `createAccess()` is not delegated by this policy — create access for child entities is governed at the API layer (e.g., require `update` on the parent before allowing attachment creation).
- The policy auto-discovers its entity type via `#[PolicyAttribute('attachment')]`.

### Canonical implementation

`Waaseyaa\Attachment\Policy\ParentDelegatedAccessPolicy` in `packages/attachment/src/Policy/`.

→ See `docs/specs/work-surface.md` F4 for the attachment wire-up.
→ See `docs/specs/field-access.md` for field-level access semantics (open-by-default).
