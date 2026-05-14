---
work_package_id: WP10
title: Operator + developer documentation, README polish, first stable release
dependencies:
- WP09
requirement_refs:
- FR-042
- FR-043
- FR-044
- FR-045
planning_base_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
merge_target_branch: kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG
branch_strategy: Planning artifacts for this feature were generated on kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG unless the human explicitly redirects the landing branch.
subtasks:
- T077
- T078
- T079
- T080
- T081
- T082
- T083
history: []
authoritative_surface: docs/
execution_mode: code_change
owned_files:
- docs/migrating-from-wordpress.md
- docs/customization.md
- README.md
- CHANGELOG.md
- docs/upgrades/.gitkeep
- public-surface-map.md
tags: []
agent: "claude"
shell_pid: "149314"
---

# WP10 — Operator + developer documentation, README polish, first stable release

## Objective

Author the marketing-grade operator guide and developer guide, polish README, finalize public-surface-map, and cut the v0.1.0 release on the standalone repo.

## Context

- Spec: [`spec.md`](../spec.md) §3.10 (FR-042..FR-045) + §6 (acceptance criteria 5, 6, 7).
- Plan: [`plan.md`](../plan.md) §"Sequencing" — closes the mission after WP09.
- ADR 012a strategic claim: "Migrate your WordPress site to Waaseyaa in one command." This WP makes that claim CREDIBLE via the operator guide.

## Implementation command

```
spec-kitty agent action implement WP10 --agent sonnet
```

## Subtask guidance

### T077 — `docs/migrating-from-wordpress.md` (operator-facing)

Audience: WP site owners considering migration to Waaseyaa. NOT developers.

Outline:
1. **What you'll need** (5 min): WP admin access, basic command-line comfort, a Waaseyaa app installed
2. **Export your WP site** (5 min): Tools → Export → All content → save the .xml file
3. **Pre-flight: get the media files** (10 min): rsync wp-content/uploads/ to your Waaseyaa server, OR keep media at WP host (slower)
4. **Configure the migration** (10 min): edit the migration source path; choose your destination entity type
5. **Run it** (5–60 min depending on size): `bin/waaseyaa import:run-all`
6. **Verify** (10 min): browse the imported content; check users, posts, media
7. **Troubleshooting** (reference): common errors, where to look in logs
8. **What didn't migrate** (reference): theme, plugins, settings — these are framework-specific, not content
9. **Post-migration polish** (optional): users reset passwords, you might want to re-resolve oEmbeds (`resolve_remote: true`)

Tone: confident, friendly, NOT defensive. No hedging. Use real screenshots if available.

This MUST read as marketing-grade. If a WP site owner reads it and walks away thinking "I could do that this weekend" — succeed. If they read it and think "this is incomplete" or "this is for hardcore devs" — fail.

### T078 — `docs/customization.md` (developer-facing)

Audience: developers integrating the WordPress reader into their Waaseyaa app.

Topics:
- Overriding the default `MigrationDefinition` entries (e.g., `wp_posts_to_articles` → `wp_posts_to_blog_posts`)
- Adding custom shortcode handlers via `WordPressShortcodeStrip` constructor
- Configuring CDN host allowlist for `WordPressMediaRewriteUrl`
- Enabling oEmbed remote resolution (`resolve_remote: true`)
- Skipping a migration (e.g., consumer has no engagement entity)
- Custom postmeta handling (deferred to v1.1, but document the extension hook)
- Multisite workaround (per-site WXR exports per research §1.5 → spec §7 question 5)

### T079 — Polish README.md

Replace WP01's skeleton with the production version:
- Tagline up top
- 30-second quick start
- Compatibility matrix (waaseyaa/migration version → this package version)
- Install instructions verified on a clean machine (per T083)
- Links to operator + developer guides
- Links to CHANGELOG, public-surface-map, GitHub issues
- License footer

### T080 — CHANGELOG.md update

Promote `[Unreleased]` to `[0.1.0] - YYYY-MM-DD` with summary entries:

```markdown
## [0.1.0] - 2026-MM-DD

### Added

- Initial release of `waaseyaa-migrate-source-wordpress`.
- Streaming WXR XML parser (`WxrReader`) supporting WXR 1.0/1.1/1.2 with skip-with-warning recovery.
- Five source plugins: `WordPressPostSource`, `WordPressUserSource`, `WordPressCommentSource`, `WordPressMediaSource`, `WordPressTaxonomySource`.
- Three process plugins: `WordPressShortcodeStrip`, `WordPressOembedExpand` (opt-in remote resolution), `WordPressMediaRewriteUrl`.
- Five default `MigrationDefinition` entries: `wp_users_to_accounts`, `wp_terms_to_taxonomy`, `wp_media_to_entities`, `wp_posts_to_articles` (example), `wp_comments_to_engagement`.
- Idempotent media copy primitive (`MediaCopier`) with local + HTTP source support.
- Operator guide (`docs/migrating-from-wordpress.md`) and developer guide (`docs/customization.md`).
- Compatibility: requires `waaseyaa/migration ^0.1.0-alpha.179` (M-002 substrate).
```

### T081 — public-surface-map.md finalize

All entries from WP01..WP09 should be flipped to `present: true`. Verify against spec §4 stable surface table. Add stable-code constants for each exception class.

### T082 — Cut v0.1.0 release

```bash
git tag -a v0.1.0 -m "Release v0.1.0 — initial WordPress source reader"
git push origin v0.1.0
```

The `release.yml` workflow (built in WP01 T007) handles the Packagist webhook. Verify the release shows up at `https://packagist.org/packages/waaseyaa/migrate-source-wordpress` within ~5 minutes.

If the workflow fails, check that Packagist registration (WP01 T010) was completed.

### T083 — Verify install on clean machine

Use a Docker scratch container OR a fresh VM:

```bash
docker run --rm -it php:8.5-cli bash
apt update && apt install -y git unzip
curl -sS https://getcomposer.org/installer | php
composer require waaseyaa/migrate-source-wordpress
# Verify it installs without errors
# Run a quick smoke against a fixture WXR file
```

Document the procedure in README.md.

## Definition of Done

- [ ] `docs/migrating-from-wordpress.md` reads as marketing-grade walkthrough (subjective gate; confirm with at least one non-developer reader if possible)
- [ ] `docs/customization.md` covers all override patterns
- [ ] README install procedure verified on clean machine
- [ ] CHANGELOG.md [0.1.0] entry summarizes the package surface
- [ ] public-surface-map.md complete with all stable-surface entries marked `present: true`
- [ ] v0.1.0 tag pushed; GitHub Release created; Packagist shows the version
- [ ] Mission acceptance gates §6 all checked

## Risks

- **Operator-guide quality is subjective.** "Marketing-grade" is the bar; the only real test is a non-developer trying to follow it. If unable to test, ask Daisy/user for review.
- **Packagist webhook timing.** Sometimes the auto-update lags by ~10 minutes. Document the verification window.
- **Post-1.0 stability commitment.** Once v0.1.0 ships, breaking changes need upgrade-guide entries (FR-045). Document this in customization.md.

## Reviewer guidance

- READ the operator guide as if you were a WP site owner. If it has jargon you don't recognize OR steps that assume context, push back.
- Verify CHANGELOG entry covers EVERY stable-surface symbol.
- Check that the v0.1.0 tag matches the public-surface-map's documented surface (no missing or extra symbols).
- Verify the install procedure works — actually run it in a Docker container before approving.

## Activity Log

- 2026-05-14T22:39:59Z – claude – shell_pid=149314 – Started implementation via action command
