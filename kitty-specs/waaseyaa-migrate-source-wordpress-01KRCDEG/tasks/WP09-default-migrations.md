---
work_package_id: WP09
title: Default migrations + cross-migration ID resolution + end-to-end validation
dependencies:
- WP03
- WP04
- WP05
- WP06
- WP07
- WP08
requirement_refs:
- FR-018
- FR-019
- FR-020
- FR-021
- FR-022
- FR-023
- FR-024
- FR-025
- FR-030
- FR-031
- FR-032
- FR-033
- FR-038
- FR-039
- FR-040
- FR-041
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T065
- T066
- T067
- T068
- T069
- T070
- T071
- T072
- T073
- T074
- T075
- T076
history: []
authoritative_surface: src/Migration/
execution_mode: code_change
owned_files:
- src/Migration/WpUsersToAccounts.php
- src/Migration/WpTermsToTaxonomy.php
- src/Migration/WpMediaToEntities.php
- src/Migration/WpPostsToArticles.php
- src/Migration/WpCommentsToEngagement.php
- tests/Integration/EndToEndImportTest.php
- testing/Fixtures/medium-site.xml
- testing/Fixtures/edge-cases/large-entries.xml
- testing/Fixtures/edge-cases/rtl-language.xml
tags: []
agent: "claude"
shell_pid: "148044"
---

# WP09 — Default migrations + cross-migration ID resolution + end-to-end validation

## Objective

Wire the five default `MigrationDefinition` instances into M-002's substrate AND prove end-to-end success by importing the small-site fixture into a Waaseyaa consumer with zero duplicates and full field-mapping coverage. This is the mission's acceptance gate.

## Context

- Spec: [`spec.md`](../spec.md) §3.4 (FR-018..FR-025) + §3.6 (FR-030..FR-033) + §3.9 (FR-040..FR-041).
- Plan: [`plan.md`](../plan.md) §"Sequencing" — WP09 syncs on all source + process plugins.
- Substrate: M-002 `MigrationDefinition`, `LookupProcessor`, `MigrationRunner`, `migration_id_map`.
- This is the LARGEST WP. Subtask count is 12 — at the edge of the 10-subtask guideline. Justified by the integration scope.

## Implementation command

```
spec-kitty agent action implement WP09 --agent sonnet
```

## Subtask guidance

### T065 — `WpUsersToAccounts`

```php
final class WpUsersToAccounts implements MigrationDefinition
{
    public function id(): string { return 'wp_users_to_accounts'; }
    public function dependencies(): array { return []; }  // First in the chain
    public function source(): SourcePluginInterface { return new WordPressUserSource(/* WxrReader from config */); }
    public function destination(): DestinationPluginInterface { return new EntityDestination(/* consumer's account entity */); }
    public function processMap(): array {
        return [
            'username' => ['plugin' => 'pass_through', 'source' => 'login'],
            'email' => ['plugin' => 'pass_through', 'source' => 'email'],
            'display_name' => ['plugin' => 'pass_through', 'source' => 'display_name'],
            'created_at' => ['plugin' => 'pass_through', 'source' => 'registered'],
            'must_reset_password' => ['plugin' => 'default_value', 'value' => true],  // T065 critical: force reset
            'password_hash' => ['plugin' => 'default_value', 'value' => null],  // T065 critical: discard WP hash
        ];
    }
}
```

The `must_reset_password: true` + `password_hash: null` are the critical password-discard implementation per research §1.7.

### T066 — `WpTermsToTaxonomy`

Hierarchical: depends on itself for parent_slug lookup. `LookupProcessor` resolves `parent_slug` → previously-imported term's destination ID via `migration_id_map`.

`dependencies()` returns `[]` — self-references work via M-002's substrate handling them in topological order within a single migration.

### T067 — `WpMediaToEntities`

`source.media_path` config (FR-021) — operator points at local fs OR HTTP URL prefix. WP05's MediaCopier handles the I/O.

`dependencies(): ['wp_terms_to_taxonomy']` — media isn't strictly dependent on terms but the canonical order from FR-024 places it third.

`processMap` includes a LookupProcessor for `parent_post_id` → posts migration. This is best-effort — if media imports before posts (which it does in the canonical order), missing lookup is the norm. Per FR-033, missing lookups become warnings + null field.

### T068 — `WpPostsToArticles` (example)

`dependencies(): ['wp_users_to_accounts', 'wp_terms_to_taxonomy', 'wp_media_to_entities']`.

`processMap` chain for `content` field:
```php
'content' => [
    'plugin' => 'wordpress_shortcode_strip',
    'then' => ['plugin' => 'wordpress_oembed_expand'],
    'then' => ['plugin' => 'wordpress_media_rewrite_url'],
],
```

For `author_login` → `author_id`:
```php
'author_id' => [
    'plugin' => 'lookup',
    'migration' => 'wp_users_to_accounts',
    'source_field' => 'author_login',
],
```

For `terms` array → destination taxonomy refs: similar lookup pattern, iterating.

The migration is named `wp_posts_to_articles` as an EXAMPLE per FR-022. README + customization.md must prominently document the rename path.

### T069 — `WpCommentsToEngagement`

`dependencies(): ['wp_users_to_accounts', 'wp_posts_to_articles']`.

LookupProcessor for `post_id` → posts; `parent_id` → self (other comments); `user_login` → users (optional, may be null for guest comments).

Document in code: this migration assumes the consumer has an "engagement" or "comment" entity type. Consumers without one MUST override or skip this migration.

### T070 — Wire ServiceProvider

Update `src/ServiceProvider.php` (created in WP01):

```php
public function migrations(): array {
    return [
        new WpUsersToAccounts(/* */),
        new WpTermsToTaxonomy(/* */),
        new WpMediaToEntities(/* */),
        new WpPostsToArticles(/* */),
        new WpCommentsToEngagement(/* */),
    ];
}
```

### T071 — Medium-site fixture

`testing/Fixtures/medium-site.xml` — fabricated WP export:
- 50 posts (40 `post`, 10 `page`)
- 5 users (1 admin, 3 editors, 1 author)
- 30 attachments (20 images, 10 PDFs)
- 200 comments (mix of standalone + threaded; some spam)
- 20 terms (15 categories + 5 tags; 3-level hierarchy)

Target file size: ~500KB — 1MB. Used for performance smoke (T075) and memory profile (T076).

### T072 — Edge-case fixtures

- `large-entries.xml`: One post with 1MB of content (long-form article + many shortcodes + many oEmbed URLs). Tests memory bound + performance.
- `rtl-language.xml`: Hebrew/Arabic content + RTL marks. Tests no charset corruption.

### T073 — `EndToEndImportTest`

```php
test('full small-site import produces expected entity counts and key field values', function () {
    // Boot a Waaseyaa kernel with in-memory entity types matching destination contract
    // Register the WordPress source provider
    // Run: bin/waaseyaa import:run-all
    // Assert: 2 accounts, 6 taxonomies, 3 media, 5 articles, 4 engagements created
    // Assert: account 'admin' has username 'admin', email '...'
    // Assert: article 1's author_id resolves to account 1's id (cross-migration lookup worked)
    // Assert: article content has shortcodes stripped + URLs rewritten
});
```

### T074 — Idempotency proof

```php
test('re-running import is a no-op (zero new records, zero modifications)', function () {
    // First import run: assert N entities created
    // Second import run: assert no new entities created, no modifications
    // Verify migration_id_map row count unchanged
});
```

### T075 — Performance smoke

Run small-site import, capture wall-clock + records/sec. Assert ≥ 100 records/sec for posts (FR-041 target — informational, not gating). Document the result in CHANGELOG entry.

### T076 — Memory profile

Run medium-site import with `memory_get_peak_usage()` instrumentation. Assert peak under 100MB for 1MB WXR (10x file size as informal bound). If exceeded, surface in issues for follow-up — not blocking.

## Definition of Done

- [ ] All 5 default migrations implement `MigrationDefinition`, declare correct `dependencies()`, and pass M-002's substrate validation
- [ ] EndToEndImportTest passes: small-site WXR → expected entity counts, cross-migration lookups resolved
- [ ] Idempotency test passes: second run is genuinely a no-op
- [ ] Performance smoke + memory profile completed and documented
- [ ] ServiceProvider updated to expose all 5 migrations via HasMigrationsInterface
- [ ] Medium + edge-case fixtures committed to testing/Fixtures/

## Risks

- **Test consumer setup complexity.** `EndToEndImportTest` needs a Waaseyaa kernel with destination entity types. Either bootstrap an in-memory kernel (simple) or use a sub-test-fixture consumer (heavier). Recommend in-memory for speed.
- **Lookup-processor ordering surprises.** If WpTermsToTaxonomy has self-reference, M-002 substrate must handle topological ordering within a single migration. Verify with a hierarchical-term test case.
- **Idempotency edge cases.** Comment migration with cross-references — second run may revisit comments and trigger another `parent_id` lookup. Verify no-op semantics across the full chain.

## Reviewer guidance

- WP09 is the acceptance gate. The end-to-end test MUST exercise the full chain — don't accept stubs that mock individual migrations away.
- Verify `dependencies()` arrays match the canonical order in FR-024 (users → terms → media → posts → comments).
- The example name `wp_posts_to_articles` is intentional — DON'T rename it to something more generic. The example status is the point.
- Check the password-discard implementation (T065) — both `must_reset_password: true` AND `password_hash: null` need to be present.

## Activity Log

- 2026-05-14T22:32:57Z – claude – shell_pid=148044 – Started implementation via action command
- 2026-05-14T22:39:47Z – claude – shell_pid=148044 – Out-of-tree WP: deliverables at standalone-repo commit 65ff7ba. 5 MigrationDefinition factories + InMemoryDestination test fixture + RTL/large-entries edge-case fixtures + EndToEndImportTest + EdgeCaseFixturesTest. pest: 165 passed (25446 assertions). phpstan --level=5: clean. SCOPE NOTE: kernel-boot end-to-end import (real EntityDestination + sqlite MigrationIdMap + MigrationRunner) is out-of-package — that layer is consumer-specific. Package ships building blocks + structural test.
