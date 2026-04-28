# Research — inferrer-entity-reference-compat

## Problem

`FieldTypeInferrer::isCompatible()` (`packages/entity/src/Attribute/FieldTypeInferrer.php:182`) rejects PHP `?int` / `?string` properties when explicitly overridden to `#[Field(type: 'entity_reference', ...)]`. The current `COMPATIBILITY_GROUPS` table (line 175) only covers symmetric overrides between text-like, numeric, and date-like ids — none for entity_reference.

Two M1 sites work around this with untyped properties + `@var` PHPDoc:

- `packages/node/src/Node.php:52-54` — `Node.uid`
- `packages/taxonomy/src/Term.php:29-31` — `Term.parent_id`

Documented as transitional gap #3 in `docs/specs/entity-system.md:587-599`.

## Decisions

### D1 — Add an asymmetric override rule, not a new symmetric group

`compatibilityGroups()` is a public seam (`FieldTypeInferrer.php:203`) consumed by static analysis with the meaning "either side may override the other." `entity_reference` cannot be inferred from a scalar PHP type, so widening that seam would be a false advertisement.

Implementation: add `private const ENTITY_REFERENCE_COMPATIBLE_INFERRED = ['integer', 'string'];` and a one-direction check in `isCompatible()`:

```php
if ($explicit === 'entity_reference'
    && \in_array($inferred, self::ENTITY_REFERENCE_COMPATIBLE_INFERRED, true)) {
    return true;
}
```

`compatibilityGroups()` stays untouched.

### D2 — Preserve `target_entity_type_id` settings key

The original mission brief used `'target_type'`, but the actual codebase (Node.php:53, Term.php:30, `EntityTypeBuilder`, and the CLAUDE.md gotcha) uses `target_entity_type_id`. Renaming the key would silently break reference resolution. The refactors will preserve the existing key.

### D3 — Reject `bool` and `float` for entity_reference

The asymmetric rule whitelists only `integer` and `string`. `bool`, `float`, `array`, `datetime`, etc. continue to raise the existing `conflictException()` diagnostic, unchanged.

## Evidence

- `packages/entity/src/Attribute/FieldTypeInferrer.php:175-205` — current compatibility-group code.
- `packages/node/src/Node.php:52-54` — workaround site #1.
- `packages/taxonomy/src/Term.php:29-31` — workaround site #2.
- `docs/specs/entity-system.md:587-599` — documented transitional gap #3 with explicit promise: "A future `inferrer-entity-reference-compat` mission will extend `FieldTypeInferrer` …".

## Open Questions

None. Surface and call sites are well-defined; this is a mechanical close-the-gap mission.

## Risks

- **Downstream consumers reading `Node.uid` / `Term.parent_id`** may have type expectations encoded in PHPDoc that PHP's stronger `?int` typing now enforces at runtime. Full PHPUnit suite + PHPStan run will catch regressions.
- None higher than that. Public field-type API and serialization are unaffected.
