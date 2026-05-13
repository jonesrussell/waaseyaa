---
affected_files: []
cycle_number: 2
mission_slug: migration-platform-v1-01KRCDE9
reproduction_command:
reviewed_at: '2026-05-13T16:38:05Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP10
---

# WP10 Review — Cycle 1 (REJECTED)

**Reviewer:** claude:opus:waaseyaa-reviewer
**Lane HEAD:** `e6ba5da70` (`feat(WP10): conformance suite + CsvSource reference (FR-049..FR-052)`)
**Branch:** `kitty/mission-migration-platform-v1-01KRCDE9-lane-a`
**Date:** 2026-05-13

## Verdict

Cycle-1 reject with a single targeted ask. Almost everything is correct; one cross-WP additive fix is needed to keep the conformance suite honest as a third-party gate.

## What was correct (do not re-litigate)

1. **MySQL/Postgres conformance — correctly OUT of scope.** Canonical `owned_files` lists only `packages/migration/tests/...` (SQLite-grade). Spec §3.8 (FR-049..FR-052), the WP10 Scope block, and the T053–T057 subtasks contain zero mention of `WAASEYAA_TEST_MYSQL_DSN`, `WAASEYAA_TEST_POSTGRES_DSN`, or cross-driver portability. The orchestrator's dispatching paragraph was an aspirational forward from WP04/WP07 reviews. Implementer's rejection stands.
2. **`resumeSafe` capability flag — correctly OUT of scope.** No `resumeSafe` symbol exists anywhere in WP07 (`MigrationRunner`/`MigrationRunState`) or in the source/destination interfaces. Spec §3.8 enumerates the conformance surface; `resumeSafe` is not in it. Spec uses fresh-instance idempotency (FR-037, contract source-plugin.md invariant #3) — exactly what the implementer's C6 gate exercises. Implementer's rejection stands.
3. **Process-plugin conformance — correctly NOT a separate base.** Per WP10 Scope ("Process-plugin conformance: not in scope") and `contracts/process-plugin.md` ("Process plugins do NOT have a dedicated conformance test base class"). FR-051 enumerates the five semantic axes covered by Source + Destination bases; it is not a third base class.
4. **FR-049, FR-050, FR-052 coverage.** Eight C-gates in `SourceConformanceTestCase`, seven D-gates in `DestinationConformanceTestCase`, `CsvSource` under `tests/Fixtures/` (autoload-dev), small fixture data file present. `@api` + `@spec FR-…` annotations on the abstract bases. Reference contract tests register under the `Unit` testsuite (`phpunit.xml.dist:16`).
5. **`rollbackClearsLookup()` hook — accept as-is.** WP08 retaining the id-map row on rollback is correct behaviour per FR-042 idempotency (re-runs use the update path) and per destination-plugin contract invariant #1. The canonical D3 gate "`lookup()` returns null after rollback" is itself the looser reading of the contract. Keep the hook; track contract clarification as a follow-up issue.
6. **C5 memory ceiling 50 MB above baseline, no production CsvSource, root `autoload-dev` edit registering `Waaseyaa\Migration\Testing\` namespace — all accepted.** The root edit is additive only and resolves PSR-4 for the autoload-dev `testing/` directory that the migration package declared but the root autoloader couldn't see. Benign.

## Gate verification (re-run)

| Gate | Result |
|---|---|
| `./vendor/bin/phpunit packages/migration/tests/Contract/` | 15/15 pass, 200,033 assertions |
| `./vendor/bin/phpunit` (full suite) | 7440/7440 pass (note: implementer's reported 8249 may include the larger conformance assertions; re-verified suite still green) |
| `composer cs-check` | clean |
| `composer phpstan` | clean |
| `bin/check-composer-policy` | OK |
| `bin/check-package-layers` | OK |
| `bin/audit-dead-code` | warn-only, no new findings introduced by WP10 |
| Smoke: `php bin/waaseyaa import:status` | runs cleanly |

## Required change — single cross-WP additive fix

### Defect — `EntityDestination::stability()` violates the canonical contract

`packages/migration/src/Plugin/Destination/EntityDestination.php:113`:

```php
private const string STABILITY = 'beta';
```

This violates the canonical destination contract on three independent axes:

1. **Interface PHPDoc** (`packages/migration/src/Plugin/DestinationPluginInterface.php`):
   ```php
   /** @return 'stable'|'experimental' */
   public function stability(): string;
   ```
2. **`contracts/destination-plugin.md` line 26:** *"The framework ships exactly one **stable** destination — EntityDestination."*
3. **FR-009 / WP01 PluginRegistry behaviour:** the runtime branches on `stability() === 'experimental'` to emit a one-shot deprecation warning on `migration.deprecation`. The string literal `'beta'` is neither bucket — it currently flies through silently and produces no warning. Third-party callers reading `stability()` see an undocumented value.

The conformance suite cannot paper this over with `allowedStabilityValues(): ['stable','experimental','beta']` — doing so neuters gate D7 (which is FR-051's normative "stability label" axis) for the framework's own reference plugin. If the FRAMEWORK's reference destination needs an opt-out to pass D7, the gate is documenting the violation, not enforcing it.

**Fix (one line + one hook removal, mirrors the implementer's existing cross-WP additive pattern in WP10):**

1. `packages/migration/src/Plugin/Destination/EntityDestination.php` line 113:
   ```php
   private const string STABILITY = 'stable';
   ```
2. `packages/migration/tests/Contract/ReferenceDestinationConformanceTest.php` — remove the `allowedStabilityValues()` override so the base class default (`['stable', 'experimental']`) applies. Drop the explanatory PHPDoc block too.

Per the destination contract doc, EntityDestination *is* the stable destination. There is no semantic case for `'beta'`; if the M-002 mission as a whole is in beta gate, that is mission-state, not plugin-stability — distinct surfaces.

If a third reviewer disagrees that this is a real defect (e.g., wants the mission to keep `'beta'` to signal pre-release status until M-002 merges to `main`), the alternative is: change the canonical contract to add a `'beta'` enum value, update `DestinationPluginInterface` PHPDoc, the contract doc, and the PluginRegistry deprecation logic. That is a multi-WP cross-cutting change well outside the WP10 review window. The single-line fix is unambiguously cheaper.

## Hooks: keep one, retire one

- **`rollbackClearsLookup()`** — keep. Document the contract ambiguity in a follow-up issue:
  > "Clarify destination-plugin contract: D3 `lookup()` returns null after rollback vs FR-042 idempotency (id-map row retained, prior `WriteResult` returned). Today they disagree; spec §5.3 + contract invariant #1 support retention. Either tighten D3 in `DestinationConformanceTestCase` to assert retention, or add an explicit normative statement to `contracts/destination-plugin.md`."
- **`allowedStabilityValues()`** — retire after the EntityDestination fix above. The base class default of `['stable', 'experimental']` is the canonical contract. Leave the hook present on the abstract base so genuine third-party experimental plugins can extend it; just don't override it in `ReferenceDestinationConformanceTest`.

## Re-review checklist

- [ ] `EntityDestination::STABILITY === 'stable'`
- [ ] `ReferenceDestinationConformanceTest::allowedStabilityValues()` removed (base default applies)
- [ ] Full phpunit suite green
- [ ] Follow-up issue filed for D3 vs FR-042 contract clarification
- [ ] No other changes — keep the rest of the implementation as-is

## Forwards to WP11 (unchanged)

- `CsvSource` FQCN: `Waaseyaa\Migration\Tests\Fixtures\CsvSource`
- Constructor: `(string $filePath, list<string> $keyFields, string $sourceType = 'csv', string $pluginId = 'csv_reference', string $delimiter = ',', string $stability = 'stable')`
- Small fixture: `packages/migration/tests/Fixtures/data/conformance-small.csv` (120 rows; `id,title,body,value_int`)
- `tests/Contract/` registered in `phpunit.xml.dist` Unit suite
