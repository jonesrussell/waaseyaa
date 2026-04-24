## Summary

Layer 4 forensic audit (regenerated) surfaced **three related gaps** for the **API** layer:

1. **CLAUDE.md drift (`L4-DOC-CLAUDE`):** `bin/check-package-layers` includes **`bimaaji`** in L4, but the Layer Architecture table in `CLAUDE.md` does not list it (`in_script_not_in_claude: ["bimaaji"]`).
2. **PHPStan CI alignment (`L4-PHPSTAN-GAP-bimaaji`):** `bimaaji` is **not** in `phpstan.neon` path includes, so default `composer phpstan` may skip it (audit still analyzed `packages/bimaaji/src` for this run).
3. **Layer boundary (`L4-USE-1`):** `packages/api/src/Controller/CodifiedContextController.php` has a static `use` of `Waaseyaa\Telescope\…` (L6 from L4).

## Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 4
```

## Artifacts

- `build/layer4-audit/layer4-audit.md`
- `build/layer4-audit/layer4_static_analysis.json` (PHPStan scoped to L4 `src/` trees; **3 file_errors** in `bimaaji` — see `phpstan_json_parsed`)
- `build/layer4-audit/symbol_test_map_layer4.json`

## PHPStan findings (bimaaji, this run)

- `JsonApiIntrospectionProvider.php`: `property.onlyWritten`, `empty.notAllowed`
- `MutationValidator.php`: `nullsafe.neverNull`

## Acceptance criteria

- [ ] `CLAUDE.md` Layer 4 row lists **`bimaaji`** alongside `api`, `routing`.
- [ ] `phpstan.neon` includes `packages/bimaaji/src` (or equivalent path rule) so CI analyzes the package.
- [ ] PHPStan **file_errors** for `bimaaji` under normal CI are **0** (fix root causes, no baseline suppressions per project norms).
- [ ] **L4→L6 import:** resolve `CodifiedContextController` → Telescope coupling (interface move, indirection, or documented exemption with tooling update) so `bin/check-package-layers` / audit static-use rule is satisfied or explicitly exempted in policy + scanner.

## Related

- Epic / roadmap: **#619** (Bimaaji), **#1243** (interface-layer refactors) may overlap with the boundary item.
