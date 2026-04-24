## Summary

**`L4-COV-AGG`:** **64** public (non-`@internal`) L4 symbols lack PHPDoc `@covers \FQCN` in the layer’s test tree. Unique FQCNs with at least one `@covers` in audit output: **0** (see `layer4-audit.md` §6 — audit counts PHPDoc `@covers` only, not `#[CoversClass]`).

## Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 4
```

## Artifacts

- `build/layer4-audit/layer4-audit.md`
- `build/layer4-audit/symbol_test_map_layer4.json`

## Sample FQCNs

`Waaseyaa\Api\Http\DiscoveryApiHandler`, `Waaseyaa\Api\ResourceSerializer`, `Waaseyaa\Api\Schema\SchemaPresenter`, …

## Acceptance criteria

- `L4-COV-AGG` count trends to **0**, or symbols marked `@internal` / documented exemptions.

## Related

- **#1339** — same layer; docs / phpstan.neon / boundary workstream.
