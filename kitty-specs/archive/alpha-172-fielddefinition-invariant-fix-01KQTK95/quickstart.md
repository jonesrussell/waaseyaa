# Quickstart: Alpha.172 FieldDefinition Invariant Fix

**Mission**: `01KQTK95TKYCKCFA1C07XE0CFM`
**Date**: 2026-05-04

---

## What this fix does

Restores the alpha.171 binding-invariant compliance for `FieldDefinition` construction in `GroupsServiceProvider` and `TaxonomyServiceProvider`. Without this fix, a clean install of `waaseyaa/groups` or `waaseyaa/taxonomy` at alpha.171 cannot boot.

## Prerequisites

- PHP 8.4+
- Composer
- Working clone of `waaseyaa` at `main` (or the mission worktree once `/spec-kitty.implement` opens it)
- Dependencies installed: `composer install`

## Reproduce the bug (alpha.171 baseline, before patch)

From repo root:

```bash
# Run the new provider unit tests — they should fail with InvalidArgumentException.
vendor/bin/phpunit packages/groups/tests/Unit/GroupsServiceProviderTest.php
vendor/bin/phpunit packages/taxonomy/tests/Unit/TaxonomyServiceProviderTest.php
```

Expected pre-patch output (excerpt):

```
InvalidArgumentException:
  Core field "description" declares targetEntityTypeId "" but is being registered against entity type "group_type".
```

## Apply the fix (WP02)

In each defect site, set `targetEntityTypeId` on the affected `FieldDefinition`:

- `packages/groups/src/GroupsServiceProvider.php:43` — add `targetEntityTypeId: 'group_type'`.
- `packages/taxonomy/src/TaxonomyServiceProvider.php:32` — add `targetEntityTypeId: 'taxonomy_vocabulary'`.
- `packages/taxonomy/src/TaxonomyServiceProvider.php:39` — add `targetEntityTypeId: 'taxonomy_vocabulary'`.

## Verify the fix

```bash
# 1. Provider unit tests now pass.
vendor/bin/phpunit packages/groups/tests/Unit/GroupsServiceProviderTest.php
vendor/bin/phpunit packages/taxonomy/tests/Unit/TaxonomyServiceProviderTest.php

# 2. Manifest-level invariant sweep — no defects across all providers.
vendor/bin/phpunit tests/Integration/PhaseN/FieldDefinitionInvariantTest.php

# 3. Registry exception contract still pinned.
vendor/bin/phpunit packages/field/tests/Unit/FieldDefinitionRegistryInvariantTest.php

# 4. Full suite green.
vendor/bin/phpunit

# 5. Static analysis + style + layer + composer policy gates.
composer cs-check
composer phpstan
bin/check-package-layers
bin/check-composer-policy

# 6. Spec freshness.
tools/drift-detector.sh
```

All commands MUST exit 0.

## Confirm release notes

```bash
head -30 CHANGELOG.md
```

The top entry must be `## [0.1.0-alpha.172] - <date>` with a `### Fixed` bullet referencing `(#1388)`.

## Done check

- [ ] Three provider call sites patched (`GroupsServiceProvider:43`, `TaxonomyServiceProvider:32`, `TaxonomyServiceProvider:39`).
- [ ] Three new tests added (provider unit × 2, manifest sweep, registry exception contract — counted as four files).
- [ ] Full PHPUnit suite green.
- [ ] All gates green.
- [ ] CHANGELOG updated.
- [ ] PR linked to `#1388`.
