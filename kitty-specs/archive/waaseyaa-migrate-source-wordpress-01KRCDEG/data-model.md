# Data Model — `waaseyaa-migrate-source-wordpress` (M-005)

**Status:** Phase 0 research, 2026-05-14
**Mission spec:** [`spec.md`](spec.md)

This document describes the **source-side** data model: the shape of `SourceRecord` instances yielded by each WordPress source plugin, and the `migration_id_map` reference flow between them. The destination side (Waaseyaa entity types) is consumer-defined and not specified here.

---

## 1. Source entity types

The five source plugins map 1:1 to WP's logical entity types. Each yields a `Waaseyaa\Migration\SourceRecord` (M-002) with the fields below.

### 1.1 User (`WordPressUserSource` → FR-006)

WXR location: `<wp:author>` elements (top-level under `<channel>`).

| Field | Type | WXR source | Notes |
|---|---|---|---|
| `id` | `int` | `<wp:author_id>` | WP's internal user id; basis for `SourceId` hash |
| `login` | `string` | `<wp:author_login>` | Maps to destination `username` by default |
| `email` | `string` | `<wp:author_email>` | Required by WP; may be empty for legacy exports |
| `display_name` | `string` | `<wp:author_display_name>` | Falls back to login if absent |
| `first_name` | `?string` | `<wp:author_first_name>` | May be empty |
| `last_name` | `?string` | `<wp:author_last_name>` | May be empty |
| `registered` | `?string` (ISO 8601) | `<wp:author_registered_date>` | Optional; only present in WXR 1.2+ |
| `role` | `string` | `<wp:author_role>` | `administrator`/`editor`/`author`/`contributor`/`subscriber` |
| `_extra` | `array<string,mixed>` | unmapped namespaced attrs | Preserves plugin-injected data |

### 1.2 Term (`WordPressTaxonomySource` → FR-009)

WXR location: `<wp:category>`, `<wp:tag>`, `<wp:term>` elements.

| Field | Type | WXR source | Notes |
|---|---|---|---|
| `id` | `int` | `<wp:term_id>` (or implicit for legacy `<wp:category>`) | Basis for `SourceId` hash |
| `taxonomy_name` | `string` | `<wp:category_taxonomy>` or fixed (`category`/`post_tag`) | Drives downstream filtering |
| `name` | `string` | `<wp:cat_name>` / `<wp:tag_name>` / `<wp:term_name>` | Display label |
| `slug` | `string` | `<wp:category_nicename>` / `<wp:tag_slug>` / `<wp:term_slug>` | URL-safe identifier |
| `description` | `?string` | `<wp:category_description>` / `<wp:term_description>` | Optional |
| `parent_slug` | `?string` | `<wp:category_parent>` / `<wp:term_parent>` | For hierarchical terms; resolved via lookup |
| `post_count` | `?int` | derived (post-pass count) | Not authoritative; informational |
| `_extra` | `array<string,mixed>` | unmapped namespaced attrs | |

**Cross-migration reference**: `parent_slug` resolves via `LookupProcessor` against the same migration (`wp_terms_to_taxonomy` → itself).

### 1.3 Media (`WordPressMediaSource` → FR-008)

WXR location: `<item>` elements where `<wp:post_type>attachment</wp:post_type>`.

| Field | Type | WXR source | Notes |
|---|---|---|---|
| `id` | `int` | `<wp:post_id>` | WP attachment post id; basis for `SourceId` hash |
| `file_path` | `string` | `<wp:attachment_url>` minus host prefix | Relative to `wp-content/uploads/` |
| `mime_type` | `string` | `<wp:postmeta>` key `_wp_attached_file` extension lookup | Falls back to `application/octet-stream` |
| `alt_text` | `?string` | `<wp:postmeta>` key `_wp_attachment_image_alt` | Empty for non-image attachments |
| `caption` | `?string` | `<excerpt:encoded>` | Optional |
| `description` | `?string` | `<content:encoded>` | Optional |
| `parent_post_id` | `?int` | `<wp:post_parent>` | Resolved via lookup to imported post (best-effort if media imported before posts) |
| `original_url` | `string` | `<wp:attachment_url>` | Full URL; used by `WordPressMediaRewriteUrl` |
| `size_bytes` | `?int` | filesystem stat (post-fetch) | For idempotency check (FR-026) |
| `_extra` | `array<string,mixed>` | unmapped postmeta | |

**Cross-migration reference**: `parent_post_id` resolves to `wp_posts_to_<entity_type>`. Resolution is best-effort — media imported before posts gets `null` (FR-033).

### 1.4 Post (`WordPressPostSource` → FR-005)

WXR location: `<item>` elements where `<wp:post_type> != attachment`.

| Field | Type | WXR source | Notes |
|---|---|---|---|
| `id` | `int` | `<wp:post_id>` | WP post id; basis for `SourceId` hash |
| `post_type` | `string` | `<wp:post_type>` | `post`/`page`/CPT slug; consumers filter on this |
| `title` | `string` | `<title>` | Plain text |
| `slug` | `string` | `<wp:post_name>` | URL-safe identifier |
| `content` | `string` | `<content:encoded>` | HTML; processed by `WordPressShortcodeStrip`, `WordPressOembedExpand`, `WordPressMediaRewriteUrl` |
| `excerpt` | `?string` | `<excerpt:encoded>` | Optional |
| `status` | `string` | `<wp:status>` | `publish`/`draft`/`pending`/`private`/`trash` |
| `published_at` | `string` (ISO 8601) | `<wp:post_date_gmt>` | UTC; falls back to `<wp:post_date>` (local) if absent |
| `modified_at` | `?string` (ISO 8601) | `<wp:post_modified_gmt>` | Optional |
| `author_login` | `string` | `<dc:creator>` | Resolved via lookup to imported user |
| `parent_id` | `?int` | `<wp:post_parent>` | For pages with parents; resolved via self-lookup |
| `terms` | `array<{taxonomy: string, slug: string}>` | `<category domain="..." nicename="...">` | Resolved via lookup to imported terms |
| `comment_status` | `string` | `<wp:comment_status>` | `open`/`closed` |
| `password` | `?string` | `<wp:post_password>` | Empty for public posts |
| `_extra` | `array<string,mixed>` | unmapped postmeta + namespaced elements | |

**Cross-migration references**: `author_login` → `wp_users_to_accounts`; `terms[].slug` → `wp_terms_to_taxonomy`; `parent_id` → self.

### 1.5 Comment (`WordPressCommentSource` → FR-007)

WXR location: nested `<wp:comment>` under `<item>` elements.

| Field | Type | WXR source | Notes |
|---|---|---|---|
| `id` | `int` | `<wp:comment_id>` | WP comment id; basis for `SourceId` hash |
| `post_id` | `int` | parent `<item>`'s `<wp:post_id>` | Resolved via lookup to imported post |
| `parent_id` | `?int` | `<wp:comment_parent>` | 0 → null; otherwise self-lookup (FR-031) |
| `author` | `string` | `<wp:comment_author>` | Display name; may be guest |
| `author_email` | `?string` | `<wp:comment_author_email>` | Optional |
| `author_url` | `?string` | `<wp:comment_author_url>` | Optional |
| `author_ip` | `?string` | `<wp:comment_author_IP>` | Optional; PII consideration documented in WP10 |
| `content` | `string` | `<wp:comment_content>` | Plain text or HTML depending on WP config |
| `published_at` | `string` (ISO 8601) | `<wp:comment_date_gmt>` | UTC |
| `approved` | `bool` | `<wp:comment_approved>` (1/0/spam/trash) | Maps to a moderation state |
| `comment_type` | `string` | `<wp:comment_type>` | `''`/`pingback`/`trackback` |
| `user_login` | `?string` | `<wp:comment_user_id>` → user lookup | Set if commenter was a registered WP user |
| `_extra` | `array<string,mixed>` | unmapped namespaced elements | |

**Cross-migration references**: `post_id` → `wp_posts_to_<entity_type>`; `parent_id` → self; `user_login` → `wp_users_to_accounts`.

---

## 2. SourceId derivation (FR-010)

Each source's `sourceIdFor(SourceRecord): SourceId` produces a deterministic hash per M-002 FR-027:

```
SourceId = sha256(canonical_json({
    "type": "<entity_type>",     // e.g. "wp_user", "wp_post"
    "id": <wp_internal_id>,
}))
```

The `<entity_type>` component prevents id collisions across types (e.g., user 1 and post 1 both have WP id 1 but distinct types).

---

## 3. Cross-migration reference graph

```
                    ┌──────────────────────┐
                    │ wp_users_to_accounts │
                    └──────────┬───────────┘
                               │ resolves: dc:creator → user
                               ▼
┌──────────────────────┐  ┌──────────────────────────────┐
│ wp_terms_to_taxonomy │──│ wp_posts_to_<entity_type>    │
│ (self-lookup parent) │  │ (terms, parent, author)      │
└──────────────────────┘  └──────────┬───────────────────┘
                                     │ resolves: post_parent → post
                                     ▼
                          ┌──────────────────────┐
                          │ wp_comments_to_      │
                          │   engagement         │
                          │ (post, parent, user) │
                          └──────────────────────┘

         ┌───────────────────────┐
         │ wp_media_to_entities  │ ◄── independent; post_parent is best-effort
         └───────────────────────┘
```

**Strict ordering** (declared via `MigrationDefinition::dependencies`):
1. `wp_users_to_accounts`
2. `wp_terms_to_taxonomy`
3. `wp_media_to_entities`
4. `wp_posts_to_<entity_type>`
5. `wp_comments_to_engagement`

**Process plugins** that depend on cross-migration lookups:
- `WordPressMediaRewriteUrl` consults `wp_media_to_entities`'s id-map to rewrite `wp-content/uploads/...` URLs in post content to imported media UUIDs.

---

## 4. Destination contract (informational)

The destination entity types are NOT specified by this package — consumers register their own destination via M-002's `EntityDestination` (or a custom `DestinationPluginInterface` implementation). The shipped default migrations assume:

| Migration | Default destination | Consumer can override |
|---|---|---|
| `wp_users_to_accounts` | Consumer's "account" entity (whatever's marked as the principal user-like type) | Yes — via override `MigrationDefinition` |
| `wp_terms_to_taxonomy` | Consumer's "taxonomy" entity | Yes |
| `wp_media_to_entities` | Consumer's "media" entity | Yes |
| `wp_posts_to_articles` | Consumer's "article" entity (example only) | Expected — consumers rename to their CPT |
| `wp_comments_to_engagement` | Consumer's "engagement" or "comment" entity | Yes; may skip if absent |

---

## 5. Open data-model questions

(Tracked alongside the open questions in [`research.md`](research.md) §2.)

- **Q1** (research §2): WP attachment metadata location — `<wp:meta>` vs `<content:encoded>`. Affects `Media._extra` field shape. **Resolution: WP02.**
- **Q5** (research §2): WP postmeta serialization format (`serialize()`-d PHP arrays). Affects whether `Post._extra` carries decoded structures or opaque strings. **Resolution: deferred to v1.1.** v1 carries raw strings.
