# FR-007/008/009 Audit Close-out

Per research.md **Decision 3**, sites that consume the decoded JSON value retain
`try { json_decode } catch`. Replacing such sites with `json_validate + json_decode`
doubles the parse cost. Only pure validity gates (decoded value discarded) are
swap candidates.

## FR-007 — `packages/cli/src/Handler/MigrateDefaultsHandler.php:236`

**Status**: CLOSED — close-with-rationale.

**Rationale**: Inside `readMigrationLog()`, the decoded `$entry` is appended to
`$entries[]` (line 237) and returned to the caller, which iterates the array as
structured data. The decoded value is consumed downstream. Per research.md
Decision 3, retain `try { json_decode } catch (\JsonException)`.

## FR-008 — `packages/cli/src/Handler/FixturePackRefreshHandler.php:41`

**Status**: CLOSED — close-with-rationale.

**Rationale**: The decoded `$decoded` is the entire fixture scenario. Immediately
after the `try/catch` (lines 47–62), the code performs `is_array($decoded)`,
reads `$decoded['nodes']` and `$decoded['relationships']`, sorts them, and
writes them into `$scenarios[$scenarioName]`. The decoded value is the whole
point of the call. `json_validate` would force a second `json_decode` immediately
after. Per research.md Decision 3, retain `try { json_decode } catch (\Throwable)`.

## FR-009 — `PerformanceCompareCommand` (now `PerformanceCompareHandler`)

**Status**: CLOSED — close-with-rationale.

**Location at audit time**: `packages/cli/src/Handler/PerformanceCompareHandler.php:96`.
The audit document referred to it by its older name; the command was refactored
into a handler under the CLI kernel modernization but the json_decode site is
unchanged.

**Rationale**: Inside `readJsonFile()`, the decoded `$decoded` is returned to the
caller as `is_array($decoded) ? $decoded : null` (line 101). The decoded value
is the whole return value of the function. Per research.md Decision 3, retain
`try { json_decode } catch (\Throwable)`.

## Summary

All three FR-007/008/009 sites are close-with-rationale. No `json_validate`
swaps were performed in WP03. The `json_validate` swaps that remain in scope
for this mission live in WP02's owned files (validity-gate sites where the
decoded value is discarded).
