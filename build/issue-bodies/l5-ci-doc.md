## Summary

- **`L5-DOC-CLAUDE`:** `ai-observability` is in L5 per `bin/check-package-layers` but missing from the Layer 5 table in `CLAUDE.md`.
- **`L5-PHPSTAN-GAP-ai-observability`:** Package not listed in `phpstan.neon` includes.
- **PHPStan (scoped audit run):** 1 file_error in `packages/ai-observability/src/Analysis/AnomalyDetector.php` line 136 — `cast.useless`.

## Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 5
```

## Artifacts

- `build/layer5-audit/layer5-audit.md`
- `build/layer5-audit/layer5_static_analysis.json`

## Acceptance criteria

- [ ] `CLAUDE.md` lists **`ai-observability`** in Layer 5 (AI).
- [ ] `phpstan.neon` includes `packages/ai-observability/src`.
- [ ] Fix `cast.useless` in `AnomalyDetector.php` (or adjust types) so PHPStan is clean for that file.
