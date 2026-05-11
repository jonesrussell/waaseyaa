# 017 — Per-field translation: declarative flag + entity translation API

**Status:** Accepted (2026-05-11)
**Mission:** Stability charter ratification; clears charter §3.2 beta criterion 9 (matrix §3.2)
**Spec context:** `docs/specs/drupal-comparison-matrix.md` §1.11, §3.2; intersects [ADR 010](010-multi-backend-field-storage.md), [ADR 016](016-revisions-first-class.md).

## Context

Entity schemas already carry a `langcode` column. The translation surface above that — per-field translatability, entity translation API, language fallback chain — does not exist at the framework level. Today apps either treat `langcode` as a filter axis (Minoo's current pattern) or roll their own translation logic per app.

Three forces point this to the framework rather than per-app:

1. **Drupal 12 parity.** Drupal does per-field translation at the entity layer. Parity-with-Drupal-12 standard says not less than that.
2. **Minoo's Anishinaabemowin milestone (#21).** Needs production-grade per-field translation for `teaching`, `dictionary_entry`, `cultural_collection`, and likely more types. Dialect handling (Nishnaabemwin / Eastern Ojibwe) needs to ride at framework level so sibling apps (other Indigenous-language consumers) inherit the same primitives.
3. **Beta gate.** Charter §3.2 criterion 9 currently blocks beta until matrix §3.2 has an ADR. This ADR clears that gate.

Translation interacts with two recent ADRs. Storage backends (ADR 010) need a per-langcode value extension. Revisions (ADR 016) and translations together produce per-(entity, langcode, revision) state, which is the most complex three-way interaction in the framework.

## Options considered

### A. App-level (apps roll their own)

Each app implements translation. Rejected: forfeits cross-app primitives, sister apps reinvent dialect handling, framework's mission promise weakened.

### B. Per-entity translation only

Every field on an entity is either fully translatable or not. Rejected: too coarse. An `event` has translatable fields (title, description) and non-translatable fields (`starts_at`, `community_id`, `capacity`); per-entity coarseness forces awkward modeling.

### C. Per-field translatable flag + entity translation API (CHOSEN)

`FieldDefinition::translatable()` declares per-field translatability. Entity translation API on `EntityInterface` provides `getTranslation($langcode)`, `hasTranslation($langcode)`, etc. Language fallback chain configurable per app. Matches Drupal's grain; fits the existing `FieldDefinition` extension pattern.

## Decision

Per-field translation as first-class framework surface.

### Declaration

```php
FieldDefinition::create('title', 'string')->translatable()
FieldDefinition::create('community_id', 'int')          // not translatable; default
```

Default: **non-translatable**. Existing entities continue to behave as today. Apps opt fields into translation field-by-field.

### Stable surface

New interface `Waaseyaa\Entity\TranslatableEntityInterface`:

```php
interface TranslatableEntityInterface extends EntityInterface
{
    public function defaultLangcode(): string;
    public function activeLangcode(): string;            // the langcode this loaded instance represents
    public function hasTranslation(string $langcode): bool;
    public function getTranslation(string $langcode): static;
    public function addTranslation(string $langcode): static;
    public function removeTranslation(string $langcode): void;
    public function translations(): iterable;            // string[] of langcodes that exist
}
```

`EntityType` declares translatability per type:

```php
new EntityType(
    id: 'teaching',
    translatable: true,
    entityKeys: [
        ...,
        'langcode' => 'langcode',           // already present
        'default_langcode' => 'default_langcode',  // new
    ],
)
```

Entity types with `translatable: true` MUST implement `TranslatableEntityInterface`. Non-translatable entity types behave exactly as today (no surface change).

### Storage shape

Storage backends (ADR 010) extend to carry per-langcode values for translatable fields.

- **`sql-blob` backend:** the `_data` blob becomes a per-langcode map. Schema gains rows keyed on `(entity_id, langcode)` rather than `(entity_id)` alone. Non-translatable field values are stored on the default-langcode row only; translation reads of a non-translatable field fall through to the default-langcode value.
- **`sql-column` backend:** translatable fields move to a sibling table `<table>__translation` keyed by `(entity_id, langcode)`. Non-translatable fields stay on the primary table. The coordinator joins them on read.

Backend-side schema details are mission-spec concerns, not ADR concerns. The contract: a translatable field reads/writes per langcode; a non-translatable field reads/writes once.

### Language fallback chain

Configurable per app via `config/waaseyaa.php`:

```php
'translation' => [
    'fallback_chain' => fn (string $requested, EntityInterface $entity): array => [
        $requested,
        $entity->defaultLangcode(),
        'en',
    ],
],
```

Default chain when not configured: `[requested, entity-default, site-default, 'en']`. The chain is applied per-field: if a translatable field has no value at the requested langcode, the framework walks the chain until it finds one. If the chain exhausts, the field returns `null`.

Fallback behavior is observable through a new method `$entity->fieldLangcode(string $fieldName): ?string` that returns the langcode where the value was actually found, or `null` if it fell through.

### Revision × translation interaction

The hardest interaction in the framework. Two semantics are plausible:

- **Single-revision-spans-all-languages.** Every save creates one revision; that revision contains values for every language. Simple; loses ability to independently revise translations.
- **Per-(entity, langcode) revisions.** Revisions key on `(entity_id, langcode, vid)`. Translating from English to Anishinaabemowin creates a revision of the Anishinaabemowin translation, leaving the English revision history untouched. Matches Drupal.

**Decision: per-(entity, langcode) revisions** for revisionable + translatable entity types. This is the right semantics for Minoo's Knowledge Keeper editorial flow — Elder edits to the Anishinaabemowin translation should not invalidate English revision history.

Implementation cost is real. The Entity Storage v2 mission (`entity-storage-v2.md`) currently defers revisionable+translatable to v1.x per ADR 016. This ADR overrides that deferral: revisionable+translatable is **in scope** for v0.x, but the implementation work lands in a follow-up mission after entity-storage-v2 ships its single-axis (revisionable OR translatable) substrate.

Sequencing: entity-storage-v2 ships revisions on non-translatable types and translations on non-revisionable types as parallel first-class features. The two-axis combination (revisionable + translatable) ships in a dedicated follow-up mission (`entity-storage-translatable-revisions.md`, TBD), which is the natural unit of work for the hardest interaction.

### Translation operation in access policies

New access operation `translate`:

```php
#[PolicyAttribute(entityType: 'teaching', operations: ['view', 'edit', 'translate'])]
final class TeachingAccessPolicy { ... }
```

`translate` governs "may this actor create or modify the Anishinaabemowin translation of this teaching." Policies that do NOT declare `translate` fall back to `edit`. The framework MUST NOT default-deny.

### What is NOT in scope

- **Translation provider integrations** (Google Translate, DeepL, etc.) — out of scope; app concern or future package.
- **Interface translation** (UI strings) — already handled by the `i18n` package + `trans()` Twig function.
- **Config translation** — different scope; a future ADR addresses it under config sync (ADR 018).
- **Language negotiation** (URL prefix, browser, session) — framework provides primitives but app owns the negotiation policy.
- **RTL support** — CSS/design concern, not entity concern.

### Migration of existing entity types

Existing entity types continue without translation. Opting in is per-type and per-field:

1. Declare `translatable: true` on `EntityType` and add `default_langcode` entityKey.
2. Implement `TranslatableEntityInterface` on the entity class.
3. Mark individual fields `translatable()` on their `FieldDefinition`.
4. Run a migration that promotes the table schema (sibling translation table for `sql-column`; per-langcode key for `sql-blob`).
5. Backfill: existing rows become the default-langcode translation; no other translations exist until added.

Reversible. Per-type. Voluntary.

## Consequences

- **Framework gains a major new entity-layer concern.** Per-field translation is large; the cost is justified by Drupal-12 parity and Minoo's milestone-#21 needs.
- **Storage backends gain a per-langcode contract extension.** The Entity Storage v2 mission spec must extend FR-011 / FR-012 / FR-017 to acknowledge translatable fields.
- **Revisions × translations becomes a v0.x concern**, contradicting ADR 016's v1.x deferral. This is the right reversal because Minoo's `teaching` is the canonical use case for both; deferring would force Minoo to pick one.
- **A new follow-up mission is named:** `entity-storage-translatable-revisions.md` (TBD). Lands after `entity-storage-v2.md` and after the WordPress migration substrate.
- **Beta gate criterion 9 is partially cleared.** Matrix §3.2 moves from `❌` to `📋` (planned). Charter §11 Q7 loses one of its two unresolved items.
- **Access surface gains `translate` operation.** Additive; falls back to `edit` for policies that don't declare it.
- **Minoo can deliver Anishinaabemowin localization (#21) on framework primitives**, not app-level workarounds. Sister apps building on Waaseyaa for other Indigenous languages inherit the same surface.

## References

- Matrix: `docs/specs/drupal-comparison-matrix.md` §1.11, §3.2, §6.2.
- Charter: `docs/specs/stability-charter.md` §3.2 criterion 9, §5.3 (entity surface).
- Related ADRs: [ADR 010](010-multi-backend-field-storage.md) (storage extends per-langcode), [ADR 011](011-entity-lifecycle-events.md) (events fire per-langcode save), [ADR 016](016-revisions-first-class.md) (revisions × translations interaction).
- Audit reference: `waaseyaa/minoo/docs/audits/2026-05-11-framework-app-audit.md` (mission-completeness gap).
- Drupal prior art: Entity Translation API, Content Translation module, hook_entity_translation_*.
- Minoo milestone: #21 (Anishinaabemowin Localization).
