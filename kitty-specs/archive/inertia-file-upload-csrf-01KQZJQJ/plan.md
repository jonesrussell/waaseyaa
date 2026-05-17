# Implementation Plan: CSRF for Inertia File Uploads

**Mission ID**: `01KQZJQJV8XMG9C1PF7TVMKKHE` (mid8: `01KQZJQJ`)
**Mission Slug**: `inertia-file-upload-csrf-01KQZJQJ`
**Spec**: [spec.md](./spec.md)
**Branch contract**: current `main` → planning base `main` → merge target `main` (matches: yes)
**Date**: 2026-05-06

---

## Summary

Inertia file uploads (`multipart/form-data` via `forceFormData: true`) currently fail against any CSRF-protected route in the Waaseyaa framework with 403 "Invalid Security Token". The root cause is that the framework's CSRF token has no JS-reachable surface — it lives only in `$_SESSION['_csrf_token']`. This plan adds a single mechanism that closes the gap for both the Inertia and vanilla-JS audiences with zero consumer-side wiring: an `XSRF-TOKEN` cookie set on HTML responses, plus acceptance of `X-XSRF-TOKEN` as an additional valid CSRF header. The convention mirrors Laravel/Inertia exactly so consumer apps following standard Inertia patterns get the fix for free.

---

## Engineering Alignment

- **Mechanism chosen**: **Approach A** — `XSRF-TOKEN` cookie + `X-XSRF-TOKEN` accepted header.
- **Approach C (hybrid) rejected**: The cookie itself is the JS-readable surface for both audiences. Inertia's axios auto-forwards the cookie to `X-XSRF-TOKEN`. Vanilla-fetch consumers read the cookie via `document.cookie`. Server-rendered Twig templates already have `CsrfMiddleware::token()` for hidden fields. A meta tag would duplicate the cookie's role for an audience that doesn't exist today.
- **Approach B (shared prop only) rejected**: Violates FR-008 (zero consumer-side wiring) — every form would need explicit token plumbing.

---

## Technical Context

**Language/Version**: PHP 8.4+ (framework requirement); test suite TypeScript optional via Vitest in some packages.
**Primary Dependencies**: Symfony HttpFoundation (already used by `packages/foundation`). No new composer packages.
**Storage**: Existing PHP `$_SESSION` driver; no change.
**Testing**: PHPUnit 10.5+ with `#[Test]` and `#[CoversClass]` attributes. Existing integration harness at `tests/Integration/Phase13/SsrHttpKernelIntegrationTest.php` reusable.
**Target Platform**: Server-side PHP, any Linux/macOS host the framework already supports.
**Project Type**: Framework monorepo (`packages/*` layout).
**Performance Goals**: NFR-001 — added overhead <1 ms p95 per HTML response.
**Constraints**: No new composer dependencies; no consumer-side code changes; no rotation/session driver changes.
**Scale/Scope**: ~3 framework packages touched (`user`, `foundation`, optionally `inertia`); 2–4 docs files; 2 new test files; 1 modified test file.

### Existing surfaces (mapped from code, not assumed)

| Surface | Location | Current behavior |
|---|---|---|
| `CsrfMiddleware` | `packages/user/src/Middleware/CsrfMiddleware.php` (priority 20, pipeline `http`) | Exempts `application/json` and `application/vnd.api+json`. Reads token from `$_POST['_csrf_token']` and `X-CSRF-Token` header. Stores in `$_SESSION['_csrf_token']`. |
| Token issuance | Same file, `CsrfMiddleware::token()` (static) | `bin2hex(random_bytes(32))` → 64 hex chars. Lazily created; rotated only on login/logout via `regenerate()`. |
| `HttpKernel` | `packages/foundation/src/Kernel/HttpKernel.php` | Priority-sorted middleware; pipeline runs through `ControllerDispatcher::dispatch()` returning `HttpResponse`. |
| HTTP abstraction | `packages/foundation/src/Http/` (Symfony HttpFoundation underneath) | `Response->headers->setCookie(...)` is the structured cookie path. |
| Inertia | `packages/inertia/` (`InertiaMiddleware`, `Inertia::share()`, `InertiaResponse`) | Real integration. Header-mismatch handling and shared-prop registry already present. |
| Non-Inertia HTML | `packages/ssr/` (Twig SSR) + skeleton | Twig templates can call `CsrfMiddleware::token()` for hidden fields. |
| CSRF unit tests | `packages/user/tests/Unit/Middleware/CsrfMiddlewareTest.php` | PHPUnit `#[Test]` style. |
| Integration harness | `tests/Integration/Phase13/SsrHttpKernelIntegrationTest.php` | Boots full `HttpKernel`, dispatches through full middleware. Reusable. |

### Open implementation question (decidable in implement, not blocking)

- **Onion vs. sequential middleware contract**: Plan does not pre-commit between (a) extending `CsrfMiddleware` to write the cookie on its own response path or (b) introducing a separate `XsrfCookieMiddleware`. Implementer reads `HttpKernel::handle()` and the existing middleware contract before choosing. Both options land identical observable behavior.

---

## Charter Check

*GATE: Must pass before Phase 0. Re-check after Phase 1.*

| Charter Anchor | Status | Notes |
|---|---|---|
| Project Charter | ✅ | Mission scope aligns with the framework's "works out of the box for convention-following consumers" DX principle. |
| Testing Standards | ✅ | PHPUnit `#[Test]` style preserved; new tests under `packages/user/tests/Unit/` and `tests/Integration/`. |
| Quality Gates | ✅ | `composer test` and `composer analyse` must remain green; NFR-004 zero-new-warnings. |
| Performance Benchmarks | ✅ | NFR-001 caps added overhead <1 ms p95 per HTML response. |
| Branch Strategy | ✅ | `main` → `main`, `branch_matches_target=true` (from `setup-plan` JSON). |
| DIR-001/002/003 | ✅ | No directive conflicts identified. Implementer to re-check at WP boundaries per spec-kitty doctrine. |

Re-check after Phase 1 design: still ✅. No new violations introduced by the artifact set.

---

## Project Structure

### Mission documentation

```
kitty-specs/inertia-file-upload-csrf-01KQZJQJ/
├── spec.md                          # Done (specify phase)
├── plan.md                          # This file
├── research.md                      # Phase 0 output
├── data-model.md                    # Phase 1 output
├── quickstart.md                    # Phase 1 output
├── contracts/
│   └── csrf-token-cookie.md         # Phase 1 output (HTTP contract)
├── checklists/
│   └── requirements.md              # From specify phase, all-pass
├── artifacts/                       # Cross-repo smoke evidence (filled at completion)
└── tasks/                           # /spec-kitty.tasks output (NOT generated here)
```

### Code surfaces touched (concrete, not generic)

```
packages/user/
├── src/Middleware/
│   ├── CsrfMiddleware.php                  # Modified: accept X-XSRF-TOKEN header
│   └── XsrfCookieMiddleware.php            # New (or merged into CsrfMiddleware on response side)
└── tests/Unit/Middleware/
    ├── CsrfMiddlewareTest.php              # Extended: full Content-Type × source matrix
    └── XsrfCookieMiddlewareTest.php        # New (only if separate middleware path chosen)

packages/foundation/                        # No file changes expected; verify middleware ordering allows cookie write on response

tests/Integration/Phase13/
└── InertiaMultipartCsrfIntegrationTest.php # New: end-to-end multipart with X-XSRF-TOKEN

docs/
├── specs/security-defaults.md              # Updated: link out to convention page
└── conventions/csrf-token-cookie.md        # New: convention page (Inertia, fetch, Twig examples)
```

**Structure Decision**: Framework monorepo with `packages/*`. Touch surface kept narrow: `packages/user` plus an integration test and docs. `packages/foundation` and `packages/inertia` are read-only for this mission unless implementer discovers a needed hook (in which case the change should be a clean response-side extension, not a refactor).

---

## Complexity Tracking

No charter violations. Complexity table not required.

---

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Cookie added to every HTML response increases bytes | Skip cookie write when response Content-Type is JSON/non-HTML. Negligible for HTML responses. |
| `HttpOnly=false` increases XSS impact surface | Same trade-off Laravel/Inertia have shipped for years. Cookie value is a CSRF token, not a session credential; XSS that reads it could already forge requests anyway. SameSite=Lax + first-party-only Domain limits exposure. |
| URL-encoding mismatch between Laravel convention and our impl | Tests pin: cookie value is URL-encoded; header comparison URL-decodes before `hash_equals`. Convention page documents the encoding. |
| Existing `X-CSRF-Token` path drift while editing | Tests pin existing behavior; no refactor of the existing acceptance path in this mission. |
| Onion vs. sequential middleware assumption is wrong | Implementer reads `HttpKernel::handle()` first; both paths land the same observable behavior. |
| Cross-repo smoke is brittle (path-repo + composer) | Procedure documented step-by-step in plan + quickstart with revert step; smoke evidence is captured before revert. |

---

## Final branch contract restatement

- Current branch at plan time: `main`
- Planning/base branch: `main`
- Final merge target for completed changes: `main`
- `branch_matches_target`: **true**

Worktree(s) for implementation will be created during `/spec-kitty.implement` from `main` (one per execution lane, per spec-kitty 0.11+ contract).

---

## Phase 0 Research

See [research.md](./research.md). Three open questions resolved:

1. **A vs C (mechanism)** → A (justified by current consumer surface).
2. **Cookie attributes** → fixed values in [contracts/csrf-token-cookie.md](./contracts/csrf-token-cookie.md).
3. **Cross-repo smoke procedure** → fixed steps in [quickstart.md](./quickstart.md).

No `[NEEDS CLARIFICATION]` markers remain.

## Phase 1 Design

- [data-model.md](./data-model.md): No new domain entities. This mission ships HTTP infrastructure.
- [contracts/csrf-token-cookie.md](./contracts/csrf-token-cookie.md): HTTP contract for the cookie write and accepted-header sources.
- [quickstart.md](./quickstart.md): Developer-facing walkthrough (Inertia, vanilla fetch, Twig, smoke procedure).

---

## Next step

Run `/spec-kitty.tasks` to break this plan into work packages. Implementer agent will be **Sonnet**; reviewer will be **Opus** (per recorded user preference).
