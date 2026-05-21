---
work_package_id: WP04
title: CI Gate + Playwright Regression + Wrap-Up
dependencies:
- WP01
- WP02
- WP03
requirement_refs:
- C-003
- C-005
- C-006
- FR-012
- NFR-004
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T014
- T015
- T016
- T017
agent: "claude:sonnet:implementer:implementer"
shell_pid: "798647"
history:
- date: '2026-05-20T23:57:32Z'
  event: created
authoritative_surface: bin/
execution_mode: code_change
owned_files:
- bin/check-admin-coercion-patterns
- packages/admin/e2e/schema-dedup.spec.ts
- CHANGELOG.md
tags: []
---

# WP04 — CI Gate + Playwright Regression + Wrap-Up

**Mission**: `admin-spa-realtime-config-contract-01KS3ST4`
**Branch strategy**: Planning base `main` → Merge target `main`. Execution worktree allocated from `lanes.json`. **Depends on WP01, WP02, WP03 merged.**
**Implement command**: `spec-kitty agent action implement WP04 --agent <name>`

---

## Objective

Complete the mission's hardening layer:

1. **`bin/check-admin-coercion-patterns`** — a CI gate that fails if any `String(x) === '1'` coercion pattern reappears in `packages/admin/app/**`. NFR-004 enforcement.
2. **`packages/admin/e2e/schema-dedup.spec.ts`** — Playwright network-capture spec that visits `/admin/node` and asserts exactly one POST to the schema endpoint per page load. FR-012 coverage.
3. **`CHANGELOG.md`** — `[Unreleased]` entries for both fixes.
4. **Full verification** — confirm `composer verify`, `npm run build`, `npm test`, `npm run test:e2e`, `npm run lint`, and `bin/check-admin-coercion-patterns` are all green on the final commit.

After this WP, the mission is complete: SC-001..SC-007 all satisfied, #1537 and #1538 closed.

---

## Context

**Prerequisites (from WP01–WP03)**:
- `useSchema()` in-flight dedup is implemented and unit-tested.
- `useAdminConfig()` composable exists with typed coercion helpers.
- All 19 call-site files are migrated; zero `useRuntimeConfig`/`String(x)==='1'` references remain.

**Constraints**:
- C-003: Merge commit footer includes `Closes #1537` and `Closes #1538`.
- C-005: `composer verify` + `cd packages/admin && npm run build && npm test && npm run lint` all green.
- C-006: No CI hooks bypassed.
- NFR-004: `bin/check-admin-coercion-patterns` must return zero matches.

---

## Subtask Details

### T014 — Create `bin/check-admin-coercion-patterns`

**Purpose**: NFR-004 enforcement — a grep-based CI gate that prevents the `String(x) === '1'` coercion anti-pattern from re-entering the SPA. Exits 1 if any matches found; documents the suppression convention.

**File**: `bin/check-admin-coercion-patterns` (new, executable)

**Implementation**:
```bash
#!/usr/bin/env bash
# bin/check-admin-coercion-patterns
#
# CI gate: fail if any String(x) === '1' (or '0') coercion patterns appear
# in packages/admin/app/**. These patterns indicate a bypass of the
# useAdminConfig() typed boundary.
#
# Suppression: Add  // allow-coercion: <reason>  on the offending line to
# suppress a confirmed false positive. Document the reason clearly.
#
# Usage: ./bin/check-admin-coercion-patterns
# Returns: 0 (no matches), 1 (matches found)
#
# Called by: cd packages/admin && npm run lint (or composer verify)

set -euo pipefail

SEARCH_DIR="packages/admin/app"

if [[ ! -d "${SEARCH_DIR}" ]]; then
  echo "ERROR: Directory ${SEARCH_DIR} not found. Run from repo root." >&2
  exit 2
fi

# Patterns to flag:
#   String(x) === '1'  or  String(x) === "1"
#   String(x) === '0'  or  String(x) === "0"
# Excludes lines with // allow-coercion: suppression marker.

matches=$(
  grep -rn \
    --include="*.ts" \
    --include="*.vue" \
    "String(.*) === ['\"].\?['\"]" \
    "${SEARCH_DIR}" 2>/dev/null \
  | grep -v "allow-coercion:" \
  || true
)

if [[ -n "${matches}" ]]; then
  echo "FAIL: Coercion pattern(s) found in ${SEARCH_DIR}:"
  echo ""
  echo "${matches}"
  echo ""
  echo "Fix: Replace String(x) === '1' with useAdminConfig().<field> (boolean)."
  echo "     See packages/admin/README.md § 'Runtime config' for the migration guide."
  echo ""
  echo "Suppress a false positive: add  // allow-coercion: <reason>  on the line."
  exit 1
fi

echo "OK: No coercion patterns found in ${SEARCH_DIR}."
exit 0
```

**Make executable**:
```bash
chmod +x bin/check-admin-coercion-patterns
```

**Wiring into CI** — choose ONE of:
a. Add to `composer verify` scripts section in root `composer.json`:
   ```json
   "verify": "... && bin/check-admin-coercion-patterns"
   ```
b. Add as a step in the admin SPA's GitHub Actions workflow (if separate).
c. Document in `packages/admin/README.md` under "CI" that this script is run manually via `./bin/check-admin-coercion-patterns` and in CI.

Check the existing `composer verify` definition before wiring. If `composer verify` already runs `cd packages/admin && npm run lint`, and `npm run lint` can be extended, add it there. Otherwise add to the `composer verify` scripts line directly.

**Validation**:
- [ ] `./bin/check-admin-coercion-patterns` exits 0 (no matches in migrated codebase).
- [ ] The script exits 1 if you temporarily add `String(x) === '1'` to any `.vue` file (manual spot-check).
- [ ] File is executable (`ls -la bin/check-admin-coercion-patterns` shows `x` bit).

---

### T015 — Create Playwright network-capture spec (FR-012)

**Purpose**: SC-002 — assert that a fresh `/admin/node` page load issues exactly one POST to `/admin/_surface/node/action/schema`. This is the integration-level proof that WP01's dedup works end-to-end in the browser, not just in unit tests.

**File**: `packages/admin/e2e/schema-dedup.spec.ts` (new)

**Implementation**:
```typescript
import { test, expect } from '@playwright/test'

/**
 * Regression spec for #1537: useSchema() concurrent fetch deduplication.
 *
 * Asserts that a fresh page load of /admin/{entityType} issues exactly one
 * POST to the schema endpoint, regardless of how many components call
 * useSchema().fetch() during mount.
 */
test.describe('useSchema() deduplication', () => {
  test('fresh /admin/node page load issues exactly one schema POST (SC-002)', async ({ page }) => {
    // Collect all requests to the schema endpoint
    const schemaRequests: string[] = []

    page.on('request', (request) => {
      if (
        request.method() === 'POST' &&
        request.url().includes('/_surface/') &&
        request.url().includes('/action/schema')
      ) {
        schemaRequests.push(request.url())
      }
    })

    // Navigate to the entity list page (triggers both the page wrapper and
    // SchemaList.vue to call useSchema('node').fetch() in onMounted)
    await page.goto('/admin/node')

    // Wait for the page to be fully loaded (network idle)
    await page.waitForLoadState('networkidle')

    // Assert exactly one schema POST was issued
    expect(schemaRequests).toHaveLength(1)
    expect(schemaRequests[0]).toContain('/admin/_surface/node/action/schema')
  })

  test('navigating back to /admin/node uses cache (no new POST)', async ({ page }) => {
    const schemaRequests: string[] = []

    page.on('request', (request) => {
      if (
        request.method() === 'POST' &&
        request.url().includes('/_surface/') &&
        request.url().includes('/action/schema')
      ) {
        schemaRequests.push(request.url())
      }
    })

    // First visit
    await page.goto('/admin/node')
    await page.waitForLoadState('networkidle')
    const afterFirstVisit = schemaRequests.length

    // Navigate away and back (SPA navigation, cache should persist)
    await page.goto('/admin')
    await page.goto('/admin/node')
    await page.waitForLoadState('networkidle')

    // Same count — no new POST on second visit (cache hit)
    expect(schemaRequests).toHaveLength(afterFirstVisit)
  })
})
```

**Auth setup**: The `/admin/node` route may redirect to login. Check whether the existing Playwright config (`packages/admin/playwright.config.ts`) has a `storageState` setup for authenticated sessions. If so, use `test.use({ storageState: 'e2e/.auth/user.json' })` at the top of the spec. If no auth setup exists yet:
1. Check `packages/admin/e2e/` for existing auth helper specs.
2. If none, add a `test.beforeAll` that logs in via the UI and saves storage state, OR stub the auth middleware in the test environment.
3. Document any auth setup added in the spec file.

**Dev server**: Playwright requires Nuxt to be running. Check `packages/admin/playwright.config.ts` for a `webServer` block:
```typescript
webServer: {
  command: 'npm run dev',
  url: 'http://localhost:3000',
  reuseExistingServer: !process.env.CI,
}
```
If missing, add it. This ensures `npm run test:e2e` starts the dev server automatically.

**Validation**:
- [ ] `cd packages/admin && npm run test:e2e` passes (both tests green).
- [ ] The first test asserts exactly 1 schema POST (not 2 — that was the bug).
- [ ] Dev server starts automatically if not already running.

---

### T016 — Add `CHANGELOG.md` `[Unreleased]` entries

**Purpose**: C-003 / mission close-out — record both fixes in the project changelog per the repo's changelog convention.

**File**: `CHANGELOG.md` (edit — add under `[Unreleased]`)

**Check the existing format** before editing. The changelog follows Keep a Changelog convention. Add under `## [Unreleased]`:

```markdown
### Fixed
- fix(admin): deduplicate concurrent `useSchema()` `fetch()` calls; a second concurrent call for the same entityType now returns the in-flight Promise instead of issuing a duplicate HTTP request (#1537)
- fix(admin): introduce `useAdminConfig()` typed composable to normalize Nuxt runtime-config digit-string coercion; remove `String(x) === '1'` workarounds from all SPA call sites (#1538)
```

If a `### Fixed` section already exists under `[Unreleased]`, append to it rather than creating a duplicate heading.

**Validation**:
- [ ] Two bullets added under `[Unreleased]` → `### Fixed`.
- [ ] Both issue numbers referenced (#1537, #1538).

---

### T017 — Final verification

**Purpose**: SC-001..SC-007 sign-off. All CI checks must be green before the final commit. This is the gate commit that closes #1537 and #1538.

**Verification sequence**:

```bash
# 1. Coercion gate
./bin/check-admin-coercion-patterns
# Expected: "OK: No coercion patterns found"

# 2. PHP/Composer verification (backend unaffected, but gate must pass)
composer verify
# Expected: all checks green

# 3. Admin SPA full suite
cd packages/admin

# TypeScript strict build
npm run build
# Expected: exit 0, zero errors

# Unit tests (includes useSchema dedup + useAdminConfig coercion tests)
npm test
# Expected: exit 0, all tests pass

# Playwright E2E (schema-dedup.spec.ts)
npm run test:e2e
# Expected: exit 0, schema-dedup tests pass

# Lint
npm run lint
# Expected: exit 0
```

**If any check fails**:
- TypeScript errors → fix the type issue in the relevant file, re-run.
- Unit test failure → diagnose the failing test; it may indicate a regression in WP01/WP02/WP03.
- E2E failure → check dev server startup, auth setup, and network interception in `schema-dedup.spec.ts`.
- Lint failure → apply auto-fix (`npm run lint -- --fix`) if available, or fix manually.
- Coercion gate failure → a missed call-site from WP03; grep for the match and migrate it.

**Final commit message**:
```
fix(admin): useSchema dedup + typed useAdminConfig envelope

- Add in-flight Promise tracker to useSchema() to deduplicate concurrent
  fetch() calls for the same entityType (FR-001..FR-003)
- Introduce useAdminConfig() typed composable with asBoolean/asString/asUrl
  coercion helpers (FR-004..FR-007)
- Migrate all 19 call-site files from useRuntimeConfig().public.* to
  useAdminConfig() (FR-006)
- Add bin/check-admin-coercion-patterns CI gate (NFR-004)
- Add Playwright schema-dedup regression spec (FR-012)

Closes #1537
Closes #1538
```

**Validation**:
- [ ] `./bin/check-admin-coercion-patterns` exits 0.
- [ ] `composer verify` exits 0.
- [ ] `cd packages/admin && npm run build` exits 0.
- [ ] `cd packages/admin && npm test` exits 0.
- [ ] `cd packages/admin && npm run test:e2e` exits 0.
- [ ] `cd packages/admin && npm run lint` exits 0.
- [ ] Final commit footer includes both `Closes #1537` and `Closes #1538`.

---

## Branch Strategy

- **Planning/base branch**: `main`
- **Merge target**: `main`
- **Depends on**: WP01, WP02, WP03 (Lane C — starts after all prior WPs are merged)
- This is the wrap-up WP; it finalizes the mission.

---

## Definition of Done

- [ ] `bin/check-admin-coercion-patterns` exists, is executable, exits 0 on the migrated codebase.
- [ ] `packages/admin/e2e/schema-dedup.spec.ts` exists and passes (`npm run test:e2e` green).
- [ ] Two `CHANGELOG.md` bullets added under `[Unreleased]` → `### Fixed`.
- [ ] All CI checks green: `composer verify`, `npm run build`, `npm test`, `npm run test:e2e`, `npm run lint`.
- [ ] Final commit footer includes `Closes #1537` and `Closes #1538`.
- [ ] GitHub issues #1537 and #1538 are auto-closed by the merge commit.

---

## Risks

| Risk | Mitigation |
|---|---|
| Playwright E2E requires auth — `/admin/node` redirects to login | Check existing `e2e/.auth/` storage state; add `test.use({ storageState })` if present; add login flow otherwise |
| Dev server `webServer` not configured in playwright.config.ts | Add `webServer` block pointing to `npm run dev` on port 3000; `reuseExistingServer: true` for local dev |
| `npm run test:e2e` does not exist in `packages/admin/package.json` | Check `package.json`; if the script is named differently (e.g., `playwright`), use that; add `"test:e2e": "playwright test"` if missing |
| `composer verify` includes a step that fails due to SPA changes | Run `composer verify` first; if it fails on unrelated PHP checks, diagnose separately |
| CHANGELOG already has conflicting entries from parallel work | Merge carefully; place the new bullets at the top of `### Fixed` |

---

## Reviewer Guidance

1. Run `./bin/check-admin-coercion-patterns` — must output "OK" and exit 0.
2. Run `cd packages/admin && npm run test:e2e` — the `schema-dedup.spec.ts` tests must appear and pass.
3. Verify the `schema-dedup.spec.ts` first test assertion: `schemaRequests` must have length 1 (not 2 — 2 was the pre-fix behavior).
4. Check `CHANGELOG.md` — two bullets under `[Unreleased]` → `### Fixed`, both referencing issue numbers.
5. Confirm the final commit's footer contains both `Closes #1537` and `Closes #1538`.

## Activity Log

- 2026-05-21T01:08:42Z – claude:sonnet:implementer:implementer – shell_pid=798647 – Started implementation via action command
