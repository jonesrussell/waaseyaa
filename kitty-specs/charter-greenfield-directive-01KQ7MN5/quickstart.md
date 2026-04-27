# Quickstart: Greenfield Removal Directive Amendment

**Mission**: `charter-greenfield-directive-01KQ7MN5`
**Goal**: Add `DIR-003: Greenfield Removal Policy` to the project charter as a top-level directive.

This is the implementation recipe — the exact edits and verification commands. Phase 0 research (research.md) settled all open questions; everything below is mechanical.

## Pre-flight

```bash
# From project root checkout (NOT a worktree).
cd /c/Users/jones/Projects/Rainbow/waaseyaa-framework
git checkout main
git pull --ff-only

# Confirm clean baseline.
spec-kitty charter status --json | jq '.status'
# Expected: "synced"
```

## Step 1 — Edit `charter.md`

Open `.kittify/charter/charter.md` and modify the `## Project Directives` section.

### 1a. Trim the alpha-phase paragraph from DIR-001's "Public-API removal policy is phase-dependent" sub-clause

Find this block (currently inside item `1. Respect risk boundaries:`):

```markdown
Public-API removal policy is phase-dependent:
  - During alpha (current state): greenfield principle applies. When a
    better pattern lands, the old one is removed outright; no
    deprecation window is required. Backwards-compat shims that retain
    known-bad patterns are forbidden.
  - At beta entry and beyond: removals from the public API surface
    ...
```

Replace with:

```markdown
Public-API removal policy is phase-dependent. During alpha, see DIR-003
(Greenfield Removal Policy). At beta entry and beyond, removals from
the public API surface follow formal deprecation:
  - Use the `@deprecated <since-version> <reason and migration target>`
    PHPDoc tag at the symbol's declaration site.
  - Reference a target removal version (e.g., "since 1.2.0 — remove in
    2.0.0") and a one-line migration note pointing at the replacement
    symbol or recipe.
  - Remain in place for at least two minor releases unless the symbol
    is security-critical, in which case removal cadence follows the
    security-advisory timeline.
  - Are listed under a `### Deprecated` heading in `CHANGELOG.md` for
    the release that introduces them.
```

(The post-alpha rules are preserved verbatim. Only the alpha sub-bullet is replaced with a cross-reference.)

### 1b. Add DIR-003 as a new top-level numbered item

After item `2. Keep documentation synchronized with workflow and behavior changes.` (and before `## Reference Index`), add:

```markdown
3. Greenfield Removal Policy: during alpha (current state), the
greenfield principle applies. When a better pattern lands, the old one
is removed outright. No deprecation window is required. Backwards-compat
shims that retain known-bad patterns are forbidden. `@deprecated`
wrappers, `Legacy*` namespaces, parallel `v2` interfaces, and "for
backward compatibility" comments are not acceptable substitutes for
deletion. Architecture quality is preferred over API stability for the
duration of alpha. Breaking changes are still announced explicitly per
DIR-001 (CHANGELOG.md entry, UPGRADING.md migration recipe) —
communication discipline is preserved; compatibility debt is not.
Severity is policy-binding regardless of the `severity: warn` field in
`directives.yaml` (Spec Kitty 3.1.6 hardcodes severity for all
directives synced from `charter.md`); the binding force of this
directive comes from its text.
```

**Why a single paragraph**: the extractor folds sub-bullets and continuation paragraphs into a single `description` field, but only when they belong to the same numbered item. Keeping DIR-003 as one paragraph with no nested bullets ensures the description is captured in full and there is no risk of orphaned content.

## Step 2 — Sync to YAML

```bash
spec-kitty charter sync --force
```

Expected stdout: `synced` or equivalent success message. If it errors, read the error and check that the numbered list in `## Project Directives` is well-formed (1. 2. 3. with no gaps).

## Step 3 — Verify directives.yaml

```bash
cat .kittify/charter/directives.yaml
```

Expect three entries: DIR-001, DIR-002, DIR-003. DIR-003 should have:
- `id: DIR-003`
- `title:` starting with "Greenfield Removal Policy:" (truncated at 50 chars)
- `description:` containing the full directive text
- `severity: warn`

## Step 4 — Verify compact context

```bash
spec-kitty charter context --action specify --json | jq -r '.text'
```

Expect:
```
Governance:
  - Template set: software-dev-default
  - Paradigms: domain-driven-design
  - Directives: DIR-001, DIR-002, DIR-003
  - Tools: git, spec-kitty
```

The key assertion: `DIR-003` appears in the `Directives:` line.

## Step 5 — Verify idempotency (NFR-003)

```bash
spec-kitty charter sync --force
git diff .kittify/charter/directives.yaml
```

Expected: empty diff. The second sync produces no change.

## Step 6 — CHANGELOG entry (FR-007)

Open `CHANGELOG.md` and add an entry under the next-release `## [Unreleased]` (or equivalent) section:

```markdown
### Added
- Charter directive **DIR-003 (Greenfield Removal Policy)** hoisted from
  a sub-bullet inside DIR-001 into its own top-level directive, so it
  surfaces in compact charter context loaded by every `/spec-kitty.specify`
  and `/spec-kitty.plan` invocation. No policy change — the alpha-phase
  greenfield removal rule was already charter law inside DIR-001. See
  `.kittify/charter/charter.md`. (mission `charter-greenfield-directive-01KQ7MN5`)
```

If `CHANGELOG.md` does not yet have an `## [Unreleased]` heading, add one at the top below the existing introductory text.

## Step 7 — Diff size check (NFR-001)

```bash
git diff --stat .kittify/charter/charter.md .kittify/charter/directives.yaml CHANGELOG.md
```

Expected: combined `+/-` line count ≤ 100.

## Step 8 — Commit

```bash
git add .kittify/charter/charter.md .kittify/charter/directives.yaml CHANGELOG.md
git commit -m "$(cat <<'EOF'
chore(charter): hoist DIR-003 (Greenfield Removal Policy)

Hoist the existing alpha-phase greenfield-removal sub-bullet from DIR-001
into its own top-level directive (DIR-003) so it surfaces in the compact
charter context that every agent loads on /spec-kitty.specify and
/spec-kitty.plan. Substantive policy unchanged; only structural placement
and ID assignment.

Mission: charter-greenfield-directive-01KQ7MN5

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Done check

- [ ] `charter.md` edited (DIR-001 sub-bullet replaced; DIR-003 added)
- [ ] `directives.yaml` regenerated, contains DIR-003
- [ ] Compact context lists `DIR-001, DIR-002, DIR-003`
- [ ] Second sync produces no diff
- [ ] CHANGELOG entry added
- [ ] Diff ≤ 100 lines
- [ ] Committed
- [ ] No `packages/` files modified

## What NOT to do

- ❌ Do **not** edit `directives.yaml` directly. The next `charter sync` will overwrite it.
- ❌ Do **not** run `spec-kitty charter generate --from-interview --force` for this amendment. The interview path is for content regeneration, not structural reorgs (research.md Q2).
- ❌ Do **not** add a `**Severity:** error` annotation to DIR-003 in `charter.md`. Spec Kitty 3.1.6 ignores it (research.md Q1) and it would mislead future readers.
- ❌ Do **not** modify `governance.yaml`, `metadata.yaml`, `references.yaml`, or `interview/` content. Out of scope (FR-008).
- ❌ Do **not** also update the in-flight Single-Entity Work Surface mission spec to reference DIR-003 in this PR. That cleanup belongs to that mission's own work packages (Out of Scope).
