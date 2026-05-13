# waaseyaa/search

**Layer 3 — Services**

Full-text and structured search for Waaseyaa applications.

Provides a search index abstraction and query builder for finding entities across types. Supports indexed field selection, faceting, and relevance ranking. Integrates with the API layer for search endpoints.

Key classes: `SearchRequest`, `SearchResult`, `SearchProviderInterface`.

## Implementation gotchas

- **FTS5 `SELECT m.*` misses FTS5 columns**: When joining `search_index` (FTS5) with `search_metadata`, `m.*` only selects metadata columns. To get FTS5 content columns (title, body), select them explicitly: `si.title`, `si.body`. The `snippet()` function also requires column index references into the FTS5 table.
- **FTS5 query escaping must strip special chars**: FTS5 treats `*`, `^`, `{}`, `:`, `"` as operators in addition to `AND/OR/NOT/NEAR`. Quoting terms with `"..."` is not sufficient — strip special characters before quoting to prevent query injection.
