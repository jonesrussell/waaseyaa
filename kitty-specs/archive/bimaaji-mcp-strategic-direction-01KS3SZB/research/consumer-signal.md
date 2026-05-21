# Consumer Signal Log

**Date gathered**: 2026-05-20
**Sources checked**:
- `/home/jones/dev/waaseyaa.org/` — Minoo local repo (present at this path)
- `grep -rn "bimaaji" /home/jones/dev/waaseyaa.org/src/` — PHP source scan
- `grep "waaseyaa/bimaaji" /home/jones/dev/waaseyaa.org/composer.json` — composer dependency check
- `gh issue list --state all --search "bimaaji mcp"` — GitHub issue search (waaseyaa/framework repo)
- `gh issue list --state all --search "mcp bimaaji"` — GitHub issue search (waaseyaa/framework repo)
- `kitty-specs/archive/` — prior bimaaji missions

---

## Signal Level: NONE

No consumer signal for bimaaji-via-MCP was found as of 2026-05-20.

The Minoo repo is present locally at `/home/jones/dev/waaseyaa.org/`. A grep of its PHP source and `composer.json` returned **no results** for `bimaaji` — Minoo does not currently depend on `waaseyaa/bimaaji` in its `composer.json` and has no PHP source referencing the bimaaji package. Minoo's previous interaction with bimaaji was limited to the broken `composer bimaaji-mcp-install` script (documented in #1387), which was removed from Minoo's `.claude/settings.json` in the alpha.171 upgrade mission.

GitHub issue search (`gh issue list --search "bimaaji mcp"` and `gh issue list --search "mcp bimaaji"`) returned only the framework-internal issues #1463 and #1387 — both maintainer-filed, not consumer-filed. Issue #1463 is explicitly tagged as "decision-pending — no work scheduled until maintainer triages" and has zero comments. There is no consumer-filed issue requesting bimaaji-via-MCP.

---

## Evidence

| Source | Result |
|--------|--------|
| `/home/jones/dev/waaseyaa.org/composer.json` | `waaseyaa/bimaaji` not in require or require-dev |
| `/home/jones/dev/waaseyaa.org/src/` PHP grep | 0 matches for "bimaaji" |
| GitHub: `gh issue list --search "bimaaji mcp"` | #1463 (maintainer, OPEN, 0 comments), #1387 (maintainer, CLOSED), #1196 (package skeleton, CLOSED), #1207 (spec-aware agent interface, CLOSED) |
| GitHub: `gh issue list --search "mcp bimaaji"` | Same 4 results — no consumer-authored issues |
| `kitty-specs/archive/` | No prior bimaaji-MCP missions found |

The only entity that has requested bimaaji MCP work is the maintainer in a self-filed planning issue (#1463). No consumer (Minoo or other) has filed a request, commented on #1463, or referenced bimaaji MCP in any tracked artifact.

---

## Implication

- **Option 1 (close #1463)**: No consumer signal observed — this is the strongest evidence in favor of Option 1. Closing #1463 as `not-planned` does not disappoint any active consumer.
- **Option 2 (extend `packages/mcp/`)**: Could be pursued proactively even without consumer signal, but the absence of demand reduces urgency. No existing consumer is blocked on this.
- **Option 3 (restore Node sidecar)**: Minoo removed the broken `mcpServers.bimaaji` entry after #1387 and has not re-requested it — no signal supports Option 3.
