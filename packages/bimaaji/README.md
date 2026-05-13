# waaseyaa/bimaaji

**Bimaaji** is a Waaseyaa package for graph-oriented knowledge work. This repository currently contains scaffolding only; behavior will land in follow-up issues.

Roadmap context: [GitHub Milestone #67](https://github.com/waaseyaa/framework/milestone/67).

Full documentation will be added in [Issue #1209](https://github.com/waaseyaa/framework/issues/1209).

## Status

**Bimaaji ships PHP-only in the current alpha range.** The MCP server bindings that previously lived under `mcp/` (Node-based `server.js` exposing `bimaaji_ping` / `bimaaji_about` tools) have been removed — they never reached consumers reliably (`composer bimaaji-mcp-install` exited 254 in downstream projects because `vendor/waaseyaa/bimaaji/mcp/server.js` was not present at runtime). Restoration is a roadmap item, tracked in [#1463](https://github.com/waaseyaa/framework/issues/1463) (deferred from [#1387](https://github.com/waaseyaa/framework/issues/1387)).

### Consumer cleanup

Projects (e.g., Minoo) that previously wired bimaaji's MCP server should:

1. Remove any `mcpServers.bimaaji` entry from `.claude/settings.json` (it pointed at a non-existent `vendor/waaseyaa/bimaaji/mcp/server.js`).
2. Drop `composer bimaaji-mcp-install` from post-install hooks or contributor docs — the script body was Minoo-local and has no upstream entry point.
3. Wait for [#1463](https://github.com/waaseyaa/framework/issues/1463) before re-introducing any bimaaji MCP tooling.
