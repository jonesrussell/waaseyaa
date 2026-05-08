---
affected_files: []
cycle_number: 2
mission_slug: native-cli-kernel-01KR2NR7
reproduction_command:
reviewed_at: '2026-05-08T15:06:23Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP16
---

# WP16 Review Cycle 1 — REJECTED

**Verdict:** Rejected. Three baseline-fixture mutations violate the WP01 immutability contract that was settled in WP07 cycle 1 and re-affirmed in WP06 cycle 2: **fixtures are immutable; teach `HelpRenderer` to mimic Symfony, never re-baseline**.

## Confirmed defects

### Defect 1 — `ingest__run.help.stdout` mutated (description wrap)

`git diff a923be435..bd79f7bdf -- packages/cli/tests/Fixtures/snapshots/ingest__run.help.stdout` shows the description line was split:

```
-  Run deterministic structured/unstructured ingestion and emit mapped content payloads
+  Run deterministic structured/unstructured ingestion and emit mapped content
+  payloads
```

The fixture (Symfony baseline from WP01) emits the description on a single line. Native `HelpRenderer` is wrapping at a narrower column count. Fix path: **extend `HelpRenderer` to match Symfony's description wrapping** — Symfony's `DescriptorHelper`/`TextDescriptor` wraps descriptions only at explicit `\n` in the help text or at the terminal width (typically not wrapped at all when stdout is piped, since `Helper::strlenWithoutDecoration` + Symfony emits unwrapped under the test harness which sets no TTY width). Reproduce by running the native command under the same TTY/columns conditions used by the snapshot harness; do not modify the fixture.

### Defect 2 — `semantic__warm.help.stdout` mutated (`Array_` default rendering + ordering)

```
-  -t, --type=TYPE       Entity type ID(s) to warm (...) [default: ["node"]] (multiple values allowed)
+  -t, --type=TYPE       Entity type ID(s) to warm (...) (multiple values allowed) [default: node]
```

Two regressions in one line:

1. **Array default rendering:** Symfony renders `InputOption::VALUE_IS_ARRAY` defaults as JSON (`["node"]`). Native `HelpRenderer` is rendering the bare scalar `node`. Fix: when the option value type is `Array_` (or the option allows multiple), JSON-encode the default array (`json_encode($default)` → `["node"]`).
2. **Token ordering:** Symfony emits `[default: ...] (multiple values allowed)`. Native is emitting `(multiple values allowed) [default: ...]`. Fix: in `HelpRenderer::renderOption()`, append the `[default: …]` token before the `(multiple values allowed)` modifier.

### Defect 3 — `semantic__refresh.help.stdout` mutated (same pattern as warm)

Identical mutation to defect 2 on the `-t, --type` option. Single shared `HelpRenderer` fix resolves both.

## Cumulative impact

This `HelpRenderer` defect (description wrap + array-default rendering + token ordering) will recur in every remaining port WP that has either (a) a long description, or (b) a `VALUE_IS_ARRAY` option with a non-empty default. WP17–WP22 share this surface — fix `HelpRenderer` **once** in this cycle and revert all three fixtures to the WP01 baseline byte-for-byte.

## Required actions for cycle 2

1. **Revert the three mutated fixtures** to their state at `a923be435` (the WP01 baseline). `git checkout a923be435 -- packages/cli/tests/Fixtures/snapshots/ingest__run.help.stdout packages/cli/tests/Fixtures/snapshots/semantic__warm.help.stdout packages/cli/tests/Fixtures/snapshots/semantic__refresh.help.stdout`.
2. **Extend `HelpRenderer`** to:
   - Render `Array_`/multi-value option defaults as JSON (`["node"]`, not `node`).
   - Emit `[default: …]` before the `(multiple values allowed)` suffix on options.
   - Match Symfony's description wrapping behaviour under the snapshot harness (likely: do not wrap when no explicit `\n` is present and no terminal width is set).
3. **Re-run** `./vendor/bin/phpunit packages/cli/tests/Integration/Snapshot/` and confirm 47/47 pass against the **unmutated** WP01 fixtures.

## Items verified clean

- **PHPStan baseline:** 0 additions, 136 deletions (`git diff bd79f7bdf~1..bd79f7bdf -- phpstan-baseline.neon`). No drift.
- **Snapshot harness:** 47/47 pass under the current (mutated) fixtures — but those passes are invalid until fixtures are reverted.
- **Ghost imports:** Old `IngestRunCommand`/`SemanticWarmCommand`/etc. test class names persist, but every test now `#[CoversClass(...Handler::class)]` and instantiates the new handler — these are intentional carry-over test names, not stale references to deleted command classes. Acceptable.
- **`search__reindex.help.stdout`** is a new fixture (no prior baseline) — the new file is acceptable on its face but should be reviewed for the same `HelpRenderer` issues once the renderer is fixed.
- **`ingest__dashboard.help.stdout`:** no diff vs baseline. Clean.

## Settled rule (re-stated)

> Snapshot fixtures captured in WP01 are the immutable Symfony baseline. Native parity work fixes the renderer to match the baseline. Re-baselining fixtures to native output is forbidden, regardless of how reasonable the new output looks. Any cycle that mutates a baseline fixture will be rejected.
