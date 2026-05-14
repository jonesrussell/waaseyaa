# Research — `waaseyaa-migrate-source-wordpress` (M-005)

**Status:** Phase 0 research, 2026-05-14
**Mission spec:** [`spec.md`](spec.md)
**Substrate:** [M-002 migration-platform-v1](../migration-platform-v1-01KRCDE9/spec.md), shipped in `waaseyaa/migration` alpha.179
**Origin ADR:** [ADR 012a](../../docs/adr/012a-migration-substrate-in-core.md)

---

## 1. Key technical decisions

### 1.1 WXR parser: streaming pull-parser via PHP `XMLReader`

**Decision.** Use PHP's built-in `XMLReader` (libxml-backed, streaming pull-parser) for `WxrReader`. Yield one `SourceRecord` per top-level WXR `<item>` (or `<wp:author>`, `<wp:term>`) without buffering the document.

**Rationale.**
- WXR exports of medium-large sites routinely exceed 100 MB; `SimpleXML` and `DOMDocument` load the entire tree into memory.
- `XMLReader::expand()` lets us materialize a `SimpleXMLElement` for a single item when convenient — pull-parsing for navigation, DOM-style for field extraction. Best of both.
- `libxml_use_internal_errors(true)` + per-record `libxml_get_errors()` enables the FR-003 graceful-recovery contract.

**Alternatives rejected.**
- `XMLParser` (SAX): callback-based, harder to compose with iterator-yielding source plugins.
- `Symfony\Component\Serializer` XML decoder: eager, intended for small payloads.

**Evidence.** WordPress core's own `WXR_Parser` (in `wp-admin/includes/class-wp-importer.php`) uses XMLReader for sites > some threshold; smaller sites fall back to SimpleXML. We standardize on XMLReader unconditionally — predictable memory profile beats slight speedup on tiny inputs.

### 1.2 WXR version support: 1.0, 1.1, 1.2

**Decision.** Detect the `<wxr:wxr_version>` element on document open; reject pre-1.0 and post-1.2. Treat 1.0 → 1.2 as additive (newer fields tolerated, missing fields default).

**Rationale.** WXR has been stable since WP 3.0 (2010, version 1.2). Real-world exports are overwhelmingly 1.2; 1.0/1.1 still appear in old archives and infrequent backups. Future WXR 2.x is a hypothetical we won't speculate on.

### 1.3 Recovery model: skip-with-warning by default

**Decision.** On per-item parse failure: log a warning to `migration.deprecation` and skip the record. `--strict` (passed via source config) escalates to `WxrParseException`.

**Rationale.** WP exports from plugin-heavy sites have malformed entries with surprising frequency (broken oEmbeds, truncated serialized PHP, NUL bytes). Default behavior should let migrations finish, with the operator-facing summary surfacing skipped counts. Strict mode is for CI / golden-master runs.

### 1.4 Source plugin shape: one per WXR entity section

**Decision.** Five concrete source plugins matching WXR's natural sectioning: `WordPressPostSource`, `WordPressUserSource`, `WordPressCommentSource`, `WordPressMediaSource`, `WordPressTaxonomySource`.

**Rationale.**
- Aligns 1:1 with M-002's per-source model (one source = one iteration over one entity type).
- Matches WXR's grouping (`<wp:author>`, `<wp:category>` / `<wp:tag>`, `<item>` with `<wp:post_type>` discriminator).
- Keeps `sourceIdFor()` deterministic per entity type (FR-010).

**Custom-post-type variants** are NOT separate plugins (FR-005 confirms). `WordPressPostSource` yields all post types; downstream `MigrationDefinition` process maps filter by `post_type` field. This avoids combinatorial source explosion (sites with 20+ CPTs would otherwise need 20+ source plugins).

### 1.5 Media copy: idempotent via target-existence check

**Decision.** Before copying a media file, check if the target path exists. If yes and size matches, no-op. If yes and size differs, treat as "needs update" — replace. If absent, stream-copy from source.

**Rationale.**
- M-002 guarantees `migration_id_map` deduplication for entity records, but media files live outside the substrate's purview — idempotency is the source plugin's responsibility.
- Size match is fast; hash verification is FR-029 SHOULD (not MUST) and only when WXR exposes the hash (varies by WP version + plugins).
- Replace-on-size-mismatch handles the case where the operator re-exports WP after editing media (legitimate update).

**Open subquestion**: do we hard-fail on size-mismatch or treat as update? Current call: update silently with a warning log entry, surfacing in the per-record summary. Operators can opt into hard-fail via `--halt-on-error` (M-002 FR-046 framework).

### 1.6 oEmbed resolution: opt-in remote calls

**Decision.** `WordPressOembedExpand` records oEmbed URLs as found by default. Resolution to provider metadata (YouTube title, Vimeo dimensions, etc.) is opt-in via a `resolve_remote: true` constructor option.

**Rationale.** Resolves spec §7 question 4. Remote calls during migration:
- Slow the import (each oEmbed is a network round-trip).
- Surprise operators in air-gapped or rate-limited environments.
- Leak migration-time IPs to oEmbed providers.

Default-off is the safe choice. The expanded-metadata workflow is for operators who explicitly want it (typically: post-migration polish run on a connected staging environment).

### 1.7 Password preservation: never, by design

**Decision.** Imported users land with a randomized password and a flag forcing reset on first login. WP password hashes (bcrypt with WP-specific cost) are discarded.

**Rationale.** Resolves spec §7 question 7.
- WP uses bcrypt with cost 8 (or PHPass legacy hashes for older sites). Waaseyaa may use different hashing parameters; importing the hash means baking WP's choice into the consumer.
- Even if hashing aligns, importing the hash without the corresponding session-token salt is incomplete.
- Forcing first-login reset is a clean-slate user experience and avoids a class of issues we can't predict.

A future "password bridge" mission (validates WP bcrypt at first login, re-hashes for Waaseyaa) is left as a separate optional package — not blocking.

### 1.8 Cross-migration ID resolution: substrate `LookupProcessor`

**Decision.** All cross-migration references (`post_author` → user, `comment_parent` → comment, `post_parent` → post, `term_parent` → term) resolve via M-002's `LookupProcessor` against `migration_id_map`. Migration definitions declare dependencies via `MigrationDefinition::dependencies` to enforce ordering.

**Rationale.** This is exactly the substrate's job. We don't reimplement lookup. Standard ordering: users → taxonomies → media → posts → comments (FR-024).

### 1.9 Repository: standalone, outside the framework monorepo

**Decision.** Create `github.com/waaseyaa/migrate-source-wordpress` as a standalone public repo. Not in the framework monorepo, not in a hypothetical `waaseyaa/extensions` monorepo.

**Rationale.** Resolves spec §7 question 1.
- Separate release cadence: WP exporter changes don't gate framework releases (and vice versa).
- Clear ownership: one package, one repo, one Packagist registration.
- Avoids the split.yml fan-out gotchas the framework recently hit (alpha.178 incident — see [feedback_new_package_release_checklist.md](../../../.claude/projects/-home-jones-dev-waaseyaa/memory/feedback_new_package_release_checklist.md)).
- Composer dependency is declarative: `"waaseyaa/migration": "^0.1.0-alpha.179"` (or whatever the substrate version is at release time).

### 1.10 Versioning: independent semver

**Decision.** Resolves spec §7 question 2. The package versions independently of the framework. Framework compatibility is declared via composer constraint (`waaseyaa/migration: ^X.Y`) and a `compatibility:` block in README.

**Rationale.** Coupling versions punishes both surfaces. Independent semver lets us cut a 1.0 of the WP reader against a 0.x framework once the WP-side surface is stable, without forcing a framework 1.0.

### 1.11 Default post-to-entity mapping: ship `wp_posts_to_articles` as documented example

**Decision.** Resolves spec §7 question 3. Ship `wp_posts_to_articles` as an `@api`-documented example with prominent "rename to your actual entity type" guidance. A future CLI generator (`bin/waaseyaa make:wp-import-migration <entity-type>`) is a follow-up.

**Rationale.** A working example is more usable than an abstract template. Consumers without an `articles` entity type rename the migration; consumers with one get a working default.

### 1.12 Comment threading: preserve raw `comment_parent`, no flattening

**Decision.** Resolves spec §7 question 8. The `wp_comments_to_engagement` migration preserves `comment_parent` as a literal field on the destination engagement record. Threading reconstruction is a downstream consumer concern.

**Rationale.** Lossy transformations during migration are hard to debug post-hoc. If the consumer's engagement entity supports threading, they wire it up. If not, the data's still there for future feature work.

---

## 2. Open questions (deferred or escalated)

| # | Question | Status |
|---|---|---|
| Q1 | Does WP attachment metadata (alt text, captions) live in `<wp:meta>` or in the attachment's `<content:encoded>`? | **TO VERIFY in WP02.** Both depending on WP version. WxrReader needs to extract from both and merge. |
| Q2 | Does `WordPressMediaRewriteUrl` need to handle CDN-prefixed `wp-content/uploads/` URLs (e.g., `https://cdn.example.com/wp-content/uploads/...`)? | **YES, in WP08.** Configurable host-allowlist on the process plugin. |
| Q3 | Should the operator guide include a Docker-based "try it locally" walkthrough? | **PUNT to WP10.** Out of scope for research; documentation team's call. |
| Q4 | What's the smoke-test fixture provenance? Real WP export or fabricated? | **TO DECIDE in WP09.** Recommend: a fabricated-but-realistic small site (5 posts, 2 users, 3 attachments, 4 comments, 6 terms) committed to the repo, plus an optional "bring your own export" CI matrix lane. |
| Q5 | How does the package handle WP serialized PHP arrays in postmeta (e.g., theme/plugin custom fields)? | **DEFER.** v1 ignores postmeta beyond what's mappable to first-class entity fields. Custom-postmeta handling is a v1.1 concern (likely needs a `WordPressPostMetaUnserialize` process plugin). |

---

## 3. Risks

1. **Real-world WXR variance.** Plugins (especially WooCommerce, Yoast SEO) inject custom XML namespaces and elements. We MUST handle these as opaque pass-through (preserve in `_extra` field) rather than failing. Validated in WP02.

2. **Memory leak in long-running imports.** Large sites take 30+ minutes; XMLReader is streaming but PHP's libxml has historical memory-bloat patterns under sustained pressure. Add periodic `gc_collect_cycles()` in the per-record loop and instrument peak memory in the WP09 validation harness.

3. **oEmbed link rot.** WP posts from 2015 reference YouTube videos that may now be private/deleted. Resolution-on-import will fail for these; the default opt-out behavior (record URL, no resolution) sidesteps the problem.

4. **Media copy bandwidth.** HTTP source path (FR-027) for sites where media isn't accessible locally — this is a 100s-of-gigabytes-class bandwidth ask. Document operator pre-flight: "rsync first, point source.media_path at the local copy."

5. **Substrate API drift.** This package depends on `waaseyaa/migration ^0.1.0-alpha.179`. Until the substrate hits 1.0, breaking changes upstream require coordinated package releases. Mitigation: lock compatibility ranges narrowly; bump aggressively when a substrate release drops.

---

## 4. References

See `research/source-register.csv` and `research/evidence-log.csv` for the full evidence trail.

Primary references:
- **WXR specification** — https://wordpress.org/documentation/article/wxr-files/ (canonical) and the community schema at https://github.com/pfefferle/wordpress-export-xml-schema.
- **M-002 substrate spec** — `kitty-specs/migration-platform-v1-01KRCDE9/spec.md` (FR-001..FR-064, especially the source-plugin contract FR-001..FR-014 and id-map contract FR-027..FR-035).
- **ADR 012a** — `docs/adr/012a-migration-substrate-in-core.md` (governing decision).
- **Drupal contrib `migrate_source_wordpress`** — https://www.drupal.org/project/migrate_source_wordpress (prior art; design heavily influenced by this).
- **WordPress core importer** — `wp-admin/includes/class-wp-importer.php` (XMLReader patterns, per-record recovery patterns).

---

## 5. Handoff to plan phase

**Ready signals:**
- Substrate (M-002) shipped as `waaseyaa/migration ^0.1.0-alpha.179` ✓
- Spec FRs are complete and tokenized (FR-001..FR-045) ✓
- WP decomposition documented in spec §5 (10 WPs, dependency graph) ✓
- Open questions resolved or scoped to specific WPs ✓

**Plan-phase priorities:**
1. Lock the standalone-repo provisioning checklist (composer skeleton, CI matrix, Packagist registration).
2. Sequence WP02 (parser) ahead of source plugins; WP02 unblocks five parallel WPs.
3. Decide implementer/reviewer assignment per WP. Default per spec §9: sonnet implements, opus reviews.
