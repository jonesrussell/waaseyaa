# Attribute-Entity Classmap Discovery — Specification (STUB)

**Status**: Stub. Full spec to be written via `/spec-kitty.specify` when M1 (`attribute-first-entity-definition`) is merged.
**Track position**: M2 of 2 in the attribute-first track.
**Predecessor**: `attribute-first-entity-definition-01KQ6DXE`

---

## Scope statement

Eliminate the remaining manual ceremony around entity registration. Once M1 has made `EntityType::fromClass()` the only way to build an `EntityType`, this mission removes the need to call it explicitly. The framework discovers attribute-decorated entity classes via the Composer classmap and registers them automatically at boot.

After this mission lands, an app declares an entity by:
- Writing a single PHP class with `#[ContentEntityType]` and `#[Field]` attributes.
- Adding the class's namespace to `composer.json` under `autoload.psr-4`.

Nothing else. No `config/entity-types.php`. No `EntityType::fromClass()` call. No `EntityTypeManager::registerEntityType()` call.

## In-scope sketch

- Composer classmap walker that finds classes carrying `#[ContentEntityType]`.
- Boot-time registration hook (kernel-level) that registers each discovered class.
- Cache the discovery result (build-time / first-boot cache; invalidated on composer install/update).
- Strategy for excluding test-only / fixture entities from production discovery.
- Migrate framework-internal entity registrations to the auto-discovered path; remove all `EntityType::fromClass()` boilerplate.
- Update `docs/specs/entity-system.md` to reflect single-step entity definition.

## Out-of-scope sketch

- Per-app extension points to override or replace discovered entity types (possible follow-up).
- Discovery for entity classes outside Composer-managed paths.

## Open design questions

- Cache invalidation strategy: explicit CLI command vs filesystem mtime vs composer hook.
- Interaction with bundles (M1 defers bundle attributes; coordinate with `bundle-scoped-field-attribute`).
- Eager vs lazy registration during kernel boot.
- Test isolation: how does a test register a fixture entity without polluting global state?

## Predecessor dependency

- M1 `attribute-first-entity-definition` must merge first; consumes `EntityType::fromClass()` and extended `#[ContentEntityType]`.
