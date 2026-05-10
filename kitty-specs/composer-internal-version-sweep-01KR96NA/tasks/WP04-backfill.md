---
work_package_id: WP04
title: 'Backfill: sync 56 manifests + composer.lock to current tag'
dependencies:
- WP01
- WP03
requirement_refs:
- FR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-composer-internal-version-sweep-01KR96NA
base_commit: 297934b3c3b3cfd9ce4ed32004e467aa73e8d78b
created_at: '2026-05-10T15:10:00+00:00'
subtasks:
- T040
- T041
- T042
- T043
history: []
authoritative_surface: composer.lock
execution_mode: code_change
owned_files:
- composer.lock
- packages/access/composer.json
- packages/admin-surface/composer.json
- packages/ai-agent/composer.json
- packages/ai-observability/composer.json
- packages/ai-pipeline/composer.json
- packages/ai-schema/composer.json
- packages/ai-vector/composer.json
- packages/api/composer.json
- packages/attachment/composer.json
- packages/auth/composer.json
- packages/billing/composer.json
- packages/bimaaji/composer.json
- packages/cli/composer.json
- packages/cms/composer.json
- packages/core/composer.json
- packages/debug/composer.json
- packages/engagement/composer.json
- packages/entity-storage/composer.json
- packages/entity/composer.json
- packages/error-handler/composer.json
- packages/field/composer.json
- packages/foundation/composer.json
- packages/full/composer.json
- packages/genealogy/composer.json
- packages/geo/composer.json
- packages/graphql/composer.json
- packages/groups/composer.json
- packages/inertia/composer.json
- packages/mail/composer.json
- packages/mcp/composer.json
- packages/media/composer.json
- packages/menu/composer.json
- packages/messaging/composer.json
- packages/node/composer.json
- packages/northcloud/composer.json
- packages/note/composer.json
- packages/notification/composer.json
- packages/oauth-provider/composer.json
- packages/oidc/composer.json
- packages/path/composer.json
- packages/plugin/composer.json
- packages/queue/composer.json
- packages/relationship/composer.json
- packages/routing/composer.json
- packages/scheduler/composer.json
- packages/search/composer.json
- packages/seo/composer.json
- packages/ssr/composer.json
- packages/state/composer.json
- packages/structured-import/composer.json
- packages/taxonomy/composer.json
- packages/telescope/composer.json
- packages/testing/composer.json
- packages/user/composer.json
- packages/validation/composer.json
- packages/workflows/composer.json
tags: []
agent: "sonnet"
shell_pid: "266283"
---

# WP04 — Backfill

Run `bin/sync-internal-versions <current-tag>` against the current state, regenerate `composer.lock`, single mechanical commit. Bring all 56 packages from `^0.1.0-alpha.150` to current. Verifies the WP01 helper and WP03 gate together.

## Success criteria

- 56 manifests modified; ~210 line edits.
- `composer.lock` regenerated.
- One commit: `chore(composer-policy): backfill internal version constraints to ^0.1.0-alpha.<NNN>` (adjust subject if a newer tag was cut between WP03 and WP04).
- Local: `bin/check-composer-policy` (incl. CP-NEW), `composer phpstan`, `vendor/bin/phpunit`, `composer cs-check` all green.
- Idempotency confirmed: re-running the sync produces no diff.

See `../plan.md` Design Decision D4 (single mechanical commit) and Risk R1 (lockfile diff size is expected).

## Activity Log

- 2026-05-10T15:45:57Z – sonnet – shell_pid=266283 – Started implementation via action command
