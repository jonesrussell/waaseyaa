# Phase 0 Research — PHP 8.4 Mechanical Modernization

**Mission**: php84-mechanical-modernization-01KR82KT
**Date**: 2026-05-10

The audit on 2026-05-10 enumerated candidate sites; this document records the four design decisions that bound the implementation.

## Decision 1: `array_find` is the right replacement for `array_values(array_filter(...))[0]`

- **Decision**: Use `array_find($array, $predicate)`.
- **Rationale**: Existing patterns extract the first matching element. `array_find` returns it directly (or `null` on miss).
- **Alternatives considered**:
  - `array_any` — returns bool, wrong shape.
  - `array_filter` chain (current) — verbose, allocates intermediate array.
  - `foreach { if return }` — verbose, control-flow noise.

## Decision 2: `array_find` returns `null` on miss; the prior pattern raised `Undefined offset`

- **Decision**: Treat each swap as a behavior review, not a refactor. If the prior code's no-match path was an error (intentional or accidental), preserve that. Most current sites either guarantee a match (test fixtures) or call inside an assertion that would catch a `null`.
- **Rationale**: Mechanical ≠ identical. `null`-returning APIs fail differently than offset-undefined errors.
- **Alternatives considered**: Trust callers — rejected (silent test failures).

## Decision 3: Keep `try { json_decode } catch` where the decoded value is consumed

- **Decision**: Apply `json_validate()` only in catch-only-suppression sites where the decode result is discarded. Sites that decode-and-use keep the existing pattern.
- **Rationale**: `json_validate` does not return decoded data. Using it as a precheck doubles the parse cost in hot paths. The optimization shines only when the catch is a pure validity gate.
- **Alternatives considered**:
  - Use `json_validate` everywhere — rejected (perf regression).
  - Refactor consumers to take pre-validated input — out of scope (architectural).

## Decision 4: `#[\Deprecated]` attribute kept alongside `@deprecated` docblock

- **Decision**: Add the attribute; do not remove the docblock.
- **Rationale**: PHPStan reads both. Some IDEs still parse only docblocks. The cost of keeping both during one release cycle is zero. A future cleanup mission can drop the docblock when tooling catches up.
- **Alternatives considered**: Attribute-only — rejected, conservative migration.

## No outstanding NEEDS CLARIFICATION

All audit-identified sites have a clear classification. WP05 (routing/access sweep) is a read-only audit step; if it surfaces nothing, the WP closes with a one-line "no candidates found" note.
