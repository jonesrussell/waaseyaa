# WP04 Review — Cycle 4

**Verdict:** APPROVE
**Reviewer:** Opus 4.7 (1M context)
**Date:** 2026-05-11
**Cycle commit:** `4960418de` — `feat(WP04): retain $errorCode; align contract and spec to PHP 8.5 reality [cycle 3]`

## Scope of this cycle

Cycle 3 left one narrow CR: rename `PartialSaveException::$errorCode` to `$code`, OR update the contract/spec to record `$errorCode` with a one-line note explaining the PHP constraint (an option cycle-3 review explicitly permitted).

Implementer took the contract-update path. This review validates the choice and the consistency of the resulting artifacts.

## PHP constraint verification (the load-bearing fact)

Sanity-checked the claim directly under PHP 8.5.6:

```
php -r "class E extends Exception { public readonly string \$code = 'X'; } new E('m');"
→ PHP Fatal error: Readonly property E::$code cannot have default value
```

A second probe without `readonly` confirms the broader constraint:

```
php -r "class E extends Exception { public string \$code = 'X'; }"
→ PHP Fatal error: Type of E::$code must be omitted to match the parent
   definition (which is non-typed protected int)
```

The constraint is real. `\Exception::$code` is non-readonly `protected int`; PHP refuses any redeclaration with a string type in a subclass, regardless of `readonly` or visibility. A typed `public readonly string $code` on a `\RuntimeException` subclass is genuinely impossible. The contract-update path was the correct one.

## Artifact consistency

Verified the rename + note landed coherently across all three surfaces:

1. **`packages/entity-storage/src/Exception/PartialSaveException.php`** — class docblock has a new "Why `$errorCode`, not `$code`" section quoting the exact PHP error message and pointing to spec §6.5 + the contract. Self-documenting at the read point. ✓
2. **`kitty-specs/entity-storage-v2-01KRCDDC/contracts/partial-save-error.md`** — code block now declares `public readonly string $errorCode = 'PARTIAL_SAVE'` (was `$code`); blockquote PHP constraint note added immediately below the class definition. ✓
3. **`kitty-specs/entity-storage-v2-01KRCDDC/spec.md`** §6.5 — same rename and same blockquote note in the spec body. ✓

All three reads now agree on the name and explain the constraint at the point a reader would hit the surprise.

## Gates

- `composer cs-check` → exit 0 (no files changed).
- `composer phpstan` → `[OK] No errors`.
- `./vendor/bin/phpunit packages/entity-storage/tests/` → `Tests: 397, Assertions: 883` — OK. The 2 PHPUnit warnings + 2 deprecations are pre-existing infrastructure noise (no code-coverage driver, PHPUnit 10 deprecations); unchanged from cycle 3.

## Cycle-1 criteria still hold

Diff is documentation-only (3 files: 1 docblock, 1 contract, 1 spec; +20/-4). No runtime behaviour change. AfterSave non-emission on partial failure, fan-out order, event payload identity — all unchanged from the cycle-1-approved implementation in `581a68421`.

## Conclusion

- PHP-constraint claim: **verified real**.
- Spec + contract + docblock: **consistently updated**, with the constraint explained at every read point.
- Gates: **green**.
- No regressions.

WP04 is approved for the lifecycle-events + PartialSaveException surface. Moving to `approved`.
