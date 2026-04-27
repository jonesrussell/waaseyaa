# Phase 0 Research: Greenfield Removal Directive

**Mission**: `charter-greenfield-directive-01KQ7MN5`
**Date**: 2026-04-27
**Spec Kitty version**: 3.1.6 (verified via `spec-kitty --version` and uv tool list)

## Q1 — Severity vocabulary

### Decision

**Ship DIR-003 at `severity: warn`.** Drop FR-003's `severity: error` requirement.

### Rationale

Spec Kitty 3.1.6's charter extractor hardcodes severity to `"warn"` for every directive synced from `charter.md`. Source: `specify_cli/charter/extractor.py:287` (located via uv cache at `C:\Users\jones\AppData\Local\uv\cache\archive-v0\S1J94GGS3-X_C25qwzd3a\specify_cli\charter\extractor.py`):

```python
directive = Directive(
    id=directive_id,
    title=item_text[:50],
    description=item_text,
    severity="warn",       # hardcoded — no markdown override
)
```

There is no charter.md annotation pattern (`**Severity:** error`, frontmatter, sibling YAML override) that the parser reads. Directives written into the auto-generated `directives.yaml` always carry `severity: warn`. Manual post-sync edits to `directives.yaml` would be clobbered by the next `spec-kitty charter sync`.

### Why this is acceptable

The mission's *primary* goal — visibility of the greenfield rule in the compact charter context — does not depend on severity. The compact context emits `Directives: DIR-001, DIR-002, DIR-003` as a list of IDs (per `charter/context.py` rendering). Severity in `directives.yaml` is metadata; it does not appear in the compact context string and does not gate any agent behavior in 3.1.6.

The directive's *text* — "no shims, no deprecation wrappers, remove bad architecture outright, no matter the cost" — is what binds agents. A `warn` severity attached to that text is no weaker than an `error` severity for our purposes: the rule is non-negotiable in policy, not in YAML metadata.

### Alternatives considered

- **Patch spec-kitty upstream** — out of scope for this mission. Could be a follow-up if severity ever gates behavior in a future Spec Kitty version.
- **Manual edit of `directives.yaml` after sync, then `git add` it** — fragile; next contributor running `charter sync` will silently overwrite. Rejected.
- **Add custom field `**Strength:**` in charter.md and parse it ourselves** — invents a private convention; defeats the point of using upstream tooling.
- **Block on severity** — would block visibility (the actual win) on a metadata field that doesn't matter today.

### Spec impact

- **FR-003 amended**: requirement now reads "DIR-003 entry shall be present in `directives.yaml` with description text conveying 'no shims, no deprecation wrappers, remove bad architecture outright'." Severity expectation dropped.
- **FR-005 unaffected**: existing DIR-001/DIR-002 severity stays `warn`. They were already `warn`. No change.
- **Spec C-005 still holds**: directive text is self-contained; the *text* enforces the rule, not the severity field.

## Q2 — `charter sync` vs `charter generate --from-interview --force`

### Decision

**Use `spec-kitty charter sync --force` after editing `charter.md`.** Do not run `charter generate --from-interview --force`.

### Rationale

The existing `directives.yaml` carries the auto-generated header:

```yaml
# Auto-generated from charter.md — do not edit directly.
# Run 'spec-kitty charter sync' to regenerate.
```

This is the canonical regeneration command for the structured YAML config. `charter sync` reads `charter.md` and rewrites `governance.yaml`, `directives.yaml`, `metadata.yaml`, and `references.yaml` to match.

`charter generate --from-interview --force` is a heavier path that re-runs the interview-driven *content generation* — useful when policy text itself comes from interview answers. For a structural reorg of existing policy text (our case), running the interview path would either:

1. Re-prompt for interview answers we don't want to change, or
2. Regenerate the charter from cached answers, possibly losing the manual structural edits.

Charter status currently shows `synced` (verified via `spec-kitty charter status --json`), so no pending mismatch to reconcile before the amendment.

### Spec impact

- **FR-003 / quickstart**: implementation runs `spec-kitty charter sync --force` (the `--force` flag because the post-edit hash will differ from `stored_hash`).
- **Charter Check section in plan.md updated**: confirms `sync`, not `generate --from-interview`, is the mandated path.

## Q3 — Cross-reference rendering / DIR-003 ID assignment

### Decision

**Adding a third numbered item to `## Project Directives` produces `DIR-003` automatically.**

### Rationale

`extractor.py:266-290` shows directive IDs are assigned by enumeration:

```python
directive_counter = 1
...
for item_text in numbered_items:
    directive_id = f"DIR-{directive_counter:03d}"
    ...
    directive_counter += 1
```

`numbered_items` comes from `section.structured_data` populated by `parser.py` from numbered markdown lists (`1. ...`, `2. ...`, `3. ...`). The current charter has two: `1. Respect risk boundaries...` (DIR-001) and `2. Keep documentation synchronized...` (DIR-002).

Adding `3. Greenfield Removal Policy: ...` as a third top-level numbered item in the same `## Project Directives` section will produce DIR-003 with `title = first 50 chars` and `description = full item text`. DIR-001 and DIR-002 are unaffected (they retain their position-based IDs).

**Important**: the directive's `description` is the *full* numbered-item text. Sub-bullets and continuation paragraphs are folded into the description. The DIR-003 entry must therefore be self-contained at the top level of the numbered item — no orphaned paragraphs that would be omitted.

### Spec impact

- **Quickstart edit recipe**: write DIR-003 as a single numbered item with policy text inline (followed by a "Post-alpha transition:" subsection that stays inside the same numbered item).
- **FR-001/FR-002**: unchanged. DIR-001's sub-bullet replacement is mechanical text editing.

## Q4 — Severity expression in markdown

**Resolved by Q1.** Severity cannot be expressed in `charter.md`. Hardcoded `warn`. See Q1 decision.

## Q5 — Compact context rendering verification (added during research)

### Decision

**The compact context's `Directives:` line is a comma-separated list of directive IDs only. Adding DIR-003 will surface as `Directives: DIR-001, DIR-002, DIR-003`. The directive *description text* does not appear in the compact `text` field.**

### Rationale

Inspection of the existing compact charter context output:

```
Governance:
  - Template set: software-dev-default
  - Paradigms: domain-driven-design
  - Directives: DIR-001, DIR-002
  - Tools: git, spec-kitty
```

Confirms: only IDs are emitted in compact mode. Description text is available via the JSON `references_count` path (full bootstrap mode) or by reading `directives.yaml` directly.

### Spec impact

- **FR-004 still satisfied**: `Directives:` line will include `DIR-003` after the amendment. ✅
- **Scenario 1 (Agent loads compact charter context)**: the agent sees `DIR-003` *as an ID*. To read the policy text, the agent must either (a) use bootstrap mode, (b) read `directives.yaml`, or (c) read `charter.md`. This is a property of Spec Kitty's compact mode, not a flaw in the amendment. The amendment still moves the needle: previously the agent saw nothing about greenfield removal; now it sees a referenceable ID.
- **Followup opportunity (out of scope here)**: Spec Kitty could be enhanced to include a per-directive one-line summary in the compact `text` field. Worth raising upstream but not required for this mission.

## Summary of spec deltas to apply before/during implement

| Spec field | Original | Updated |
|---|---|---|
| FR-003 | "DIR-003 entry … with `severity: error`" | "DIR-003 entry … description conveys 'no shims, no deprecation wrappers, remove bad architecture outright'." Severity drops. |
| FR-005 | "Only DIR-003 is added at `severity: error`" | "DIR-003 ships at `severity: warn` (Spec Kitty 3.1.6 hardcodes severity for all directives synced from charter.md). Existing DIR-001/DIR-002 unchanged." |
| Spec § Assumptions, severity vocabulary | "available severity levels include warn and error; `error` is appropriate" | Replaced by Q1 finding: severity is hardcoded warn in 3.1.6. |

These deltas will be applied to spec.md as part of the first work package's pre-implementation revision (allowed by the standard mission lifecycle: research informs spec refinement before tasks).

## Tooling notes

- `spec-kitty charter status --json` returned `status: synced` — clean baseline before edits.
- `spec-kitty charter sync --force` is the regeneration command. `--force` is required because `charter.md` hash will differ from `stored_hash` after edits.
- Idempotent regeneration (NFR-003) verified by running `sync --force` twice — the parser is deterministic on the same input.

## References

- `specify_cli/charter/extractor.py:266-292` — directive ID assignment + hardcoded severity.
- `specify_cli/charter/parser.py` — numbered-list extraction.
- `specify_cli/charter/sync.py` — `charter.md` → YAML rewrite path.
- `specify_cli/charter/context.py` — compact vs bootstrap rendering.
- `.kittify/charter/directives.yaml` (current state) — `severity: warn` on DIR-001 and DIR-002.
- `.kittify/charter/charter.md` (current state) — `## Project Directives` with two numbered items.
