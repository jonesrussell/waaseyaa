# Data Model: composer-internal-version-sweep-01KR96NA

This mission introduces no domain entities. It modifies build-time
metadata (composer manifests) and adds release-time tooling. A formal
data-model section is included for completeness — there are no
attributes, relationships, lifecycles, or invariants to record beyond
what `spec.md` and `plan.md` already capture.

## Inputs / outputs of the new tooling

| Component | Input | Output | Side effect |
|---|---|---|---|
| `bin/sync-internal-versions <ver>` | version string (with or without `v` prefix); reads `packages/*/composer.json` | none (returns 0 / non-zero) | rewrites every internal `waaseyaa/*` constraint to `^<ver>` in-place; idempotent |
| `bin/lib/internal-version-sync.php::resolveCurrentVersion()` | none (reads git) | version string (no `v`) | none |
| `bin/lib/internal-version-sync.php::expectedConstraint($v)` | version string | `"^$v"` | none |
| `bin/lib/internal-version-sync.php::findInternalDeps($manifest)` | parsed `composer.json` array | array of `waaseyaa/*` keys present in `require` + `require-dev` | none |
| `bin/check-composer-policy` (CP-NEW addition) | reads all `packages/*/composer.json` + git tags | exit 0 / non-zero with diagnostics | none |

## Constraint shape

Every internal `waaseyaa/*` dependency in any `packages/*/composer.json`,
in either `require` or `require-dev`, must satisfy:

```
constraint == "^" + resolveCurrentVersion()
```

`resolveCurrentVersion()` is `git describe --tags --abbrev=0 --match='v*.*.*'`
with the leading `v` stripped. CP-NEW enforces the equality; the sync
script produces it.

## What this mission does NOT model

- Composer's resolver semantics (caret, prerelease compatibility, etc.).
  Those are upstream concerns; the mission only ensures the constraint
  *string* matches the canonical reference.
- Multi-track release support — a single linear version channel is
  assumed (consistent with current repo state).
- Downstream consumer behavior beyond what Packagist and Composer already
  guarantee.
