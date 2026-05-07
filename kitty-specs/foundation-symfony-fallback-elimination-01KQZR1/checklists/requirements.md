# Requirements checklist — foundation-symfony-fallback-elimination-01KQZR1

Use during `/spec-kitty.specify` and plan review. Mark items when satisfied.

## Problem framing

- [ ] Mission names the concrete fallback categories (routing edge, controller shape, service resolution duplication, any others found in WP01).
- [ ] Each category maps to an owner package (`waaseyaa/foundation`, `waaseyaa/routing`, `waaseyaa/ssr`, etc.) before implementation WPs commit.

## Success

- [ ] `docs/specs/infrastructure.md` (or a scoped contract file) documents the post-change resolution order with zero undocumented implicit fallbacks in foundation HTTP paths.
- [ ] PHPUnit / PHPStan gates pass on `main` after changes.
- [ ] Consumer impact (Giiken, Minoo, skeleton) is either none or listed with an explicit upgrade note in CHANGELOG.

## Governance

- [ ] PR traceability follows `docs/specs/workflow.md` (issue or mission reference in PR body).
- [ ] No edits under `vendor/`.
