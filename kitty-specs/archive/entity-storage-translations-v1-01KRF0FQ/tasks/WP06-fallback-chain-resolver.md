---
work_package_id: WP06
title: FallbackChainResolver + trait fallback wiring
dependencies:
- WP04
- WP05
requirement_refs:
- FR-015
- FR-037
- FR-038
- FR-039
- NFR-002
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T027
- T028
- T029
- T030
- T031
history: []
authoritative_surface: packages/entity/
execution_mode: code_change
owned_files:
- packages/entity/src/Hydration/FallbackChainResolver.php
- packages/entity/src/Exception/InvalidConfigurationException.php
- packages/entity/tests/Unit/Hydration/FallbackChain*
tags: []
agent: "claude:opus:waaseyaa-reviewer:reviewer"
shell_pid: "578760"
---

# WP06 — FallbackChainResolver + trait fallback wiring

## Objective

Ship the configurable language-fallback resolver and wire it into `TranslatableEntityTrait` so translatable-field reads consult the chain. Per-field-resolution observability via `fieldLangcode()`. EntityRepository changes (LanguageManager constructor, active-language `find()`) ship in WP10 to avoid file-ownership overlap on `EntityRepository.php`.

## Context

- **Spec:** [`../spec.md`](../spec.md) §3.7 (FR-037..FR-039) — `EntityRepository` integration (FR-040, FR-041) ships in WP10.
- **Research:** [`../research.md`](../research.md) R9 (fallback resolver shape).

## Subtasks

### T027 — `FallbackChainResolver` class

**Steps:**

1. Create `packages/entity/src/Hydration/FallbackChainResolver.php`:

   ```php
   <?php
   declare(strict_types=1);
   namespace Waaseyaa\Entity\Hydration;

   use Waaseyaa\Entity\EntityInterface;
   use Waaseyaa\Entity\Exception\InvalidConfigurationException;

   final readonly class FallbackChainResolver
   {
       public function __construct(
           private \Closure $chainFn,
           private int $maxChainLength = 8,
       ) {}

       /** @return iterable<string> */
       public function resolve(string $requested, EntityInterface $entity): iterable
       {
           $chain = ($this->chainFn)($requested, $entity);
           if (count($chain) > $this->maxChainLength) {
               throw InvalidConfigurationException::fallbackChainTooLong(
                   count($chain),
                   $this->maxChainLength
               );
           }
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

**Files:** ~50 lines.

### T028 — `InvalidConfigurationException`

**Steps:**

1. Create or extend `packages/entity/src/Exception/InvalidConfigurationException.php`:
   ```php
   public static function fallbackChainTooLong(int $actual, int $max): self
   {
       return new self(sprintf(
           'Fallback chain length %d exceeds maximum %d. Configure translation.fallback_chain to return at most %d entries.',
           $actual,
           $max,
           $max
       ));
   }
   ```

**Files:** ~30 lines.

### T029 — Default chain function

**Steps:**

1. In a service provider or `EntityServiceProvider`, register a default `FallbackChainResolver` if the consumer doesn't configure one:
   ```php
   $defaultChain = function (string $requested, EntityInterface $entity): array {
       $entityDefault = $entity instanceof TranslatableInterface
           ? $entity->defaultLangcode()
           : null;
       $siteDefault = $config['locale'] ?? 'en';
       return array_filter([$requested, $entityDefault, $siteDefault, 'en']);
   };
   ```
2. Wire the resolver into the entity-storage container.

**Files:** Service-provider bootstrap (~30 lines).

### T030 — Wire trait `resolveFieldLangcode()` to consult resolver

**Steps:**

1. WP01's `TranslatableEntityTrait` has a `$fieldLangcodes` map and per-instance state. Extend it (in this WP) with a hook:
   ```php
   protected ?FallbackChainResolver $fallbackResolver = null;

   /** @internal */
   public function _setFallbackResolver(FallbackChainResolver $resolver): void
   {
       $this->fallbackResolver = $resolver;
   }
   ```
2. The trait's existing `get($fieldName)` override (which extends `EntityBase::get()`):
   - For non-translatable fields: existing behaviour.
   - For translatable fields:
     ```php
     if ($this->fallbackResolver === null) {
         $value = $this->loadFieldValue($fieldName, $this->activeLangcode());
         $this->fieldLangcodes[$fieldName] = $value !== null ? $this->activeLangcode() : null;
         return $value;
     }
     foreach ($this->fallbackResolver->resolve($this->activeLangcode(), $this) as $lc) {
         $value = $this->loadFieldValue($fieldName, $lc);
         if ($value !== null) {
             $this->fieldLangcodes[$fieldName] = $lc;
             return $value;
         }
     }
     $this->fieldLangcodes[$fieldName] = null;
     return null;
     ```

**Files:** `packages/entity/src/TranslatableEntityTrait.php` (modify, ~50 lines added).

Note: this is a modification to a WP01-owned file. The dependency declaration (`WP06 depends on WP04`/WP05`) ensures WP01 has merged. The shared file ownership is acceptable because WP06 lands after WP01 in the lane sequence. Document the cross-WP touch in the WP14 reconciliation note.

### T031 — Per-instance `fieldLangcode` tracking

The trait already has `$fieldLangcodes` from WP01. T030's code populates it. The `fieldLangcode($fieldName): ?string` getter from WP01 returns from it.

**Verification:** Reading the same translatable field twice MUST not regenerate the chain — cache the resolved langcode in `$fieldLangcodes` and short-circuit subsequent reads.

**Note:** T032 (`EntityRepository` LanguageManager constructor) and T033 (`find()` active-language wire-up) ship in WP10. WP06 produces the resolver class consumed there.

## Tests for this WP

- `FallbackChainResolver`: default chain de-duplicates; chain length > 8 throws `InvalidConfigurationException::fallbackChainTooLong()`.
- Trait field-read with fallback: missing 'fr' value falls through chain `['fr', 'en']` → returns 'en' value; `fieldLangcode('title') === 'en'`.
- Fallback exhaustion: missing in all chain langcodes → `null` + `fieldLangcode($field) === null`.
- Repeat-read short-circuit: reading the same translatable field twice does not invoke the resolver twice (cache via `$fieldLangcodes`).

Test files live under `packages/entity/tests/Unit/Hydration/`.

## Definition of Done

- [ ] `FallbackChainResolver` class shipped with bounded chain (NFR-002).
- [ ] `InvalidConfigurationException::fallbackChainTooLong()` factory.
- [ ] `TranslatableEntityTrait` reads translatable fields through the resolver when present.
- [ ] `fieldLangcode($fieldName)` returns the resolved langcode (or null on exhaustion).
- [ ] All tests pass.
- [ ] `composer phpstan`, `composer cs-check`, `bin/check-package-layers` green.

## Risks

| Risk | Mitigation |
|---|---|
| Cross-WP file touch on `TranslatableEntityTrait.php`. | Documented; WP06 strictly extends behavior added in WP01 (no rewrites). Linear lane sequencing prevents merge conflict. |
| `LanguageManager` is an L0 service consumed from L1 (entity-storage); is this an upward dep? No — L0 → L1 is allowed direction; L1 consuming L0 is downward. | Verify `bin/check-package-layers` green. |
| The default chain function references `$config['locale']` which may not exist in test contexts. | Default to `'en'` when null. Tests pass an explicit chain function. |

## Reviewer guidance

- Verify `FallbackChainResolver::resolve()` yields entries (lazy), doesn't materialize array.
- Verify field-langcode caching: second read of same field doesn't re-walk the chain.
- Verify `readActiveLanguage` defaults to `false` (opt-in).

## Implementation command

```bash
spec-kitty agent action implement WP06 --agent <name>
```

## Activity Log

- 2026-05-12T23:07:20Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=571501 – Started implementation via action command
- 2026-05-12T23:15:04Z – claude:opus:waaseyaa-implementer:implementer – shell_pid=571501 – FallbackChainResolver + trait fallback wiring; bounded chain (NFR-002); fieldLangcode cache
- 2026-05-12T23:15:38Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=578760 – Started review via action command
- 2026-05-12T23:18:38Z – claude:opus:waaseyaa-reviewer:reviewer – shell_pid=578760 – WP06 approved: FallbackChainResolver (NFR-002 bounded, lazy generator), withDefaultChain factory as T029 substitute (no EntityServiceProvider in entity package — defensible), trait fallback wiring with fieldLangcode cache and repeat-read short-circuit, NFR-001 non-translatable invariant preserved. 15 new tests pass. Gates green; full suite 46e/1f matches baseline.
