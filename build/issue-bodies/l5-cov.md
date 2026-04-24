## Summary

**`L5-COV-AGG`:** **70** public (non-`@internal`) L5 symbols lack PHPDoc `@covers \FQCN` in the layer’s test tree. Audit counts docblock `@covers` only, not PHPUnit `#[CoversClass]`.

## Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 5
```

## Artifacts

- `build/layer5-audit/layer5-audit.md`
- `build/layer5-audit/symbol_test_map_layer5.json`

## Sample FQCNs

`Waaseyaa\AI\Agent\AgentAuditLog`, `Waaseyaa\AI\Agent\ToolRegistryInterface`, … (see JSON).

## Acceptance criteria

- `L5-COV-AGG` count trends to **0**, or symbols marked `@internal` / documented exemptions.
