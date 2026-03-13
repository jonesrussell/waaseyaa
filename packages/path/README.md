# waaseyaa/path

**Layer 2 — Content Types**

Path alias management for Waaseyaa applications.

Defines the `path_alias` entity type mapping human-readable URLs to internal entity identifiers. `PathAliasResolver` is used by the SSR page handler to look up entities from request paths. `PathAliasAccessPolicy` (auto-discovered via `#[PolicyAttribute]`) makes aliases publicly viewable; create/edit/delete requires `administer url aliases`.

Key classes: `PathAlias`, `PathAliasResolver`, `PathAliasAccessPolicy`, `PathServiceProvider`.
