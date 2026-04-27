# Bundle-Scoped Field Attribute — Specification (STUB)

**Status**: Stub. Full spec via `/spec-kitty.specify` when ready to plan.
**Predecessor**: `attribute-first-entity-definition-01KQ6DXE`

---

## Scope statement

Extend the `#[Field]` attribute (introduced in M1) with a `bundle:` parameter so multi-bundle entity types declare bundle-specific fields directly on the entity class. Replaces the imperative `EntityTypeManager::addBundleFields()` call path with a declarative attribute path consistent with the rest of attribute-first entity definition.

## Example shape

```php
#[ContentEntityType(id: 'node', label: 'Content', bundles: ['article', 'page'])]
final class Node extends ContentEntityBase {
    #[Field] public string $title;                              // core (all bundles)
    #[Field(bundle: 'article')] public ?string $byline;         // article-only
    #[Field(bundle: 'article')] public ?\DateTimeImmutable $publishedAt;
    #[Field(bundle: 'page')] public ?string $template;          // page-only
}
```

## In-scope sketch

- Add `bundle:` parameter to `#[Field]` (string or null; null = core field).
- Extend `EntityType::fromClass()` to partition fields into core + per-bundle definitions.
- Wire bundle field definitions through `FieldDefinitionRegistry::registerBundleFields()`.
- `#[ContentEntityType]` may need a `bundles:` parameter, OR bundle keys may be discovered from the `#[Field(bundle: …)]` attributes themselves.
- Migrate framework-internal multi-bundle entity types; drop `EntityTypeManager::addBundleFields()` calls.

## Out-of-scope sketch

- Bundle entity-type definition itself (e.g. `node_type` config entity).
- Per-bundle field overrides where a core field is replaced for one bundle.

## Open design questions

- Bundles enumerated on `#[ContentEntityType(bundles: [...])]` vs auto-collected from `#[Field(bundle: …)]`?
- Validation: unknown `bundle:` value throws at registration?
- Inheritance: child entity class adding bundle-scoped fields to parent's bundles?

## Predecessor dependency

- M1 `attribute-first-entity-definition` (the `#[Field]` attribute itself).
