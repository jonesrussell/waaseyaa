## Summary

Layer 6 (Interfaces) audit: **`L6-COV-AGG`** — **226** public (non-`@internal`) symbols with no PHPDoc `@covers \FQCN` in this layer’s PHP test tree. Audit counts **PHPDoc** `@covers` only, not PHPUnit `#[CoversClass]`.

Note: `admin` is largely Nuxt; PHP `src/` under `packages/admin` may still need `@covers` where tests exist.

## Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 6
```

## Artifacts

- `build/layer6-audit/layer6-audit.md`
- `build/layer6-audit/symbol_test_map_layer6.json`

## Sample FQCNs (from audit)

`Waaseyaa\AdminSurface\Query\SurfaceFilterOperator`, `Waaseyaa\AdminSurface\Host\AbstractAdminSurfaceHost`, … (see JSON).

## Acceptance criteria

- `L6-COV-AGG` count trends toward **0**, or remaining symbols are `@internal` / documented as out-of-scope for PHP test `@covers`.

## Labels

`tech-debt`, `testing`
