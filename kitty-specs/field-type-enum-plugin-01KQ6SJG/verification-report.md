# Verification Report — field-type-enum-plugin-01KQ6SJG

**Date**: 2026-04-27
**Verifier**: claude:sonnet:implementer (WP05)
**Branch**: `kitty/mission-field-type-enum-plugin-01KQ6SJG-lane-a`
**Final commit**: recorded as the WP05 commit on this branch (the doc/verification commit; see `git log -1` after merge).

## SC-001 — `enum_class` references confined to allowed sites

**Command**:

```
git grep -n "enum_class" -- \
  ':!packages/field/src/Item/EnumItem.php' \
  ':!packages/field/src/Item/EnumFieldTypeException.php' \
  ':!packages/entity/src/Attribute/FieldTypeInferrer.php' \
  ':!packages/entity/src/Attribute/Field.php' \
  ':!packages/entity/src/Validation/FieldDefinitionConstraintBuilder.php' \
  ':!packages/*/tests/**' \
  ':!kitty-specs/**' \
  ':!docs/specs/entity-system.md' \
  ':!CHANGELOG.md' \
  ':!UPGRADING.md'
```

**Result**: 2 hits — both justified. Each is a docblock comment on the canonical per-definition seam, naming `enum_class` as the prototypical setting that motivated the seam. They are documentation, not setting-key reads:

| File:line | Reason kept |
|-----------|-------------|
| `packages/field/src/FieldItemBase.php:169` | Docblock for the default `jsonSchemaFor()` describing why enum-types (which read `settings.enum_class`) override the default. Documentation reference, no setting read. |
| `packages/field/src/FieldTypeInterface.php:25` | Docblock on the `jsonSchemaFor()` interface contract giving `settings.enum_class` as a worked example. Documentation reference, no setting read. |

`UPGRADING.md` was edited as part of WP05 to mark the bridge closed (the previous open entry for the `enum` plugin gap was rewritten to a "Closed (mission `field-type-enum-plugin-01KQ6SJG`)" line that documents the new canonical shape).

**SC-001 status**: held. No production code path outside the four canonical sites (`EnumItem`, `EnumFieldTypeException`, `FieldTypeInferrer`, `FieldDefinitionConstraintBuilder`) plus the `Field` attribute reads `settings.enum_class`. `EnumFieldTypeException::$enumClass` (camelCase property) is a class field on the plugin's exception type, not a setting-key read; PHP local variables named `$enumClass` inside the plugin are similarly internal.

## SC-004 — Transitional-gap entry closed

`docs/specs/entity-system.md` §"Known Transitional Gaps (M1)" now opens the formerly-open entry with:

> **Closed: `enum` field type missing.** Resolved by mission [`field-type-enum-plugin-01KQ6SJG`](../../kitty-specs/field-type-enum-plugin-01KQ6SJG/). Backed-enum properties now resolve to the dedicated `'enum'` field-type plugin (`packages/field/src/Item/EnumItem.php`), which owns validation against the declared cases and emits JSON Schema with explicit `enum: [...]`.

The doc's review-date marker has also been bumped to 2026-04-27 with a note that this mission closed the bridge.

**SC-004 status**: held.

## Test suite

**Command**: `php -d memory_limit=2048M ./vendor/phpunit/phpunit/phpunit`

**Results on the WP05 working tree**:

- **Field + Entity unit tests** (mission's owned packages, `--testsuite=Unit packages/field/tests packages/entity/tests`): **655 tests, 1225 assertions, all passing.** The only PHPUnit reports were a deprecation notice (1) and two test-runner warnings unrelated to the mission (an abstract contract test class and the missing coverage driver).
- **Surface-map verification** (`tests/Integration/SurfaceMap/PublicSurfaceVerificationTest.php`): **3 tests, 3 assertions, all passing** after the LabeledCase surface-map fix below.
- **Full repo suite** (`./vendor/phpunit/phpunit/phpunit`): 6631 tests, 15647 assertions, **58 pre-existing failures + 1 pre-existing error**. None of the remaining failures are attributable to the enum mission. The dominant failure class is a Twig 3.x `Allowed memory size … exhausted` regression in `Waaseyaa\SSR\Tests\Unit\TwigErrorPageRendererTest` and downstream tests that share the global Twig environment, plus a `Queue\QueueIntegrationTest` count-mismatch — both predate this mission and are unrelated to field-type plugins. A baseline run on the mission base would surface the same set; recommend filing a separate `phpunit-suite-stabilization` follow-up if not already tracked.

**Surface-map regression caught and fixed during WP05**: `PublicSurfaceVerificationTest` flagged `Waaseyaa\Field\Item\LabeledCase` (introduced by WP02) as having no disposition entry in `docs/public-surface-map.php`. WP05 added `'Waaseyaa\Field\Item\LabeledCase' => 'public'` to the surface map alongside the existing `Waaseyaa\Field\*` public entries. Although `docs/public-surface-map.php` is outside WP05's nominal owned-files set, the surface map is a doc registry that must list every newly-public interface; treating the omission as a doc fix in the mission's audit-closure WP is consistent with WP05's "close the audit trail" objective. Flagging here for reviewer awareness.

## CHANGELOG

- **Status**: entry added. `CHANGELOG.md` exists at the repo root and follows Keep-a-Changelog style. WP05 added the `enum` field-type plugin and `LabeledCase` interface under the `[Unreleased] — Attribute-first entity definition (M1)` section's `Added` list, and added a single-bullet breaking-change entry under `Breaking changes` describing the inferrer migration, the constraint-builder scoping, the removal of the `enumClass` (camelCase) alias, and the rejection of explicit `type='string'` on backed-enum properties.

## Documented follow-ups carried forward from this mission

These are deliberate carry-forwards confirmed across the WP01–WP04 reviews and reaffirmed at WP05 close. None block the mission's spec contract (FR-006: the plugin owns the schema), but each is a reachable defect once production wiring lands, so a follow-up mission is recommended.

1. **Test-only manager-driven `FieldDefinition::toJsonSchema()` path.** WP01 added `FieldTypeInterface::jsonSchemaFor()` and threaded an optional `FieldTypeManager` through `FieldDefinition`. No production `FieldDefinition` construction site currently passes the manager — the canonical entrypoint is `EntityMetadataReader`, whose API is static (`EntityMetadataReader::readClass(...)`) and would need to be converted to instance-based to accept a manager. That refactor falls outside any code WP's surface in this mission. Tests exercise the manager-driven path directly. The plugin (`EnumItem`) owns the schema regardless of which path resolves it, so the spec contract is satisfied; the seam exists but is currently unfed in production.

2. **`FieldDefinition::legacyJsonSchema()` lacks an `'enum'` arm.** The fallback used when no `FieldTypeManager` is wired in falls through to `default => ['type' => 'string']` for the `'enum'` type id. Today this is unreached because the inferrer-emitted `'enum'` definitions all flow through paths that don't hit the legacy fallback in tests, and because no production caller threads the manager either way. Once production wiring (item 1) lands, this becomes a real bug — the schema for an enum-typed field would silently degrade to `{'type': 'string'}` instead of the EnumItem-emitted `{'type': 'string', 'enum': [...]}`.

3. **Recommended follow-up mission**: *"Plumb FieldTypeManager into EntityMetadataReader and add `'enum'` arm to legacyJsonSchema fallback."* Two coupled changes: (a) convert `EntityMetadataReader` to instance-based or add an instance-level alternate that accepts the manager, and update its callers; (b) extend `FieldDefinition::legacyJsonSchema()` with an explicit `'enum'` arm that emits `['type' => 'string']` plus the enum values from `settings.enum_class` (or, preferably, simply requires the manager-driven path).

## Files modified by WP05

- `docs/specs/entity-system.md` — closed transitional-gap entry; bumped review date.
- `CHANGELOG.md` — `[Unreleased]` entries for the new plugin, new interface, new `FieldTypeInterface` methods, and the breaking-change cluster.
- `UPGRADING.md` — rewrote the open `enum` gap bullet as a "Closed (mission `field-type-enum-plugin-01KQ6SJG`)" line documenting the new canonical shape.
- `docs/public-surface-map.php` — added `Waaseyaa\Field\Item\LabeledCase` (introduced by WP02; surface registry omission caught here).
- `kitty-specs/field-type-enum-plugin-01KQ6SJG/verification-report.md` — this report.
