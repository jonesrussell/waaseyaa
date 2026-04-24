# Layer 0 Forensic Audit (Foundation)

Generated: 2026-04-24T21:13:54+00:00 UTC

## 1. Canonical scope

Layer 0 (Foundation) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: analytics, cache, database-legacy, error-handler, foundation, geo, http-client, i18n, ingestion, mail, mercure, oauth-provider, plugin, queue, scheduler, state, typed-data, validation.

**Drift vs CLAUDE.md:** `in_script_not_in_claude` = ["analytics","geo","mercure","oauth-provider"], `in_claude_not_in_script` = [].

## 2. Priority-ordered findings

### L0-DOC-CLAUDE
- **Priority:** P1 | **Category:** public_api_documentation | **Severity:** low
- **Message:** CLAUDE.md Layer 0 table and bin/check-package-layers disagree on package set.
```json
{
    "in_script_not_in_claude": [
        "analytics",
        "geo",
        "mercure",
        "oauth-provider"
    ],
    "in_claude_not_in_script": []
}
```

### L0-USE-1
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/cache/src/Listener/EntityCacheSubscriber.php",
    "from_package": "cache",
    "import": "Waaseyaa\\Entity\\Event\\EntityEvents",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-2
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/cache/src/Listener/ConfigCacheInvalidator.php",
    "from_package": "cache",
    "import": "Waaseyaa\\Config\\Event\\ConfigEvent",
    "target_package": "config",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-3
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/cache/src/Listener/EntityCacheInvalidator.php",
    "from_package": "cache",
    "import": "Waaseyaa\\Entity\\Event\\EntityEvent",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-4
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/cache/src/CacheConfigResolver.php",
    "from_package": "cache",
    "import": "Waaseyaa\\Access\\AccountInterface",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-5
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/BroadcastRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\Controller\\BroadcastController",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-6
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SchemaRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\EntityAccessHandler",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-7
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SchemaRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\Controller\\SchemaController",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-8
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SchemaRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\OpenApi\\OpenApiGenerator",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-9
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SchemaRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\Schema\\SchemaPresenter",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-10
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SchemaRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-11
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SearchRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\EmbeddingProviderFactory",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-12
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SearchRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\SearchController",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-13
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SearchRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\SqliteEmbeddingStorage",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-14
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SearchRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\ResourceSerializer",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-15
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/SearchRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManagerInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-16
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/WaaseyaaContext.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\AccountInterface",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-17
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/WaaseyaaContext.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\Controller\\BroadcastStorage",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-18
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/JsonApiRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\EntityAccessHandler",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-19
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/JsonApiRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\JsonApiController",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-20
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/JsonApiRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\JsonApiDocument",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-21
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/JsonApiRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\JsonApiError",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-22
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/JsonApiRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\ResourceSerializer",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-23
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/JsonApiRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-24
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/McpRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\EntityAccessHandler",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-25
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/McpRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\EmbeddingProviderFactory",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-26
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/McpRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\SqliteEmbeddingStorage",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-27
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/McpRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\ResourceSerializer",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-28
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/McpRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-29
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/McpRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Mcp\\McpController",
    "target_package": "mcp",
    "target_layer": 6,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-30
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/EntityTypeLifecycleRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeIdNormalizer",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-31
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/EntityTypeLifecycleRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeLifecycleManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-32
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Http/Router/EntityTypeLifecycleRouter.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-33
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Diagnostic/BootDiagnosticReport.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-34
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Diagnostic/HealthChecker.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-35
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Diagnostic/HealthChecker.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManagerInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-36
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Diagnostic/HealthChecker.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\Field\\FieldDefinitionRegistryInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-37
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Diagnostic/HealthChecker.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\EntityStorage\\SqlSchemaHandler",
    "target_package": "entity-storage",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-38
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/Bootstrap/AccessPolicyRegistry.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\EntityAccessHandler",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-39
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/Bootstrap/ContentTypeValidator.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-40
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/Bootstrap/AppEntityTypeLoader.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-41
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/Bootstrap/ProviderRegistry.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-42
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/Bootstrap/ProviderRegistry.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\Exception\\EntityTypeRegistrationCollisionException",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-43
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/BuiltinRouteRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-44
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/BuiltinRouteRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Routing\\RouteBuilder",
    "target_package": "routing",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-45
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/BuiltinRouteRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Routing\\WaaseyaaRouter",
    "target_package": "routing",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-46
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\EntityAccessHandler",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-47
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\Audit\\EntityAuditLogger",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-48
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\Audit\\EntityWriteAuditListener",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-49
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\ContentEntityBase",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-50
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-51
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeLifecycleManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-52
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeManager",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-53
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\Repository\\EntityRepositoryInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-54
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\EntityStorage\\Connection\\SingleConnectionResolver",
    "target_package": "entity-storage",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-55
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\EntityStorage\\Driver\\RevisionableStorageDriver",
    "target_package": "entity-storage",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-56
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\EntityStorage\\Driver\\SqlStorageDriver",
    "target_package": "entity-storage",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-57
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\EntityStorage\\EntityRepository",
    "target_package": "entity-storage",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-58
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\EntityStorage\\SqlEntityStorage",
    "target_package": "entity-storage",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-59
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/AbstractKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\EntityStorage\\SqlSchemaHandler",
    "target_package": "entity-storage",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-60
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\AccessChecker",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-61
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\AccountInterface",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-62
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\ErrorPageRendererInterface",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-63
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\Gate\\EntityAccessGate",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-64
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\Middleware\\AuthorizationMiddleware",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-65
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\Controller\\BroadcastStorage",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-66
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\Http\\DiscoveryApiHandler",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-67
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Routing\\WaaseyaaRouter",
    "target_package": "routing",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-68
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\User\\DevAdminAccount",
    "target_package": "user",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-69
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\User\\Middleware\\BearerAuthMiddleware",
    "target_package": "user",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-70
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\User\\Middleware\\CsrfMiddleware",
    "target_package": "user",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-71
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/HttpKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\User\\Middleware\\SessionMiddleware",
    "target_package": "user",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-72
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Access\\PermissionHandler",
    "target_package": "access",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-73
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\EmbeddingProviderFactory",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-74
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\SemanticIndexWarmer",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-75
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\SqliteEmbeddingStorage",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-76
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\CLI\\CliCommandRegistry",
    "target_package": "cli",
    "target_layer": 6,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-77
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\CLI\\Command\\DbInitCommand",
    "target_package": "cli",
    "target_layer": 6,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-78
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\CLI\\Command\\Optimize\\OptimizeManifestCommand",
    "target_package": "cli",
    "target_layer": 6,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-79
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\CLI\\Command\\WaaseyaaVersionCommand",
    "target_package": "cli",
    "target_layer": 6,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-80
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\CLI\\WaaseyaaApplication",
    "target_package": "cli",
    "target_layer": 6,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-81
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Config\\ConfigManager",
    "target_package": "config",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-82
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Config\\Storage\\FileStorage",
    "target_package": "config",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-83
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeIdNormalizer",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-84
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/ConsoleKernel.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Routing\\WaaseyaaRouter",
    "target_package": "routing",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-85
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/EventListenerRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\EmbeddingProviderFactory",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-86
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/EventListenerRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\EntityEmbeddingCleanupListener",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-87
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/EventListenerRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\EntityEmbeddingListener",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-88
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/EventListenerRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\AI\\Vector\\SqliteEmbeddingStorage",
    "target_package": "ai-vector",
    "target_layer": 5,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-89
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/EventListenerRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Api\\Controller\\BroadcastStorage",
    "target_package": "api",
    "target_layer": 4,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-90
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/EventListenerRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\Event\\EntityEvent",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-91
- **Priority:** P2 | **Category:** layer_boundary | **Severity:** high
- **Message:** L0 source uses Waaseyaa type from higher layer (static use)
```json
{
    "file": "packages/foundation/src/Kernel/EventListenerRegistrar.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\Event\\EntityEvents",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L0 symbols with no @covers in this layer's test tree (count). Full list: symbol_test_map_layer0.json
```json
{
    "count": 269,
    "sample_fqcn": [
        "Waaseyaa\\Analytics\\UmamiClient",
        "Waaseyaa\\Cache\\CacheFactory",
        "Waaseyaa\\Cache\\TagAwareCacheInterface",
        "Waaseyaa\\Cache\\CacheItem",
        "Waaseyaa\\Cache\\CacheTagsInvalidator",
        "Waaseyaa\\Cache\\Listener\\TranslationCacheInvalidator",
        "Waaseyaa\\Cache\\Listener\\EntityCacheSubscriber",
        "Waaseyaa\\Cache\\Listener\\ConfigCacheInvalidator",
        "Waaseyaa\\Cache\\Listener\\EntityCacheInvalidator",
        "Waaseyaa\\Cache\\Backend\\DatabaseBackend",
        "Waaseyaa\\Cache\\Backend\\MemoryBackend",
        "Waaseyaa\\Cache\\Backend\\NullBackend"
    ]
}
```

## 3. PHPStan (L0 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["analytics","cache","database-legacy","error-handler","foundation","geo","http-client","i18n","ingestion","mail","mercure","oauth-provider","plugin","queue","scheduler","state","typed-data","validation"],"missing_from_phpstan_neon":[]}
- PHPStan totals (this run): 0 errors, 0 files with errors (see `layer0_static_analysis.json`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer0_layer_boundary_report.json`.
Static: Waaseyaa `use` in L0 code targeting layer >0 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **8**, *Listener* classes **0**, *Attribute* classes (heuristic) **12**. See `layer0_metadata_consistency.json` for file paths.

## 6. Test / @covers

Unique FQCNs with at least one `@covers`: 2
Public symbols with no @covers: 269 (see coverage finding and `symbol_test_map_layer0.json`).

## 7. Hygiene

See `layer0_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in this layer’s `src/`.

## 8. Deliverable files

- `build/layer0-audit/layer0-audit.md`
- `build/layer0-audit/layer0-audit.json`
- `build/layer0-audit/public_api_layer0.json`
- `build/layer0-audit/symbol_test_map_layer0.json`
- `build/layer0-audit/layer0_layer_boundary_report.json`
- `build/layer0-audit/layer0_metadata_consistency.json`
- `build/layer0-audit/layer0_hygiene_report.txt`
- `build/layer0-audit/layer0_static_analysis.json`
