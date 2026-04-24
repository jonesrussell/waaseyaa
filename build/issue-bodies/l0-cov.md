## Summary

Forensic layer audit **Layer 0 (Foundation)** reports **269** public (non-`@internal`) symbols with no PHPDoc `@covers \FQCN` in this layer’s test tree (`L0-COV-AGG`). The audit counts **PHPDoc** `@covers` only, not PHPUnit `#[CoversClass]`.

## Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 0
```

## Artifacts

- `build/layer0-audit/layer0-audit.md`
- `build/layer0-audit/symbol_test_map_layer0.json` (full list: `public_non_internal_symbols_lacking_at_covers`)

## Sample FQCNs (from audit)

`Waaseyaa\Analytics\UmamiClient`, `Waaseyaa\Cache\CacheFactory`, `Waaseyaa\Foundation\…` (see JSON for full set).

## Acceptance criteria

- `L0-COV-AGG` count in `layer0-audit.md` trends toward **0**, or remaining symbols are explicitly marked `@internal` / documented as intentionally uncovered with rationale.
- Optional: add `tools/audit/segment_l0_covers_by_package.php` (mirror L1/L3 segment scripts) if package-level breakdown helps execution.

## Labels

`tech-debt`, `testing`
