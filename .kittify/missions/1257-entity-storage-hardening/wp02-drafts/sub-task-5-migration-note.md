# Sub-task 5 — `HasCommunityInterface` deprecation migration note (DRAFT)

**Lands in:** `packages/groups/CHANGELOG.md` (operator-facing) + cross-referenced from `docs/specs/entity-system.md` §Community Scoping (sub-task 3 edit 3.F).

**Cycle:** Deprecation begins at the next `waaseyaa/groups` release containing WP10. Removal scheduled for the next **minor** release after that.

**Audience:** Anyone whose entity classes implement `HasCommunityInterface` / use `HasCommunityTrait` and who registers community-scoped entity types via `EntityType`.

---

## Proposed CHANGELOG entry

The entry below is targeted at the next `waaseyaa/groups` release. Place under a new `### Deprecated` heading (Keep a Changelog format).

```markdown
### Deprecated

- **`Waaseyaa\Entity\Community\HasCommunityInterface` and `HasCommunityTrait`.** Tenancy opt-in is moving from a class-hierarchy marker to a declarative key on `EntityType` registration. Consumers must declare `tenancy: ['scope' => 'community']` on the entity type. The marker interface continues to function during this release; `LoggerInterface::warning()` emits a one-time deprecation notice per `(entity-type id, process)` on first wiring. Removal: the next minor release. See migration recipe below.
```

---

## Proposed migration recipe (full body of CHANGELOG section)

```markdown
## Migrating from `HasCommunityInterface` to declarative `tenancy:`

This release moves community-scoping opt-in from a marker interface on the entity class to a declarative key on `EntityType` registration. The change unblocks framework-shipped `final` entity classes (e.g. `Waaseyaa\Groups\Group`) from being community-scoped by consumers — historically blocked because a `final` class cannot be marked from outside.

### What changes

| Before | After |
|---|---|
| `class MyEntity extends ContentEntityBase implements HasCommunityInterface { use HasCommunityTrait; }` | `class MyEntity extends ContentEntityBase { /* no marker */ }` |
| Service provider wiring: `is_a($entityType->getClass(), HasCommunityInterface::class, true)` triggers `CommunityScope` injection | `EntityType` registration declares `tenancy: ['scope' => 'community']`; `SqlStorageDriver` and `MemoryStorageDriver` wire `CommunityScope` from this declaration. |
| Schema requires entity table to carry a `community_id` column. | Unchanged — schema column stays. The trait no longer drives wiring; column ownership is independent. |

### Migration steps

1. **Add `tenancy:` to your `EntityType` registration.**

   ```php
   $entityTypeManager->registerEntityType(new EntityType(
       id: 'my_entity',
       label: 'My Entity',
       class: MyEntity::class,
       keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
       tenancy: ['scope' => 'community'],   // ← NEW
   ));
   ```

2. **Verify isolation tests still pass.** Run your existing cross-tenant isolation suite. Behavior should be identical: `CommunityScope` is still injected, just from a different signal.

3. **Remove `HasCommunityInterface` from the class.** Delete the `implements HasCommunityInterface` clause and the `use HasCommunityTrait;` line. The schema column (`community_id`) stays — `ContentEntityBase::get('community_id')` / `set('community_id')` continue to work without the trait.

4. **Confirm the deprecation log line stops firing.** After the registration update, the `[deprecated] HasCommunityInterface on entity-type "X"` warning should no longer appear in your logs on first wiring.

### Note for adopters mid-migration on `Waaseyaa\Groups\Group`

If your application currently runs a local `App\Entity\Group` alongside a composer dep on `waaseyaa/groups` (transition state — the local class predates `Waaseyaa\Groups\Group` becoming framework-shipped), apply the migration in this order:

1. **First:** add `tenancy: ['scope' => 'community']` to your `EntityType` registration that uses `Waaseyaa\Groups\Group`.
2. **Then:** verify the cross-tenant isolation tests still pass against the framework class.
3. **Only then:** collapse `App\Entity\Group` onto `Waaseyaa\Groups\Group` (`final`, no `HasCommunityInterface`) and update call sites.

Order matters: the `tenancy:` flip is wiring-local and reversible; the class collapse touches every consumer.

### Removal timeline

- **This release** (WP10 of mission #1257): `tenancy:` ratified canonical; `HasCommunityInterface` continues to function with one-time deprecation log per `(entity-type id, process)`.
- **Next minor release**: `HasCommunityInterface`, `HasCommunityTrait`, and the `is_a()` opt-in check in service providers are removed. Code that still relies on the marker stops getting `CommunityScope` injection at boot — entities will no longer be tenant-scoped without the registration key.

### Reference

- Architectural decision: `.kittify/missions/1257-entity-storage-hardening/spec.md` §C1.
- Spec: `docs/specs/entity-system.md` §Community Scoping → §Tenancy declaration.
- Implementation work package: WP10 (tenancy-opt-in-via-entitytype) of mission #1257.
```

---

## What sub-task 5 does *not* do

- Doesn't touch code (WP10 implements; this is the user-facing prose).
- Doesn't add a section to `entity-system.md` itself — the migration text in entity-system.md (sub-task 3 edit 3.F → "Migration: HasCommunityInterface → declarative tenancy") cross-references this CHANGELOG entry rather than duplicating it. Single source of truth, two readable surfaces.
