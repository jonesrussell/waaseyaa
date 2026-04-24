## Summary

Layer 6 forensic audit lists **eight** Interface-layer packages present in `bin/check-package-layers` but **missing** from `phpstan.neon` path includes:

`admin`, `admin-surface`, `debug`, `deployer`, `genealogy`, `graphql`, `inertia` (in addition to packages already included: `cli`, `mcp`, `ssr`, `telescope`).

Finding IDs in `layer6-audit.md`: `L6-PHPSTAN-GAP-admin`, `…-admin-surface`, `…-debug`, `…-deployer`, `…-genealogy`, `…-graphql`, `…-inertia`.

## Regenerate

```bash
php tools/audit/GenerateLayerAudit.php 6
```

## Artifacts

- `build/layer6-audit/layer6-audit.md` (PHPStan / CI alignment section)
- `build/layer6-audit/layer6_static_analysis.json`

## Acceptance criteria

- [ ] Each listed L6 package’s `packages/<short>/src` is covered by `phpstan.neon` (or a documented, intentional exclusion list with reason — e.g. non-PHP SPA — and CI policy).
- [ ] After inclusion, `composer phpstan` passes for those paths (fix new findings or stage with explicit follow-up issues per package if scope explodes).

## Labels

`tech-debt`, `dx`
