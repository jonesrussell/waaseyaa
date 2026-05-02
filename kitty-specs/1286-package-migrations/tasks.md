# Tasks / work packages — #1286

Work packages should be small enough for single PRs with clear review surfaces.

| WP | Title | Outcome |
|----|--------|---------|
| WP01 | **Spec + ADR sketch** | `docs/specs/` (or new `package-migrations.md`) documents current + intended Composer shape; RFC clearly labeled. |
| WP02 | **Reference migration** | One package (OIDC per issue, or agreed alternate) ships `migrations/*.php` + manifest entry; `migrate:up` passes. |
| WP03 | **Integration test** | Fresh SQLite path proves package migration applies and ledger row exists. |
| WP04 | **OIDC boot cleanup** | Remove `addFieldColumns` from `OidcServiceProvider::boot()`; migration is sole schema path for those columns. |
| WP05 | **Loader/manifest hardening (optional)** | If pursuing Phase B: support ordered array in `composer.json`, tests, composer policy if schema changes. |

**Review gate:** Each WP gets Spec Kitty `implement` → `review` before merge; link `#1286` in PR.
