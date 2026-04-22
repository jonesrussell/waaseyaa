# Genealogy share grants (C1) — `genealogy_share` v1

**Status:** Specification for implementation (v1 shape locked in the genealogy plan).

## Entity: `genealogy_share`

Each row is an **authorization-bearing grant document**, not a generic `relationship` edge.

### References

- **`genealogy_tree`** — workspace the grant applies to (required).
- **`user` (grantee)** — recipient account (required).

### Mutable columns (examples)

- **scope** — coarse visibility scope for the grantee within the tree (exact enum TBD at implementation).
- **`living_visible`** — whether living individuals may be shown under this grant.
- **branch filters** — optional serialized filter for subtree / branch scoping.
- **`expires_at`**, **`revoked_at`** — lifecycle; empty `revoked_at` means active when not expired.

### Events

Emit **domain events** on the foundation event bus when grants are created, accepted, changed, or revoked. Use **pluggable sinks** (log channel, null, queue). **No** immutable audit database is required until a product mandates compliance-grade retention.

### Relationship to `relationship`

- **Topology** (parent, spouse, household membership) stays on **`relationship`** rows.
- **Authorization** for cross-account visibility stays on **`genealogy_share`** rows.
