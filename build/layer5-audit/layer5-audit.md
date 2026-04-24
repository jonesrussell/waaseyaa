# Layer 5 Forensic Audit (AI)

Generated: 2026-04-24T21:14:07+00:00 UTC

## 1. Canonical scope

Layer 5 (AI) packages from `bin/check-package-layers` **LAYER_BY_SHORT**: ai-agent, ai-observability, ai-pipeline, ai-schema, ai-vector.

**Drift vs CLAUDE.md:** `in_script_not_in_claude` = ["ai-observability"], `in_claude_not_in_script` = [].

## 2. Priority-ordered findings

### L5-DOC-CLAUDE
- **Priority:** P1 | **Category:** public_api_documentation | **Severity:** low
- **Message:** CLAUDE.md Layer 5 table and bin/check-package-layers disagree on package set.
```json
{
    "in_script_not_in_claude": [
        "ai-observability"
    ],
    "in_claude_not_in_script": []
}
```

### L5-PHPSTAN-GAP-ai-observability
- **Priority:** P2 | **Category:** ci_alignment | **Severity:** medium
- **Message:** Package 'ai-observability' (L5) not listed in phpstan.neon paths; default CI may not analyze it.

### L5-COV-AGG
- **Priority:** P4 | **Category:** test_coverage | **Severity:** low
- **Message:** Public (non-@internal) L5 symbols with no @covers in this layer's test tree (count). Full list: symbol_test_map_layer5.json
```json
{
    "count": 70,
    "sample_fqcn": [
        "Waaseyaa\\AI\\Agent\\AgentAuditLog",
        "Waaseyaa\\AI\\Agent\\ToolRegistryInterface",
        "Waaseyaa\\AI\\Agent\\Event\\ToolCallStarted",
        "Waaseyaa\\AI\\Agent\\Event\\LlmCallCompleted",
        "Waaseyaa\\AI\\Agent\\Event\\ToolCallCompleted",
        "Waaseyaa\\AI\\Agent\\AgentAction",
        "Waaseyaa\\AI\\Agent\\McpServer",
        "Waaseyaa\\AI\\Agent\\AgentResult",
        "Waaseyaa\\AI\\Agent\\AgentExecutor",
        "Waaseyaa\\AI\\Agent\\ToolRegistry",
        "Waaseyaa\\AI\\Agent\\AgentInterface",
        "Waaseyaa\\AI\\Agent\\Provider\\RateLimitException"
    ]
}
```

## 3. PHPStan (L5 paths) and CI alignment

- `phpstan.neon` inclusions for this layer’s packages: {"included":["ai-agent","ai-pipeline","ai-schema","ai-vector"],"missing_from_phpstan_neon":["ai-observability"]}
- PHPStan totals (this run): 0 errors, 1 files with errors (see `layer5_static_analysis.json`).

## 4. Layer boundary (manifest + static `use`)

Composer: `bin/check-package-layers` and `bin/audit-require-dev-layers` output is captured in `layer5_layer_boundary_report.json`.
Static: Waaseyaa `use` in L5 code targeting layer >5 — see P2-style findings. Group-`use` and other references are out of band for this pass.

## 5. Metadata and extension points

Counts: service providers **2**, *Listener* classes **4**, *Attribute* classes (heuristic) **0**. See `layer5_metadata_consistency.json` for file paths.

## 6. Test / @covers

Unique FQCNs with at least one `@covers`: 8
Public symbols with no @covers: 70 (see coverage finding and `symbol_test_map_layer5.json`).

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
