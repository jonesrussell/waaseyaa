# Layer 6 Forensic Audit (Interfaces)

Generated: 2026-04-30T17:02:32+00:00 UTC

## 1. Canonical scope

Layer 6 (Interfaces) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: admin-surface, cli, debug, deployer, genealogy, graphql, inertia, mcp, ssr, telescope.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

### L6-PHPSTAN-GAP-deployer
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'deployer' (L6) not listed in phpstan.neon paths; default CI may not analyze it.

### L6-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L6 symbols with no @covers / #[CoversClass] in this layer's test tree (count). Full list: symbol_test_map_layer6.json
```json
{
    "count": 56,
    "sample_fqcn": [
        "Waaseyaa\\AdminSurface\\Action\\SurfaceActionHandlerInterface",
        "Waaseyaa\\CLI\\CliServiceProvider",
        "Waaseyaa\\CLI\\Provenance\\ProvenanceReport",
        "Waaseyaa\\CLI\\Provenance\\InstalledWaaseyaaPackage",
        "Waaseyaa\\CLI\\Command\\AdminBuildCommand",
        "Waaseyaa\\Cli\\Command\\SearchReindexCommand",
        "Waaseyaa\\CLI\\Command\\AdminDevCommand",
        "Waaseyaa\\CLI\\Command\\WaaseyaaVersionCommand",
        "Waaseyaa\\CLI\\Command\\SyncRulesCommand",
        "Waaseyaa\\CLI\\Ingestion\\SourceConnectorInterface",
        "Waaseyaa\\Debug\\DebugServiceProvider",
        "Waaseyaa\\Debug\\ErrorPreviewController"
    ]
}
```

## 3. PHPStan (L6 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["admin-surface","cli","debug","genealogy","graphql","inertia","mcp","ssr","telescope"],"missing_from_phpstan_neon":["deployer"]}
- See `layer6_static_analysis.json` for raw output (if PHPStan output is not valid JSON, inspect `raw_stdout`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer6_layer_boundary_report.json`.
Static: Waaseyaa `use` in L6 code targeting layer >6 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **10**, *Listener* classes **0**, *Attribute* classes (heuristic) **5**. See `layer6_metadata_consistency.json` for file paths.

## 6. Test coverage (@covers + #[CoversClass])

Unique FQCNs with at least one `@covers` or `#[CoversClass]`: 184
Public symbols with no indexed coverage: 56 (see coverage finding and `symbol_test_map_layer6.json`).

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
