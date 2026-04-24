# Layer 6 Forensic Audit (Interfaces)

Generated: 2026-04-24T21:14:07+00:00 UTC

## 1. Canonical scope

Layer 6 (Interfaces) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: admin, admin-surface, cli, debug, deployer, genealogy, graphql, inertia, mcp, ssr, telescope.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

### L6-PHPSTAN-GAP-admin
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'admin' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-PHPSTAN-GAP-admin-surface
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'admin-surface' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-PHPSTAN-GAP-debug
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'debug' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-PHPSTAN-GAP-deployer
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'deployer' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-PHPSTAN-GAP-genealogy
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'genealogy' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-PHPSTAN-GAP-graphql
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'graphql' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-PHPSTAN-GAP-inertia
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'inertia' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L6 symbols with no @covers in this layer's test tree (count). Full list: symbol_test_map_layer6.json
```json
{
    "count": 226,
    "sample_fqcn": [
        "Waaseyaa\\AdminSurface\\Query\\SurfaceFilterOperator",
        "Waaseyaa\\AdminSurface\\Query\\SurfaceQueryParser",
        "Waaseyaa\\AdminSurface\\Query\\SurfaceQuery",
        "Waaseyaa\\AdminSurface\\Action\\SurfaceActionHandlerInterface",
        "Waaseyaa\\AdminSurface\\Host\\AdminSurfaceSessionData",
        "Waaseyaa\\AdminSurface\\Host\\AdminSurfaceUiPayload",
        "Waaseyaa\\AdminSurface\\Host\\AdminSurfaceResultData",
        "Waaseyaa\\AdminSurface\\Host\\GenericAdminSurfaceHost",
        "Waaseyaa\\AdminSurface\\Host\\AbstractAdminSurfaceHost",
        "Waaseyaa\\AdminSurface\\Catalog\\ActionDefinition",
        "Waaseyaa\\AdminSurface\\Catalog\\EntityDefinition",
        "Waaseyaa\\AdminSurface\\Catalog\\CatalogBuilder"
    ]
}
```

## 3. PHPStan (L6 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["cli","mcp","ssr","telescope"],"missing_from_phpstan_neon":["admin","admin-surface","debug","deployer","genealogy","graphql","inertia"]}
- See `layer6_static_analysis.json` for raw output (if PHPStan output is not valid JSON, inspect `raw_stdout`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer6_layer_boundary_report.json`.
Static: Waaseyaa `use` in L6 code targeting layer >6 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **10**, *Listener* classes **0**, *Attribute* classes (heuristic) **2**. See `layer6_metadata_consistency.json` for file paths.

## 6. Test / @covers

Unique FQCNs with at least one `@covers`: 0
Public symbols with no @covers: 226 (see coverage finding and `symbol_test_map_layer6.json`).

## 7. Hygiene

See `layer6_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in this layer’s `src/`.

## 8. Deliverable files

- `build/layer6-audit/layer6-audit.md`
- `build/layer6-audit/layer6-audit.json`
- `build/layer6-audit/public_api_layer6.json`
- `build/layer6-audit/symbol_test_map_layer6.json`
- `build/layer6-audit/layer6_layer_boundary_report.json`
- `build/layer6-audit/layer6_metadata_consistency.json`
- `build/layer6-audit/layer6_hygiene_report.txt`
- `build/layer6-audit/layer6_static_analysis.json`
