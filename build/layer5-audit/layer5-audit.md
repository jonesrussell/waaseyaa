# Layer 5 Forensic Audit (AI)

Generated: 2026-04-30T17:02:31+00:00 UTC

## 1. Canonical scope

Layer 5 (AI) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: ai-agent, ai-observability, ai-pipeline, ai-schema, ai-vector.

**Drift vs CLAUDE.md:** none (package lists match the Layer Architecture table for this row).

## 2. Priority-ordered findings

### L5-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L5 symbols with no @covers / #[CoversClass] in this layer's test tree (count). Full list: symbol_test_map_layer5.json
```json
{
    "count": 32,
    "sample_fqcn": [
        "Waaseyaa\\AI\\Agent\\ToolRegistryInterface",
        "Waaseyaa\\AI\\Agent\\Event\\ToolCallStarted",
        "Waaseyaa\\AI\\Agent\\Event\\LlmCallCompleted",
        "Waaseyaa\\AI\\Agent\\Event\\ToolCallCompleted",
        "Waaseyaa\\AI\\Agent\\AgentInterface",
        "Waaseyaa\\AI\\Agent\\Provider\\RateLimitException",
        "Waaseyaa\\AI\\Agent\\Provider\\ProviderInterface",
        "Waaseyaa\\AI\\Agent\\Provider\\ToolResultBlock",
        "Waaseyaa\\AI\\Agent\\Provider\\StreamingProviderInterface",
        "Waaseyaa\\AI\\Agent\\Provider\\ToolUseBlock",
        "Waaseyaa\\AI\\Agent\\Provider\\MessageResponse",
        "Waaseyaa\\AI\\Agent\\Provider\\MaxIterationsException"
    ]
}
```

## 3. PHPStan (L5 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["ai-agent","ai-observability","ai-pipeline","ai-schema","ai-vector"],"missing_from_phpstan_neon":[]}
- PHPStan totals (this run): 0 errors, 0 files with errors (see `layer5_static_analysis.json`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer5_layer_boundary_report.json`.
Static: Waaseyaa `use` in L5 code targeting layer >5 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **2**, *Listener* classes **4**, *Attribute* classes (heuristic) **0**. See `layer5_metadata_consistency.json` for file paths.

## 6. Test coverage (@covers + #[CoversClass])

Unique FQCNs with at least one `@covers` or `#[CoversClass]`: 48
Public symbols with no indexed coverage: 32 (see coverage finding and `symbol_test_map_layer5.json`).

## 7. Hygiene

See `layer5_hygiene_report.txt` for TODO/FIXME/HACK and risk patterns in this layer’s `src/`.

## 8. Deliverable files

- `build/layer5-audit/layer5-audit.md`
- `build/layer5-audit/layer5-audit.json`
- `build/layer5-audit/public_api_layer5.json`
- `build/layer5-audit/symbol_test_map_layer5.json`
- `build/layer5-audit/layer5_layer_boundary_report.json`
- `build/layer5-audit/layer5_metadata_consistency.json`
- `build/layer5-audit/layer5_hygiene_report.txt`
- `build/layer5-audit/layer5_static_analysis.json`
