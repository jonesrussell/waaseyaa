---
work_package_id: "WP02"
title: "PHPStan rule wiring (skeleton)"
dependencies: ["WP01"]
planning_base_branch: "main"
merge_target_branch: "main"
branch_strategy: "Planning artifacts were generated on main; completed changes must merge back into main."
subtasks:
  - "T004"
  - "T005"
  - "T006"
  - "T007"
  - "T008"
phase: "Phase 1 - Foundations"
assignee: ""
agent: ""
shell_pid: ""
authoritative_surface: "packages/entity/src/PhpStan/FieldAttributeRule"
execution_mode: "code_change"
mission_id: "01KQ6XW7Y3QD0JJ7JTP9JCSDPM"
mission_slug: "attribute-first-entity-static-analysis-01KQ6XW7"
owned_files:
  - "packages/entity/composer.json"
  - "packages/entity/src/PhpStan/FieldAttributeRule.php"
  - "packages/entity/phpstan-rules.neon"
  - "phpstan.neon"
tags: []
history:
  - timestamp: "2026-04-27T07:42:00Z"
    agent: "system"
    action: "Prompt generated via /spec-kitty.tasks"
---

# Work Package Prompt: WP02 — PHPStan rule wiring (skeleton)

## Branch Strategy

- **Planning base**: `main`.
- **Merge target**: `main`.
- **Execution worktree**: lane-allocated post `finalize-tasks`.

## Objective

Land the empty-but-registered `FieldAttributeRule` so subsequent WPs (WP03..WP07)
add detection logic to a class that already runs in CI. No detection rules
implemented in this WP — `processNode()` returns `[]`. Verifies plumbing.

## Subtask Guidance

### T004 — composer dev dependency

Edit `packages/entity/composer.json`. In `require-dev`, add (alongside the
existing phpunit pin):

```json
"phpstan/phpstan": "^1.11"
```

Use whatever PHPStan version is already in the monorepo lockfile (check
repo-root `composer.lock`). Run `composer update --lock packages/entity` if
necessary.

### T005 — Rule skeleton

Create `packages/entity/src/PhpStan/FieldAttributeRule.php`:

```php
<?php
declare(strict_types=1);

namespace Waaseyaa\Entity\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Lints #[Field] attribute usage. Mirrors FieldTypeInferrer::infer()'s
 * checks so that misuses surface in static analysis instead of at kernel boot.
 *
 * @implements Rule<Node\Stmt\Property>
 */
final class FieldAttributeRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Property::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        return [];
    }
}
```

(WP03..WP07 fill in `processNode`.)

### T006 — Rule registration neon

Create `packages/entity/phpstan-rules.neon`:

```neon
services:
    -
        class: Waaseyaa\Entity\PhpStan\FieldAttributeRule
        tags:
            - phpstan.rules.rule
```

### T007 — Wire into root config

Edit repo-root `phpstan.neon`. Append to the existing `includes:` block:

```neon
    - packages/entity/phpstan-rules.neon
```

### T008 — Verify green

Run `vendor/bin/phpstan analyse --no-progress`. Expected: same result as
before this WP (empty rule emits no errors).

If the analysis discovers PHPStan can't autoload `FieldAttributeRule`, add a
`scanFiles:` or `scanDirectories:` entry in `packages/entity/phpstan-rules.neon`
pointing at `packages/entity/src/PhpStan`, OR confirm `composer dump-autoload`
picks up the new namespace via the existing PSR-4 mapping (it should — the
package's autoload root is `Waaseyaa\Entity\` over `src/`).

## Files

- `packages/entity/composer.json`
- `packages/entity/src/PhpStan/FieldAttributeRule.php` (new)
- `packages/entity/phpstan-rules.neon` (new)
- `phpstan.neon`

## Validation

- [ ] `vendor/bin/phpstan analyse --no-progress` exits 0.
- [ ] No new errors introduced (phpstan-baseline.neon unchanged).
- [ ] Rule class is reachable from PHPStan (verify by temporarily having `processNode` return one fake error and confirming PHPStan reports it; revert before commit).
