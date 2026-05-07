# Contract: Resolution and dispatch boundary (draft)

**Mission**: `foundation-symfony-fallback-elimination-01KQZR1`  
**Status**: Draft — WP02 ratifies after WP01 inventory.

## 1. Kernel-owned service resolution

- **Single semantic source**: Services listed in `docs/specs/infrastructure.md` under `ProviderRegistryKernelServices` SHALL NOT be re-resolved via parallel if-chains in `HttpKernelServiceResolver`.
- **HTTP resolver role**: `HttpServiceResolverInterface` resolves **user-declared** controller constructor parameters by class name via provider bindings first; kernel-owned types SHALL flow through the same `KernelServicesInterface::get()` semantics (or a delegated helper with one implementation).

## 2. Routing match failures

- **Foundation HTTP** SHALL NOT branch on `\Symfony\Component\Routing\Exception\ResourceNotFoundException` or `MethodNotAllowedException` for expected routing outcomes once WP04 completes.
- **Acceptable**: A routing-layer adapter in `waaseyaa/routing` (or matcher wrapper) that translates Symfony matcher behavior into Waaseyaa-owned types or return values consumed by `HttpKernel`.

## 3. `_controller` attribute

- **Default contract** entering `ControllerDispatcher`: string `FQCN::method`, or `Closure` / invokable object.
- **Array `[FQCN, method]`**: either eliminated at route registration time or explicitly deprecated with a removal milestone; not silently normalized in multiple places.

## 4. Consumer-visible errors

- JSON:API-shaped **404** and **405** responses for unmatched routes remain valid unless a documented deprecation changes content-type or body shape.
