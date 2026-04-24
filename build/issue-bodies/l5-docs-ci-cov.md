## Part A — Documentation, PHPStan.neon, and static analysis (ai-observability)

### Summary

- **`L5-DOC-CLAUDE`:** `ai-observability` is in L5 per `bin/check-package-layers` but missing from the Layer 5 table in `CLAUDE.md`.
- **`L5-PHPSTAN-GAP-ai-observability`:** Package not listed in `phpstan.neon` includes.
- **PHPStan (scoped run):** 1 file_error in `packages/ai-observability/src/Analysis/AnomalyDetector.php` line 136 — `cast.useless` (casting to float something already float).

### Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 5
```

### Artifacts

- `build/layer5-audit/layer5-audit.md`
- `build/layer5-audit/layer5_static_analysis.json`

### Acceptance criteria (Part A)

- [ ] `CLAUDE.md` lists **`ai-observability`** in Layer 5 (AI).
- [ ] `phpstan.neon` includes `packages/ai-observability/src`.
- [ ] Fix `cast.useless` in `AnomalyDetector.php` (or adjust types) so PHPStan is clean for that file.

---

## Part B — PHPDoc `@covers` backlog (`L5-COV-AGG`)

### Summary

**70** public (non-`@internal`) L5 symbols lack PHPDoc `@covers` in the layer’s test tree. Audit counts docblock `@covers` only, not `#[CoversClass]`.

### Artifact

- `build/layer5-audit/symbol_test_map_layer5.json`

### Acceptance criteria (Part B)

- `L5-COV-AGG` count trends to **0**, or symbols marked `@internal` / explicitly exempted with rationale.
