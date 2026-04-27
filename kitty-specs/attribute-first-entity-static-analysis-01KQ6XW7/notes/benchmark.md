# NFR-001 Benchmark — Attribute-First Entity Static Analysis

**Date**: 2026-04-27
**Host**: Windows 11, PHP 8.5.5, PHPStan 2.1.30 (PSR-4 vendor install).
**Lane**: `kitty/mission-attribute-first-entity-static-analysis-01KQ6XW7-lane-a`.

## Method

Three consecutive runs of:

```
vendor/bin/phpstan analyse --no-progress --memory-limit=2G packages/entity/src
```

were timed with PowerShell's `[System.Diagnostics.Stopwatch]` wrapping the
process invocation. Two configurations were compared, swapping only the
`includes:` line for `packages/entity/phpstan-rules.neon` in the repo-root
`phpstan.neon` (rule active vs commented out). All other state — vendor
tree, tmpDir contents, machine load — held constant.

## Results (wall-clock seconds)

| Configuration | Run 1 (cold) | Run 2 | Run 3 | Median (warm) |
|---------------|--------------|-------|-------|----------------|
| Without rule  | 4.596        | 1.123 | 1.093 | **1.123**      |
| With rule     | 4.525        | 1.102 | 1.108 | **1.108**      |

Run 1 is dominated by PHPStan's result-cache build; the warm-run median is
the right comparison.

## Verdict

`Median(after) / Median(before) = 1.108 / 1.123 ≈ 0.987` — within noise of
parity, well under the 1.10× ceiling NFR-001 requires. NFR-001 satisfied.

The result is unsurprising: the rule visits a single AST node type
(`Node\Stmt\Property`), only acts when a `#[Field]` attribute is present
(an early skip for the vast majority of properties), and reuses the
already-loaded `FieldTypeInferrer` constants/helpers — no additional
reflection passes or container construction.
