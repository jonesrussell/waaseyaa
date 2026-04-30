# Mission spec: API Symfony decoupling

**Charter:** Wrap Symfony HTTP types behind Waaseyaa request/response abstractions so app code never imports Symfony directly. Scope is HTTP layer only.

**Milestone:** Track 3 — Parity & performance
**Origin:** Pass 1 architect-mode triage (2026-04-30). This mission absorbs the closed issues listed in `meta.json` `child_issues`.

---

## Purpose

Wrap Symfony HTTP types behind Waaseyaa request/response abstractions so app code never imports Symfony directly. Scope is HTTP layer only.

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
