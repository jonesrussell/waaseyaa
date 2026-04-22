# waaseyaa/groups

**Layer 2 — Content Types**

Multi-bundle `group` content entity type for Waaseyaa applications. This package is **framework-agnostic**: consuming apps register bundles and fields; it does not target any single product domain.

**History:** Originally extracted from an early Waaseyaa product codebase; extraction context belongs in this repository’s changelog, not in runtime docs.

Defines two entity types:

- `group` — the content entity. Bundle-aware (`bundleEntityType: 'group_type'`), keyed by `gid`/`uuid`, labeled by `name`.
- `group_type` — the config entity that declares bundle identities.

The package ships with **zero pre-registered bundles and zero bundle-scoped fields**. Consuming applications create `GroupType` config entities and register bundle-scoped fields via `EntityTypeManager::addBundleFields($entityTypeId, $bundle, $fields)`. Bundle-scoped columns land in per-bundle subtables named `group__{bundle}` as described in `docs/specs/bundle-scoped-storage.md`.

Key classes: `Group`, `GroupType`, `GroupsServiceProvider`.

## Adding a bundle

```php
$entityTypeManager->addBundleFields('group', 'business', [
    new FieldDefinition(
        name: 'email',
        type: 'string',
        targetEntityTypeId: 'group',
        targetBundle: 'business',
    ),
]);
```

The framework materializes `group__business` on the next install / schema pass. Missing subtables for registered-but-unmaterialized bundles are reported by `waaseyaa health:check` as `MISSING_BUNDLE_SUBTABLE`.
