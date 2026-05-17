# Implementation Plan: WordPress Source Reader (M-005)

**Branch**: `kitty/mission-waaseyaa-migrate-source-wordpress-01KRCDEG` | **Date**: 2026-05-14 | **Spec**: [`spec.md`](spec.md) | **Research**: [`research.md`](research.md) | **Data model**: [`data-model.md`](data-model.md)

**Input**: Mission spec defining a separate-package WordPress importer atop the M-002 migration substrate.

---

## Summary

Ship `waaseyaa-migrate-source-wordpress` as a standalone composer package providing a streaming WXR parser, five source plugins (User/Term/Media/Post/Comment), three WP-specific process plugins (shortcode strip, oEmbed expand, media-URL rewrite), and five default migration definitions wired into `waaseyaa/migration` (M-002 substrate, alpha.179+).

Validated by importing a real WordPress site end-to-end into a Waaseyaa consumer with zero duplicates, zero data loss in the field-mapped surface, and idempotent re-runs.

---

## Technical Context

**Language/Version**: PHP 8.5+ (matches framework requirement; uses 8.5 idioms where applicable per [feedback_php_version](../../../.claude/projects/-home-jones-dev-waaseyaa/memory/feedback_php_version.md))
**Primary Dependencies**:
- `waaseyaa/migration ^0.1.0-alpha.179` — substrate (`SourcePluginInterface`, `ProcessPluginInterface`, `MigrationDefinition`, `LookupProcessor`, `migration_id_map` table)
- `waaseyaa/foundation ^0.1.0-alpha.179` — logging, file I/O abstractions
- PHP `ext-libxml`, `ext-xmlreader` (built-in; declared in composer.json)
- Optional: `psr/http-client` for HTTP media fetch + oEmbed resolution
**Storage**: M-002's `migration_id_map` (SQLite/MySQL/PostgreSQL via consumer's DB binding); per-record errors via M-002 telemetry surface
**Testing**:
- Unit (Pest v4 or PHPUnit 10.5 — match consumer convention; default Pest)
- Conformance via M-002's `SourceConformanceTestCase` (one per source plugin)
- Integration: WXR fixtures (small / medium / edge) → end-to-end migration → entity-count + key-field assertions
- Performance smoke: ≥100 records/sec for posts on commodity hardware (informational, not gating per FR-041)
**Target Platform**: PHP-FPM under Caddy (matches Waaseyaa deployment shape); CI on ubuntu-latest
**Project Type**: Single-package composer library (separate repo, NOT in framework monorepo per research §1.9)
**Performance Goals**: ≥100 records/sec post import (FR-041, informational); memory bounded for 100 MB+ WXR files (FR-001)
**Constraints**:
- Streaming-only XML parsing (no eager loads)
- Idempotent media copy (re-runs = no-op)
- All source plugins MUST pass `SourceConformanceTestCase` from M-002
- Cross-migration references resolve via M-002's `LookupProcessor` (no reimplementation)
**Scale/Scope**: Validated against a real medium-site WordPress export (target: 1000–10000 posts, 100s of attachments). Larger sites work but aren't gated by validation.

---

## Charter Check

This package ships outside the framework monorepo, so the framework's `stability-charter.md` doesn't directly apply. The package maintains its own stable surface per charter §5.8 extension-author obligations:

- **§4.4 (typed exceptions with stable string codes)**: ✓ planned (`WxrParseException`, `WordPressMediaCopyException`, `WordPressOembedResolutionException`)
- **§5.8 (substrate stable surface)**: ✓ consumed via `waaseyaa/migration ^0.1.0-alpha.179` semver constraint
- **Layer architecture (CLAUDE.md)**: N/A — package is not in monorepo; no layer enforcement applies
- **Composer policy (`bin/check-composer-policy`)**: N/A for the standalone repo; we apply equivalent local policy (no `@dev`, sort-packages: true)
- **Public-surface-map**: package maintains its own `public-surface-map.md` listing the §4 stable surface entries

**Gate result**: PASS. No charter violations to justify.

---

## Project Structure

### Documentation (this mission's spec dir)

```
kitty-specs/waaseyaa-migrate-source-wordpress-01KRCDEG/
├── plan.md              # This file
├── research.md          # Phase 0 — decisions, rationale (12 decisions)
├── data-model.md        # Phase 1 — source-record shapes, reference graph
├── research/
│   ├── source-register.csv
│   └── evidence-log.csv
├── spec.md              # Mission spec (FR-001..FR-045, 10 WPs)
├── tasks.md             # Phase 2 output (next: spec-kitty tasks)
├── tasks/               # WP01..WP10 detailed work-package specs (next: spec-kitty tasks)
└── meta.json            # Mission metadata
```

### Source Code (separate repo)

The standalone repo `github.com/waaseyaa/migrate-source-wordpress` will follow this layout:

```
waaseyaa-migrate-source-wordpress/
├── composer.json                    # waaseyaa-migrate-source-wordpress
├── README.md                        # Install + basic usage + links to guides
├── CHANGELOG.md                     # Keep-a-Changelog format
├── public-surface-map.md            # Stable surface listing per charter §5.8
├── src/
│   ├── Wxr/
│   │   ├── WxrReader.php            # WP02 — streaming XMLReader-backed parser
│   │   └── WxrVersion.php           # 1.0/1.1/1.2 enum
│   ├── Source/
│   │   ├── WordPressUserSource.php       # WP03
│   │   ├── WordPressTaxonomySource.php   # WP04
│   │   ├── WordPressMediaSource.php      # WP05
│   │   ├── WordPressPostSource.php       # WP06
│   │   └── WordPressCommentSource.php    # WP07
│   ├── Process/
│   │   ├── WordPressShortcodeStrip.php   # WP08
│   │   ├── WordPressOembedExpand.php     # WP08
│   │   └── WordPressMediaRewriteUrl.php  # WP08
│   ├── Migration/
│   │   ├── WpUsersToAccounts.php         # WP09
│   │   ├── WpTermsToTaxonomy.php         # WP09
│   │   ├── WpMediaToEntities.php         # WP09
│   │   ├── WpPostsToArticles.php         # WP09 (example)
│   │   └── WpCommentsToEngagement.php    # WP09
│   ├── Media/
│   │   ├── MediaCopier.php               # WP05 — local + HTTP source paths
│   │   └── MediaCopyResult.php           # WP05
│   ├── Exception/
│   │   ├── WxrParseException.php         # WP02
│   │   ├── WordPressMediaCopyException.php
│   │   └── WordPressOembedResolutionException.php
│   └── ServiceProvider.php               # WP01 — registers HasMigrationsInterface + HasMigrationPluginsInterface
├── testing/
│   └── Fixtures/
│       ├── small-site.xml                # WP09 fabricated test fixture
│       ├── medium-site.xml
│       └── edge-cases/
│           ├── malformed-entries.xml
│           ├── unicode.xml
│           ├── rtl-language.xml
│           └── plugin-namespaces.xml     # WooCommerce/Yoast custom XML
├── tests/
│   ├── Unit/
│   │   ├── Wxr/WxrReaderTest.php
│   │   ├── Source/{User,Taxonomy,Media,Post,Comment}SourceTest.php
│   │   ├── Process/{ShortcodeStrip,OembedExpand,MediaRewriteUrl}Test.php
│   │   └── Media/MediaCopierTest.php
│   ├── Conformance/                       # uses M-002's SourceConformanceTestCase
│   │   └── {User,Taxonomy,Media,Post,Comment}ConformanceTest.php
│   └── Integration/
│       └── EndToEndImportTest.php         # WP09 — full small-site import + idempotency
├── docs/
│   ├── migrating-from-wordpress.md        # WP10 — operator-facing marketing-grade guide
│   ├── customization.md                   # WP10 — developer-facing override guide
│   └── upgrades/                          # FR-045 — entries on breaking changes (post-1.0)
├── .github/
│   └── workflows/
│       ├── ci.yml                         # PHP test matrix (8.5+); lint; dead-code audit
│       └── release.yml                    # tag → Packagist webhook (after manual registration)
└── phpunit.xml                            # OR pest.config (decide WP01)
```

**Structure Decision**: Single composer-package layout (Option 1 from template, adapted). No backend/frontend split needed — this is a pure PHP library consumed by Waaseyaa apps.

---

## Sequencing & Critical Path

Per spec §5, with research-phase additions:

```
Pre-WP01 prerequisites (this session, or first WP01 session):
  - Provision github.com/waaseyaa/migrate-source-wordpress repo
  - Apply branch protection / CI requirements per framework convention
  - Decide on Pest vs PHPUnit (recommend Pest v4 to match Laravel ecosystem; framework uses PHPUnit but consumer apps use Pest)

WP01 — Package scaffold (~3-5h)
  - composer.json, README.md, CHANGELOG.md, src/ tree, basic ServiceProvider, CI skeleton
  - Acceptance: `composer install` + empty test suite passes in CI
  - Outputs: scaffolded repo, registered Packagist (manual one-time)

WP02 — WxrReader (~6-10h)
  - Streaming XMLReader pull-parser; version detection; recovery model; type discriminator
  - FR-001..FR-004
  - Acceptance: parses all small/medium fixtures; rejects pre-1.0/post-1.2; --strict flag works

WP03..WP07 — Five source plugins (parallelizable after WP02; ~4-6h each)
  - One source plugin per WP entity type (User/Taxonomy/Media/Post/Comment)
  - Each implements SourcePluginInterface; each has SourceConformanceTest
  - WP05 (Media) also delivers MediaCopier primitive (FR-026..FR-029)

WP08 — Three process plugins (~4-6h)
  - WordPressShortcodeStrip, WordPressOembedExpand, WordPressMediaRewriteUrl
  - Depends on WP05 + WP06 (process plugins consume those source records)

WP09 — Default migrations + cross-migration ID resolution + validation (~8-12h)
  - Five default MigrationDefinition entries
  - LookupProcessor wiring for cross-migration references
  - End-to-end integration test on small-site fixture
  - Idempotency proof (re-run is a no-op)
  - Performance smoke (informational)

WP10 — Documentation + first stable release (~4-6h)
  - migrating-from-wordpress.md (marketing-grade)
  - customization.md (developer-facing)
  - README.md polish
  - Cut v0.1.0 release on the standalone repo
```

**Critical path**: WP01 → WP02 → (WP03..WP07 parallel) → WP08 → WP09 → WP10
**Parallelization opportunity**: After WP02 completes, dispatch WP03/WP04/WP05/WP06/WP07 in parallel via the implement-review loop (5 lanes). WP08 syncs on WP05 + WP06. WP09 syncs on all source + process plugins.

**Estimated total**: 50–70 hours of focused work assuming sonnet-as-implementer + opus-as-reviewer per spec §9.

---

## Cross-Cutting Decisions (from research)

| Topic | Decision | Reference |
|---|---|---|
| XML parser | PHP `XMLReader` (streaming, libxml-backed) | research §1.1 |
| WXR versions | 1.0, 1.1, 1.2 | research §1.2 |
| Recovery model | Skip-with-warning by default; --strict opt-in | research §1.3 |
| Source plugin shape | 5 sources, one per WP entity type | research §1.4 |
| CPT handling | Single PostSource + downstream filter | research §1.4 |
| Media copy | Idempotent via target-existence + size check | research §1.5 |
| oEmbed | Opt-in remote resolution (`resolve_remote: false` default) | research §1.6 |
| Passwords | Discarded; force first-login reset | research §1.7 |
| Cross-migration IDs | M-002 LookupProcessor (no reimplementation) | research §1.8 |
| Repo location | Standalone (NOT in framework monorepo) | research §1.9 |
| Versioning | Independent semver; framework compat range in composer.json | research §1.10 |
| Default post mapping | `wp_posts_to_articles` as documented example | research §1.11 |
| Comment threading | Preserve raw `comment_parent`; no flattening | research §1.12 |

---

## Risks & Mitigations

| Risk | Mitigation | Owner WP |
|---|---|---|
| Real-world WXR plugin-namespace variance | Opaque pass-through into `_extra` field; never fail on unknown elements | WP02 |
| Memory leak in long imports | Periodic `gc_collect_cycles()` in record loop; instrument peak memory in WP09 harness | WP02, WP09 |
| oEmbed link rot | Default-off remote resolution; record URL as-is | WP08 |
| HTTP media-fetch bandwidth | Document operator pre-flight: rsync first, point at local copy | WP05, WP10 |
| Substrate API drift | Lock `^0.1.0-alpha.X` constraint narrowly; bump on substrate releases | WP01 |
| Standalone-repo provisioning forgotten | Document the provisioning checklist as part of WP01 acceptance | WP01 |

---

## Implementer / Reviewer Assignment

Per spec §9 mission metadata:
- **Implementer**: sonnet
- **Reviewer**: opus
- **Escalation after 2 rejections**: opus-as-implementer
- **Pattern**: Standard Spec Kitty implement-review loop per [`spec-kitty-implement-review` skill]

WPs eligible for parallel dispatch (after WP02): WP03, WP04, WP05, WP06, WP07. Use 5-lane parallel implementation when available.

---

## Acceptance (mirrors spec §6)

The mission is complete when:

1. All 10 WPs are merged.
2. All FRs in spec §3 are covered by tests.
3. WP09's real-site (or fabricated-realistic) import test passes in CI: end-to-end import produces expected entity counts and zero duplicates.
4. Idempotency proven: re-running the import is a no-op.
5. Package published to Packagist as `waaseyaa-migrate-source-wordpress` (separate from the framework repo's CI).
6. Operator documentation (`docs/migrating-from-wordpress.md`) reads as a marketing-grade walkthrough — first-impression-quality.
7. README links to both operator and developer guides; installation steps verified on a clean machine.

---

## Complexity Tracking

No charter violations to justify (see Charter Check above).

---

## Next Phase

Run `spec-kitty tasks --mission waaseyaa-migrate-source-wordpress-01KRCDEG` to materialize WP01..WP10 detail files under `tasks/`. Each WP detail will inherit FR coverage from spec §5 and the sequencing above.
