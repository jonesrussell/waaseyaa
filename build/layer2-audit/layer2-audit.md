# Layer 2 Forensic Audit (Content Types)

Generated: 2026-04-30T17:02:24+00:00 UTC

## 1. Canonical scope

Layer 2 (Content Types) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: engagement, groups, media, menu, messaging, node, note, path, relationship, taxonomy.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

### L2-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L2 symbols with no @covers / #[CoversClass] in this layer's test tree (count). Full list: symbol_test_map_layer2.json
```json
{
    "count": 8,
    "sample_fqcn": [
        "Waaseyaa\\Media\\FileRepositoryInterface",
        "Waaseyaa\\Messaging\\MessagingServiceProvider",
        "Waaseyaa\\Note\\NoteServiceProvider",
        "Waaseyaa\\Path\\ResolvedPath",
        "Waaseyaa\\Path\\PathAliasManagerInterface",
        "Waaseyaa\\Relationship\\RelationshipParameterValidator",
        "Waaseyaa\\Relationship\\VisibilityFilterInterface",
        "Waaseyaa\\Relationship\\RelationshipServiceProvider"
    ]
}
```

## 3. PHPStan (L2 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["engagement","groups","media","menu","messaging","node","note","path","relationship","taxonomy"],"missing_from_phpstan_neon":[]}
- PHPStan totals (this run): 0 errors, 0 files with errors (see `layer2_static_analysis.json`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer2_layer_boundary_report.json`.
Static: Waaseyaa `use` in L2 code targeting layer >2 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **10**, *Listener* classes **2**, *Attribute* classes (heuristic) **0**. See `layer2_metadata_consistency.json` for file paths.

## 6. Test coverage (@covers + #[CoversClass])

Unique FQCNs with at least one `@covers` or `#[CoversClass]`: 54
Public symbols with no indexed coverage: 8 (see coverage finding and `symbol_test_map_layer2.json`).

## 7. Hygiene

See `layer2_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in this layer’s `src/`.

## 8. Deliverable files

- `build/layer2-audit/layer2-audit.md`
- `build/layer2-audit/layer2-audit.json`
- `build/layer2-audit/public_api_layer2.json`
- `build/layer2-audit/symbol_test_map_layer2.json`
- `build/layer2-audit/layer2_layer_boundary_report.json`
- `build/layer2-audit/layer2_metadata_consistency.json`
- `build/layer2-audit/layer2_hygiene_report.txt`
- `build/layer2-audit/layer2_static_analysis.json`
