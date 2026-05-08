# Contract: Resolution and dispatch boundary

**Mission**: `foundation-symfony-fallback-elimination-01KQZR1`  
**Status**: **Approved** (ratified against `artifacts/fallback-inventory.md`, WP01, 2026-05-07)

## 1. Kernel-owned service resolution

**Approved** — unchanged from draft.

- **Single semantic source**: Services listed in `docs/specs/infrastructure.md` under `ProviderRegistryKernelServices` SHALL NOT be re-resolved via parallel if-chains in `HttpKernelServiceResolver`.
- **HTTP resolver role**: `HttpServiceResolverInterface` resolves **user-declared** controller constructor parameters by class name via provider bindings first; kernel-owned types SHALL flow through the same `KernelServicesInterface::get()` semantics (or a delegated helper with **one** implementation shared with / delegating to `ProviderRegistryKernelServices`).

## 2. Routing match failures

**Revised** — WP numbering corrected: target is **WP03** (merged resolver + routing WP).

- **Foundation HTTP** (`packages/foundation/src/Kernel/HttpKernel.php`) SHALL NOT use `catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException)` or `MethodNotAllowedException` for expected routing outcomes after WP03.
- **Normative approach**: `packages/routing/src/WaaseyaaRouter.php` (or a dedicated private helper in `waaseyaa/routing`) catches Symfony matcher failures and maps them to a Waaseyaa-owned outcome consumed by `HttpKernel` — e.g. a small result value object, discriminated union array shape, or domain-specific exception type **defined under `Waaseyaa\Routing`** — such that foundation imports **no** `Symfony\Component\Routing\Exception\*` classes for this control path.
- **Observable**: HTTP 404 / 405 JSON:API bodies remain materially the same as pre-mission behavior unless CHANGELOG documents a deprecation.

## 3. `_controller` attribute

**Approved** — WP04 complete: normalization lives in `waaseyaa/routing`, not in `ControllerDispatcher`.

- **Default contract** entering `ControllerDispatcher`: string `FQCN::method`, or `Closure` / invokable object.
- **Array `[FQCN, method]`**: `RouteBuilder::controller()` and `RouteBuilder::normalizeControllerDefault()` (used when `HttpKernel` copies match parameters onto the request) coerce to `FQCN::method`. `ControllerDispatcher` does not rewrite `_controller`.

## 4. Consumer-visible errors

**Approved** — unchanged from draft.

- JSON:API-shaped **404** and **405** responses for unmatched routes remain valid unless a documented deprecation changes content-type or body shape.
