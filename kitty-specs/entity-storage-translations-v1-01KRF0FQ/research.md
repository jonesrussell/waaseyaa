# Phase 0 Research: Entity Storage — Single-Axis Translations v1

**Mission:** M-006 / `01KRF0FQ0AA42F434JNAA56WFB`
**Date:** 2026-05-12
**Plan:** [`plan.md`](plan.md)

Resolves every open issue surfaced during planning interrogation. No `[NEEDS CLARIFICATION]` markers remain.

---

## R1 — TranslatableInterface naming reconciliation

**Question:** Spec FR-006 says: *"Interface `Waaseyaa\Entity\TranslatableEntityInterface extends EntityInterface` MUST be declared in `packages/entity/src/`."* But `packages/entity/src/TranslatableInterface.php` already exists with a minimal partial surface (`language()`, `hasTranslation()`, `getTranslation()`, `getTranslationLanguages()`). Two interfaces with overlapping intent will confuse the surface.

**Decision:** Keep the existing class name `TranslatableInterface` (Waaseyaa namespace canonical) and expand it to the full surface described in spec §3.2 (FR-006..FR-015). Update spec FRs in WP01's implementer task brief to use the canonical name.

**Method-name reconciliation:**

| Spec FR name (old) | Existing TranslatableInterface method | Resolution |
|---|---|---|
| `activeLangcode(): string` (FR-008) | `language(): string` | Add `activeLangcode()` as canonical; keep `language()` as deprecated alias for one minor cycle (zero callers in framework today — both are net-new in practice since no entity type sets `translatable: true`). |
| `translations(): iterable<string>` (FR-013) | `getTranslationLanguages(): array` | Keep `getTranslationLanguages()` as primary (more explicit, already typed); add `translations()` as alias. |
| `hasTranslation(string): bool` (FR-009) | `hasTranslation(string): bool` | Identical. No change. |
| `getTranslation(string): static` (FR-010) | `getTranslation(string): static` | Identical. No change. |
| `defaultLangcode(): string` (FR-007) | — (missing) | NEW method. |
| `addTranslation(string): static` (FR-011) | — (missing) | NEW method. |
| `removeTranslation(string): void` (FR-012) | — (missing) | NEW method. |
| `fieldLangcode(string): ?string` (FR-015) | — (missing) | NEW method on `ContentEntityBase` (not on the interface — see R7). |

**Rationale:** The existing `TranslatableInterface.php` predates this mission's spec. Its docblock already locks in the canonical project semantics: *"a Waaseyaa entity object represents ONE language at a time. getTranslation() returns a separate entity object."* That matches spec §7.2 exactly. Keeping the name (not introducing `TranslatableEntityInterface`) removes a confusing parallel surface and respects the existing project decision.

**Alternatives considered:**

- *Rename `TranslatableInterface` → `TranslatableEntityInterface` and delete the old stub.* Slightly cleaner from a "no legacy" standpoint, but adds churn for zero benefit since no caller depends on the old name yet.
- *Keep both interfaces, with `TranslatableEntityInterface extends TranslatableInterface`.* Rejected: introduces a needless parallel surface with no clear semantic distinction.

**Spec impact:** Spec FR-006/FR-010/FR-014 reference `TranslatableEntityInterface`; WP01 must include a "reconcile to TranslatableInterface" task in the implementer brief. The mission-close review (WP14) will update the spec retroactively to reflect the shipped name.

---

## R2 — FieldDefinition readonly-builder pattern

**Question:** `FieldDefinition` is `final readonly class`. Spec FR-016 requires a `translatable(bool): static` builder. Readonly classes cannot mutate; how is the builder shaped?

**Decision:** Follow the precedent set by `FieldDefinition::storedIn(string $backendId): self` (line 209) and `FieldDefinition::indexed(): self` (line 252). Each builder constructs a new instance with the field-list copied and the target field swapped:

```php
public function translatable(bool $value = true): self
{
    return new self(
        // ... all current constructor params copied ...
        translatable: $value,
        // ...
    );
}
```

The `isTranslatable(): bool` getter already exists at line 78; behaviour previously returned `false` for all callers because no constructor route set the param. After this mission, the param is load-bearing.

**Rationale:** Pattern is already established and tested in the codebase. Zero novel design needed.

**Alternatives considered:**

- *Static factory `FieldDefinition::translatableField(...)`*. Rejected: breaks the fluent builder API consumers already use.
- *Mutator method.* Rejected: violates the `readonly` invariant the class declares.

---

## R3 — EntityEvent extension shape

**Question:** Spec FR-043: *"`EntityEvent` payloads MUST gain a `?string $langcode` public readonly property."* What's the shape?

**Decision:** Append `?string $langcode` as the third constructor parameter with default `null`. Existing call sites are unaffected.

```php
final class EntityEvent extends Event
{
    public function __construct(
        public readonly EntityInterface $entity,
        public readonly ?EntityInterface $originalEntity = null,
        public readonly ?string $langcode = null,
    ) {}
}
```

For translation-specific events (`PRE/POST_TRANSLATION_INSERT/UPDATE/DELETE`), introduce a thin subclass `TranslationEvent extends EntityEvent` whose constructor requires `$langcode` (no default). This makes "translation-specific code path" type-narrowable for listeners.

```php
final class TranslationEvent extends EntityEvent
{
    public function __construct(
        EntityInterface $entity,
        public readonly string $langcode,
        ?EntityInterface $originalEntity = null,
    ) {
        parent::__construct($entity, $originalEntity, $langcode);
    }
}
```

Listeners that need entity-level events only catch `EntityEvent`; listeners that need per-translation behaviour catch `TranslationEvent`. The dispatched name (event ID string) carries the semantic.

**Rationale:** Minimum API surface; type-narrowable; backward compatible.

**Alternatives considered:**

- *Pure constants on `EntityEvent`* — works, but listeners can't `instanceof TranslationEvent` to differentiate. Less ergonomic.
- *Six independent event classes* — too many; redundant.

---

## R4 — Translation event class hierarchy

**Question:** Six event-name constants are needed (`PRE/POST_TRANSLATION_INSERT/UPDATE/DELETE`). Where do they live?

**Decision:** Add to the existing `EntityEvents` registry (or equivalent — verify naming in WP08). All six constants point at the `TranslationEvent` class. Dispatcher loops translations explicitly only when the entity has more than one translation. Single-translation path is one event dispatch (entity-level only).

**Naming convention:**

```php
final class EntityEvents
{
    public const PRE_INSERT = 'waaseyaa.entity.pre_insert';
    public const POST_INSERT = 'waaseyaa.entity.post_insert';
    public const PRE_UPDATE = 'waaseyaa.entity.pre_update';
    public const POST_UPDATE = 'waaseyaa.entity.post_update';
    public const PRE_DELETE = 'waaseyaa.entity.pre_delete';
    public const POST_DELETE = 'waaseyaa.entity.post_delete';
    // NEW (WP08):
    public const PRE_TRANSLATION_INSERT = 'waaseyaa.entity.pre_translation_insert';
    public const POST_TRANSLATION_INSERT = 'waaseyaa.entity.post_translation_insert';
    public const PRE_TRANSLATION_UPDATE = 'waaseyaa.entity.pre_translation_update';
    public const POST_TRANSLATION_UPDATE = 'waaseyaa.entity.post_translation_update';
    public const PRE_TRANSLATION_DELETE = 'waaseyaa.entity.pre_translation_delete';
    public const POST_TRANSLATION_DELETE = 'waaseyaa.entity.post_translation_delete';
}
```

Names are bare strings on the wire (Symfony EventDispatcher convention). The class hosting them gives discoverability via static analysis.

**Rationale:** Symfony-idiomatic; bare strings on the wire; static analysis surfaces names.

---

## R5 — `SqlSchemaHandler` translation table sync

**Question:** How does the schema-sync routine learn about translation tables?

**Decision:** Extend `EntitySchemaSync` (already reads `EntityType::isTranslatable()` per the grep against current code) to allocate `<table>__translation` when the flag is true. Branch is purely additive: the existing single-table-per-entity-type path stays for `translatable: false`.

**Translation-table column derivation** (for `sql-column` backend):

1. Iterate `EntityType::getFieldDefinitions()`.
2. Partition into translatable / non-translatable buckets via `FieldDefinition::isTranslatable()`.
3. Translatable bucket + `entity_id` + `langcode` → translation table.
4. Non-translatable bucket → stay on primary table.

For `sql-blob`: primary key widens to `(entity_id, langcode)`. `_data` blob contains translatable fields only; non-translatable fields are stored once on the default-langcode row.

**Rationale:** Minimum extension. Existing code path is preserved verbatim for non-translatable types (NFR-001 invariant).

---

## R6 — `bin/check-package-layers` and dependency-direction safety

**Question:** Does the mission introduce any upward layer edges that would fail `bin/check-package-layers`?

**Decision:** No upward edges introduced.

| New / changed surface | Package | Layer |
|---|---|---|
| `TranslatableInterface` (expand) | entity | L1 |
| `EntityTranslationException` | entity | L1 |
| `TranslationEvent` | entity | L1 |
| `FieldDefinition::translatable()` | field | L1 |
| `SaveContext::withLangcode` | entity-storage | L1 |
| Schema sync, hydrator, coordinator | entity-storage | L1 |
| `AccessChecker` translate op recognition | access | L1 |
| `MakeMigrationCommand --add-translations` | cli | L6 (Interfaces) — already depends on entity-storage |
| `LanguageManager` consumption | i18n (L0) read **from** entity-storage (L1) — but the dependency is in the opposite direction: entity-storage uses an L0 service via optional DI. Allowed. |

L6 → L1 (cli → entity-storage) is already established. No new package-graph edges.

---

## R7 — Test fixture autoload pattern (production-install gotcha)

**Question:** Spec FR-058 ships a `TranslatableEntityContractTest` base class. Where does it live?

**Decision:** `packages/entity/testing/TranslatableEntityContractTest.php`, registered via `autoload-dev` in `packages/entity/composer.json` (not `autoload`).

**Rationale:** Documented project gotcha — production installs (`composer install --no-dev`) run reflection-based class scans on `autoload` paths. A test-helper class that `extends PHPUnit\Framework\TestCase` placed under `autoload` triggers a `Class "PHPUnit\Framework\TestCase" not found` fatal at kernel boot, crashing every consumer. This was the lesson from waaseyaa/graphql alpha.106 → alpha.107 (production outage on minoo). Project memory: feedback_partial_fix_closes_footer.md is not the lesson; the lesson lives in the `Code Style` section of `CLAUDE.md`: *"Never put classes that extend dev-only deps under `autoload`."*

**Where `fieldLangcode()` lives** (related sub-question): On `ContentEntityBase`, not on `TranslatableInterface`. Reason: the method tracks per-instance state (last-resolved langcode per field) that's specific to the loaded entity — not a contract every implementer must define. Adding it to the interface would force every future implementer to allocate state, which is unnecessary. Keep the interface minimal; the base class owns the observability.

---

## R8 — `default_langcode` entity key migration policy

**Question:** Spec adds a new entity key `default_langcode`. What's the policy for existing data when a consumer flips an entity type to `translatable: true`?

**Decision:** Three-step flow, owned by WP11 (`MakeMigrationCommand --add-translations`):

1. **`--default-langcode <lc>` is required.** Generator refuses to run without it (FR-052).
2. **Existing rows backfill `langcode = default_langcode = <lc>`.** This is non-destructive for rows that already had `langcode` set (mismatch emits a warning, preserves data; see §8.2 of spec).
3. **Schema sync** then proceeds with the widened primary key (sql-blob) or sibling translation table (sql-column) per backend.

For the framework's own fixture entity type, no existing data exists — the fixture is born translatable.

For consumer apps (Minoo `teaching`) that flip the flag post-release: they ship the generated `--add-translations` migration as a normal Doctrine migration in their app's `migrations/` directory. No framework runtime auto-migration.

---

## R9 — Fallback chain implementation

**Question:** Per spec FR-037, the chain is configured as a callable in `config/waaseyaa.php`. What does the resolver look like?

**Decision:**

```php
final readonly class FallbackChainResolver
{
    public function __construct(
        private \Closure $chainFn,             // (string, EntityInterface) => string[]
        private int $maxChainLength = 8,       // NFR-002
    ) {}

    /** @return iterable<string> */
    public function resolve(string $requested, EntityInterface $entity): iterable
    {
        $chain = ($this->chainFn)($requested, $entity);
        if (count($chain) > $this->maxChainLength) {
            throw new InvalidConfigurationException(
                "Fallback chain length {$count} exceeds maximum {$this->maxChainLength}"
            );
        }
        // De-duplicate while preserving order
        $seen = [];
        foreach ($chain as $lc) {
            if (!isset($seen[$lc])) {
                $seen[$lc] = true;
                yield $lc;
            }
        }
    }
}
```

Default chain (when not configured):

```php
fn (string $requested, EntityInterface $entity): array => [
    $requested,
    $entity instanceof TranslatableInterface ? $entity->defaultLangcode() : 'en',
    $siteDefault ?? 'en',
    'en',
];
```

`ContentEntityBase` consumes `FallbackChainResolver` via optional DI (defaults to a NullResolver returning `[$requested]` only). For each translatable field read, walk the resolver until a non-null value is found.

`$entity->fieldLangcode(string $fieldName): ?string` reads from a per-instance map populated by the last field-read.

---

## R10 — `EntityRepository::findTranslations()` query shape

**Question:** Spec FR-042 requires a single-query implementation. Asserted via query-count test (NFR-005).

**Decision:**

```sql
-- sql-column
SELECT t.*, pri.*
FROM <table>__translation t
INNER JOIN <table> pri ON pri.entity_id = t.entity_id
WHERE t.entity_id = ?
```

```sql
-- sql-blob (rows are per-langcode in the primary table)
SELECT *
FROM <table>
WHERE entity_id = ?
```

Returns one entity instance per langcode. The repository hydrates each row into a `TranslatableInterface` instance with `activeLangcode()` set to the row's `langcode`.

**Rationale:** Aligns with `findBy()` query-builder pattern. No N+1.

---

## R11 — Access policy `translate` operation default behaviour

**Question:** Spec FR-048: *"when no policy answers `translate`, the entity-level `update` decision MUST apply."* How is "no policy answers" detected?

**Decision:** The existing `AccessChecker` already recognizes operation names. Add `'translate'` to the recognized set. When `AccessPolicyInterface::access()` returns `Neutral` (no answer), fall through to `'update'`. The "translate ⊆ update" rule applies only when explicit `translate` policies return Neutral; an explicit Forbidden on `translate` is honoured.

**Rationale:** Mirrors the existing access-policy "open-by-default at field-level, deny-unless-granted at entity-level" semantics described in the access-control spec.

---

## R12 — `read_active_language` config + LanguageManager wire-up

**Question:** Spec FR-041 makes `EntityRepository::find()` *optionally* return the active translation if available. How is this opt-in surfaced?

**Decision:** Two-level config:

```php
// config/waaseyaa.php
return [
    'translation' => [
        'read_active_language' => env('WAASEYAA_TRANSLATION_READ_ACTIVE_LANGUAGE', false),
        'fallback_chain' => null,  // null → default chain (R9)
    ],
];
```

`EntityRepository` accepts a nullable `LanguageManagerInterface` constructor param. When set AND `read_active_language === true` AND active langcode differs from default AND hasTranslation(active): return the active translation. Otherwise return default langcode translation.

When `LanguageManagerInterface` is not provided (CLI / queue / test context): behaviour is as if `read_active_language === false`. Always returns default.

**Rationale:** C-004 — i18n is optional DI. CLI / queue contexts (no HTTP request, no active language) get deterministic default-langcode behaviour without configuration.

---

## R13 — `ContentEntityBase::implements TranslatableInterface` for non-translatable types

**Question:** Spec FR-014: *"`ContentEntityBase` (existing) MUST implement `TranslatableInterface`; methods MUST throw `EntityTranslationException::notTranslatable()` when called on an entity whose type has `translatable: false`."* Why throw instead of returning sensible defaults?

**Decision:** Throw. A caller asking for `$entity->getTranslation('fr')` on a `translatable: false` entity has a logic bug; silently returning `$this` would mask it. Throwing surfaces it.

This matches the broader project pattern: explicit failure beats silent fallback (e.g., `EntityType::isTranslatable()` boot validation, `enforceIsNew()` semantics, etc.).

**Rationale:** Fail-fast invariant aligns with D1 (default-langcode source).

---

## R14 — Implementer / reviewer agent assignments

| Role | Subagent | Tooling |
|---|---|---|
| Implementer | `sonnet` via `Agent` tool with `subagent_type: claude` | Full repo access, `composer install` in lane worktree, `composer phpstan && composer cs-check && vendor/bin/phpunit` per WP. |
| Reviewer | `opus` via `Agent` tool with `subagent_type: claude` | Same access; runs the same gates plus reads spec / plan / contracts before approving. |
| Escalation after 3 consecutive rejections | `opus-as-implementer` | Switch implementer to opus, find a different reviewer or human (`@jonesrussell`). |

Lane worktrees lack `vendor/` — implementer brief must include `composer install` as the first step (project memory: `feedback_lane_worktrees_no_vendor.md`).

Authoritative gates are `composer` scripts. Intelephense diagnostics in lane worktrees are vendor-resolution noise (project memory: prior session).

---

## Decisions summary table

| ID | Topic | Decision |
|---|---|---|
| R1 | Interface naming | Expand existing `TranslatableInterface`, do not create `TranslatableEntityInterface`. |
| R2 | FieldDefinition builder | New-self pattern matching `storedIn()`/`indexed()`. |
| R3 | EntityEvent shape | Append `?string $langcode`. Subclass `TranslationEvent` for translation-specific events. |
| R4 | Event hierarchy | Constants on `EntityEvents` registry; one `TranslationEvent` class. |
| R5 | Schema sync | Extend `EntitySchemaSync` additively; non-translatable types unchanged. |
| R6 | Layer-graph impact | Zero new upward edges; all surface stays in L1+L0. |
| R7 | Testing autoload | `testing/` directory under `autoload-dev` only. |
| R8 | Migration policy | `--add-translations` + required `--default-langcode`; backfill non-destructively. |
| R9 | Fallback chain | `FallbackChainResolver` consuming a closure; bounded by NFR-002. |
| R10 | findTranslations query | Single INNER JOIN (sql-column) / single WHERE (sql-blob). |
| R11 | translate op default | translate ⊆ update; explicit Forbidden on translate is honoured. |
| R12 | LanguageManager wire-up | Optional DI; gated on `read_active_language` config. |
| R13 | Non-translatable callers | Throw `EntityTranslationException::notTranslatable()`. |
| R14 | Agent assignments | sonnet implementer + opus reviewer; opus escalation after 3 rejections. |

All decisions feed directly into `data-model.md`, `contracts/`, and the WP decomposition.
