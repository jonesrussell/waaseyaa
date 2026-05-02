# Tasks / work packages — #529

Map to GitHub children where possible; split further inside Spec Kitty if needed.

| WP | GitHub | Title | Outcome |
|----|--------|--------|---------|
| WP01 | **#522** | Design spec | `docs/specs/` artifact: diff algebra, safety gates, compiler contract, ledger fields, CLI UX. |
| WP02 | **#521** | Engine + compiler | `SchemaDiff` types + SQLite compiler + integration with `Migrator` / migration loading strategy. |
| WP03 | **#518** | Regression tests | Additive / rename-like / destructive cases; idempotency; bundle + entity-level fixtures. |
| WP04 | — | Ledger checksum | Schema + `MigrationRepository` + ADR for existing installs. |
| WP05 | — | dry-run / verify | CLI + structured output; link to operator diagnostics if applicable. |
| WP06 | — | Composer manifest | Ordered manifest support; deprecation path for directory-only loader. |

**Review:** Each WP through Spec Kitty implement → review; epic #529 stays open until all acceptance criteria met.
