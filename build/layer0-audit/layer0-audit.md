# Layer 0 Forensic Audit (Foundation)

Generated: 2026-04-24T21:45:59+00:00 UTC

## 1. Canonical scope

Layer 0 (Foundation) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: analytics, cache, database-legacy, error-handler, foundation, geo, http-client, i18n, ingestion, mail, mercure, oauth-provider, plugin, queue, scheduler, state, typed-data, validation.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

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
    "file": "packages/foundation/src/Diagnostic/BootDiagnosticReport.php",
    "from_package": "foundation",
    "import": "Waaseyaa\\Entity\\EntityTypeInterface",
    "target_package": "entity",
    "target_layer": 1,
    "rule": "L0 must not use types from layer >0 via use statement"
}
```

### L0-USE-6
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

### L0-USE-7
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

### L0-USE-8
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

### L0-USE-9
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
