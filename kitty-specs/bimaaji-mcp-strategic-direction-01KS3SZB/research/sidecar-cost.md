# Node Sidecar Maintenance Cost History

**Date gathered**: 2026-05-20
**Sources**: `git log --all --oneline -- packages/bimaaji/mcp*`, #1387, #1463

---

## Timeline

| Date | Event |
|------|-------|
| approx 2026-04 | `packages/bimaaji/mcp/` scaffolding added (server.js, package.json, package-lock.json) — earliest known presence per git |
| 2026-04-13 | #1196 (Package skeleton) closed; bimaaji shipped in alpha range with MCP scaffolding present |
| alpha.157+ | Failure onset: `vendor/waaseyaa/bimaaji/mcp/server.js` absent at runtime; `mcp/` directory contains only `package-lock.json` |
| alpha.171 | Failure confirmed during Minoo upgrade mission (`upgrade-waaseyaa-alpha-171-01KQTDC2`, WP06) |
| 2026-05-13 | #1387 filed: "bimaaji: restore or remove MCP server scaffolding" |
| 2026-05-13 | Commit `46f4c41af`: "chore(bimaaji): remove broken Node MCP scaffolding + document PHP-only status (#1387) (#1464)" — removes `server.js` (50 lines), `package.json` (11 lines), `package-lock.json` (1,139 lines); updates `README.md` |
| 2026-05-13 | #1387 closed; #1463 filed as deferred roadmap item |

**Span**: scaffolding existed for approximately 1 month (early April → May 13 2026) without ever working reliably.

---

## Failure Mode

`composer bimaaji-mcp-install` exits with code **254** in any consumer project with `waaseyaa/bimaaji` installed (verified in Minoo through alpha.171). The expected artifact `vendor/waaseyaa/bimaaji/mcp/server.js` is absent at runtime — the `mcp/` directory was shipped with only `package-lock.json`, not the actual `server.js` or `package.json`.

Consequence for consumers: `.claude/settings.json` entries for `mcpServers.bimaaji` pointed to a non-existent path. Claude Code sessions in those repos could not resolve any `mcp__bimaaji__*` tools. The `bin/install-mcp-npm.php` post-install hook gracefully skips directories without `package.json`, so `composer install` itself did not fail — the breakage was silent until consumers manually ran `composer bimaaji-mcp-install`.

---

## Root Cause (if determinable)

The `package.json` and `server.js` were present in the scaffolding commit but the artifact did not survive Packagist distribution — `mcp/` directory contents were either excluded from the published archive or the Composer split mechanism dropped non-PHP files. This is a **distribution pipeline gap**, not a code logic bug: the Node files existed in the monorepo but did not reach consumers via `composer install`.

Root cause is partially determinable: the `bin/install-mcp-npm.php` graceful-skip behavior masked the failure from `composer install`, meaning no CI gate caught the missing artifact. Exact cause of the Packagist exclusion is not determinable from git history alone — would require inspecting `.gitattributes` or Packagist archive contents at alpha.157.

---

## Cost Estimate

- **Commits directly related to bimaaji MCP**: 2 (the scaffolding add commit in early April — exact hash not recoverable from current git log — and the removal commit `46f4c41af` on 2026-05-13)
- **Issues**: #1387 (filed + closed same day), #1463 (open, 0 comments, decision-pending)
- **Consumer impact**: Minoo WP06 in mission `upgrade-waaseyaa-alpha-171-01KQTDC2` — one WP dedicated to diagnosing and working around the broken MCP entry
- **Developer time estimate**: **medium** — approximately 1–2 hours of developer time across: initial scaffolding, discovery of failure, Minoo workaround WP, issue filing, and removal PR. The sidecar was never functional from consumer perspective despite being present for ~1 month.

---

## Implications per option

- **Option 3 (restore sidecar)**: The failure mode is addressable in principle (fix the Packagist distribution of `mcp/` Node files, add a working install script, add a CI verification step) but requires solving a distribution pipeline problem that was not solved in the original alpha range. Re-adding the sidecar without fixing that pipeline would reproduce the same failure. The `bin/install-mcp-npm.php` graceful-skip creates a dangerous silent-failure footgun. Estimated cost to fix properly: 4–8 hours (pipeline investigation + fix + consumer integration test). This is disproportionate to current consumer demand (zero).
- **Option 1 / Option 2**: The sidecar cost history is concrete evidence that the Node sidecar approach carries a distribution maintenance burden unique to non-PHP artifacts in a PHP-primary Composer package. Option 2 eliminates this class of failure entirely. Option 1 defers the question with zero maintenance cost.
