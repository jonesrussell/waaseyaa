# Layer 4 Forensic Audit (API)

Generated: 2026-04-24T21:46:01+00:00 UTC

## 1. Canonical scope

Layer 4 (API) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: api, bimaaji, routing.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

### L4-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L4 symbols with no @covers in this layer's test tree (count). Full list: symbol_test_map_layer4.json
```json
{
    "count": 63,
    "sample_fqcn": [
        "Waaseyaa\\Api\\Http\\DiscoveryApiHandler",
        "Waaseyaa\\Api\\Http\\Router\\DiscoveryRouter",
        "Waaseyaa\\Api\\ResourceSerializer",
        "Waaseyaa\\Api\\Query\\ParsedQuery",
        "Waaseyaa\\Api\\Query\\QueryFilter",
        "Waaseyaa\\Api\\Query\\QueryParser",
        "Waaseyaa\\Api\\Query\\QueryApplier",
        "Waaseyaa\\Api\\Query\\QuerySort",
        "Waaseyaa\\Api\\Query\\PaginationLinks",
        "Waaseyaa\\Api\\Schema\\SchemaPresenter",
        "Waaseyaa\\Api\\Controller\\BroadcastStorage",
        "Waaseyaa\\Api\\Controller\\TranslationController"
    ]
}
```

## 3. PHPStan (L4 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["api","bimaaji","routing"],"missing_from_phpstan_neon":[]}
- PHPStan totals (this run): 0 errors, 0 files with errors (see `layer4_static_analysis.json`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer4_layer_boundary_report.json`.
Static: Waaseyaa `use` in L4 code targeting layer >4 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **2**, *Listener* classes **0**, *Attribute* classes (heuristic) **1**. See `layer4_metadata_consistency.json` for file paths.

## 6. Test / @covers

Unique FQCNs with at least one `@covers`: 3
Public symbols with no @covers: 63 (see coverage finding and `symbol_test_map_layer4.json`).

## 7. Hygiene

See `layer4_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in this layer’s `src/`.

## 8. Deliverable files

- `build/layer4-audit/layer4-audit.md`
- `build/layer4-audit/layer4-audit.json`
- `build/layer4-audit/public_api_layer4.json`
- `build/layer4-audit/symbol_test_map_layer4.json`
- `build/layer4-audit/layer4_layer_boundary_report.json`
- `build/layer4-audit/layer4_metadata_consistency.json`
- `build/layer4-audit/layer4_hygiene_report.txt`
- `build/layer4-audit/layer4_static_analysis.json`
