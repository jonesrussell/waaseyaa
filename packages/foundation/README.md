# waaseyaa/foundation

**Layer 0 — Foundation**

Application bootstrapping and kernel infrastructure for Waaseyaa.

Provides `AbstractKernel` (base for `HttpKernel` and `ConsoleKernel`), `ServiceProvider` base class, `PackageManifestCompiler` (discovers access policies, middleware, and providers via attribute scanning), and `DomainEvent` primitives. Kernels intentionally import from all layers as entry-point orchestrators. Run `waaseyaa optimize:manifest` after adding new providers or policies.

Key classes: `AbstractKernel`, `HttpKernel`, `ConsoleKernel`, `ServiceProvider`, `PackageManifestCompiler`.
