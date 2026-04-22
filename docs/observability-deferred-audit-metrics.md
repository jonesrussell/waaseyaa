# Observability: discovery, routes, and boot (deferred audit follow-up)

Lightweight metrics to watch after manifest, SSR template discovery, and provider graph changes. Prefer **CI baselines** or a small dashboard over heavy instrumentation.

## Suggested metrics

| Metric | Why |
|--------|-----|
| **Provider count** (from `PackageManifest::providers` after boot) | Catches accidental removal or explosion of discovered providers. |
| **Route count** (named routes after `BuiltinRouteRegistrar`) | Surfaces duplicate-name failures (`WaaseyaaRouter` throws) and unexpected route growth. |
| **Kernel / console bootstrap wall time** | Regression detector for discovery or manifest compile cost. |
| **Manifest compile vs cache hit** | Log or count `compileAndCache` vs `load()` cache path in dev/staging if needed. |
| **Entity auto-register** | When `config['entity_auto_register']` is enabled, compare count of definitions from `attributeEntityTypes` vs explicit `entityType()` registrations over time. Parity is covered by `ProviderRegistryTest` (`entity_auto_register_registers_attribute_manifest_classes` / `_off_skips_`). |
| **AI / pipeline jobs** (where applicable) | Success/failure rates for bridges and pipelines—keep versioned HTTP/MCP contracts stable when touching discovery. |

## Where to implement

- **CI:** PHPUnit tests already assert manifest shape, duplicate route names (`BuiltinRouteRegistrarDuplicateRouteTest`), vendor Twig paths (`ThemeServiceProviderVendorTemplatesTest`), and entity auto-register toggles.
- **Production:** Optional `LogLevel::INFO` one-liner after manifest load (provider count, route count) if log volume is acceptable.
