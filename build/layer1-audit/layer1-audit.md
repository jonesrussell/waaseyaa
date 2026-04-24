# Layer 1 Forensic Audit (Core Data)

Generated: 2026-04-24T21:13:57+00:00 UTC

## 1. Canonical scope

Layer 1 (Core Data) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: access, auth, config, entity, entity-storage, field, oidc, testing, user.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

### L1-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L1 symbols with no @covers in this layer's test tree (count). Full list: symbol_test_map_layer1.json
```json
{
    "count": 177,
    "sample_fqcn": [
        "Waaseyaa\\Access\\ConfigEntityAccessPolicy",
        "Waaseyaa\\Access\\AccountInterface",
        "Waaseyaa\\Access\\RedirectValidator",
        "Waaseyaa\\Access\\PermissionHandlerInterface",
        "Waaseyaa\\Access\\AccessPolicyInterface",
        "Waaseyaa\\Access\\Gate\\PolicyAttribute",
        "Waaseyaa\\Access\\Gate\\GateInterface",
        "Waaseyaa\\Access\\Gate\\AccessDeniedException",
        "Waaseyaa\\Access\\Gate\\Gate",
        "Waaseyaa\\Access\\Gate\\EntityAccessGate",
        "Waaseyaa\\Access\\Middleware\\AuthorizationMiddleware",
        "Waaseyaa\\Access\\FieldAccessPolicyInterface"
    ]
}
```

## 3. PHPStan (L1 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["access","auth","config","entity","entity-storage","field","oidc","testing","user"],"missing_from_phpstan_neon":[]}
- PHPStan totals (this run): 0 errors, 0 files with errors (see `layer1_static_analysis.json`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer1_layer_boundary_report.json`.
Static: Waaseyaa `use` in L1 code targeting layer >1 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **3**, *Listener* classes **1**, *Attribute* classes (heuristic) **4**. See `layer1_metadata_consistency.json` for file paths.

## 6. Test / @covers

Unique FQCNs with at least one `@covers`: 23
Public symbols with no @covers: 177 (see coverage finding and `symbol_test_map_layer1.json`).

## 7. Hygiene

See `layer1_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in this layer’s `src/`.

## 8. Deliverable files

- `build/layer1-audit/layer1-audit.md`
- `build/layer1-audit/layer1-audit.json`
- `build/layer1-audit/public_api_layer1.json`
- `build/layer1-audit/symbol_test_map_layer1.json`
- `build/layer1-audit/layer1_layer_boundary_report.json`
- `build/layer1-audit/layer1_metadata_consistency.json`
- `build/layer1-audit/layer1_hygiene_report.txt`
- `build/layer1-audit/layer1_static_analysis.json`
