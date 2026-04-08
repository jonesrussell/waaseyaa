# Symfony 7 version floors in split packages

Packages that depend directly on Symfony components use **`^7.0`** unless a concrete API or behavior from Symfony **7.1+** is required (none identified as of 2026-04).

## Audit (2026-04-08, #1151)

| Package            | Symfony component(s) | Previous floor | Current floor | Notes |
|--------------------|----------------------|--------------|---------------|-------|
| waaseyaa/entity    | event-dispatcher, uid, validator | ^7.3 | ^7.0 | No usage of APIs gated on 7.3 |
| waaseyaa/entity-storage | event-dispatcher | ^7.3 | ^7.0 | Same |
| waaseyaa/queue     | messenger            | ^7.3 | ^7.0 | Same |
| waaseyaa/routing   | routing              | ^7.3 | ^7.0 | Same |
| waaseyaa/typed-data | validator           | ^7.3 | ^7.0 | Same |
| waaseyaa/validation | validator           | ^7.3 | ^7.0 | Same |

Foundation, search, and testing had floors normalized earlier (see `infrastructure.md` review stamp 2026-04-08b).

If a future change **requires** a minor that is only available in Symfony 7.3+, bump that one constraint and append a row here with the exact API reference.
