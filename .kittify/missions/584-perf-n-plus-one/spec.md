# Mission spec: Performance: N+1 elimination and query optimization

**Charter:** Eager loading on EntityRepository, DataLoader for GraphQL, query result caching, SQL-level pagination, EntityReferenceItem N+1 fix.

**Milestone:** Track 3 — Parity & performance
**Origin:** Pass 1 architect-mode triage (2026-04-30). This mission absorbs the closed issues listed in `meta.json` `child_issues`.

---

## Purpose

Eager loading on EntityRepository, DataLoader for GraphQL, query result caching, SQL-level pagination, EntityReferenceItem N+1 fix.

This is a minimal scaffold. Before any work package enters the `implement` lane, expand this section to:

- name the public contracts touched (interfaces, attributes, events)
- list the breaking changes allowed under the modern stance (PHP 8.4+, no legacy)
- reference the relevant `docs/specs/` artifacts that constrain the mission
- describe the verification evidence required for sign-off

---

## Absorbed issues

See `meta.json` `child_issues` for the full list. Each closed issue carries a cross-link comment pointing back to this mission.

---

## Acceptance

To be defined per work package. The charter alone is not enough to drive execution; the spec must define contracts, breakage, and evidence before any WP runs.
