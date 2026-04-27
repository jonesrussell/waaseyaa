# FR-010 Baseline — Attribute-First Entity Static Analysis

**Date**: 2026-04-27
**Lane**: `kitty/mission-attribute-first-entity-static-analysis-01KQ6XW7-lane-a`.

## Method

Both runs invoke the same command on the same lane checkout, swapping only
whether `packages/entity/phpstan-rules.neon` is included from the repo-root
`phpstan.neon`:

```
vendor/bin/phpstan analyse --no-progress --memory-limit=2G
```

Analysis covers every package in repo-root `phpstan.neon` `paths:`,
including all entity-using packages enumerated in the WP brief
(genealogy, node, note, taxonomy, user, oidc, engagement, groups,
messaging, path) plus the rest of the monorepo.

## Results

| Run                                             | Result            | New errors |
|-------------------------------------------------|-------------------|------------|
| Without `FieldAttributeRule` (include commented out) | `[OK] No errors` | —          |
| With `FieldAttributeRule` (include active)            | `[OK] No errors` | 0          |

## Verdict

FR-010 satisfied. Adding the new rule introduces zero new errors on
existing entity-using packages or anywhere else in the monorepo at the
merge point. No suppressions were added to `phpstan-baseline.neon` for
this mission.
