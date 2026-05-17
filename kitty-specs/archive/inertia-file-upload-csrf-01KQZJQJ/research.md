# Research: CSRF for Inertia File Uploads

**Mission**: `inertia-file-upload-csrf-01KQZJQJ`
**Phase**: 0 (research)
**Status**: complete — no `[NEEDS CLARIFICATION]` markers remain

---

## Decision 1 — Mechanism for exposing the CSRF token to JavaScript

**Decision**: **Approach A** — set an `XSRF-TOKEN` cookie on HTML responses and accept `X-XSRF-TOKEN` as an additional valid CSRF header.

**Rationale**:

- The framework's existing `CsrfMiddleware` already accepts an `X-CSRF-Token` header, but the token has no JS-reachable surface, so clients cannot populate that header.
- Inertia's axios client auto-forwards an `XSRF-TOKEN` cookie as `X-XSRF-TOKEN` on every state-changing request without any consumer-side JS code. This satisfies FR-008 (zero consumer-side wiring) for the dominant consumer pattern.
- Vanilla-fetch consumers can read the same cookie via `document.cookie` and send it as `X-XSRF-TOKEN`. One mechanism, two audiences.
- Server-rendered Twig templates already have `CsrfMiddleware::token()` for hidden form fields. They don't need a cookie or meta tag.
- The Laravel/Inertia community has battle-tested this exact convention for years, including with multipart uploads.

**Alternatives considered**:

- **Approach B (Inertia shared prop only)**: Rejected. Requires every consumer form to read and forward the prop manually. Violates FR-008.
- **Approach C (hybrid: cookie + meta tag or shared prop)**: Rejected. The cookie already serves both Inertia and vanilla-fetch consumers. A meta tag would only matter for a third audience that doesn't exist in the framework's current consumer base. Adding it now is speculative complexity.
- **Per-route CSRF exemptions**: Out of scope per spec C-002.
- **Switch to double-submit-cookie pattern entirely (drop session-side token)**: Out of scope; would change rotation policy (C-003).

---

## Decision 2 — Cookie attributes

**Decision**: see [contracts/csrf-token-cookie.md](./contracts/csrf-token-cookie.md) for the binding contract. Summary:

| Attribute | Value | Reasoning |
|---|---|---|
| Name | `XSRF-TOKEN` | Inertia/Laravel convention; Inertia's axios looks for this exact name. |
| Value | URL-encoded session CSRF token | Laravel encodes; Inertia's axios decodes during forwarding. We follow suit so consumers don't double-decode. |
| HttpOnly | `false` | Required: JavaScript must read it. |
| Secure | matches request scheme | Don't break HTTP local dev; don't leak over HTTP in production. |
| SameSite | `Lax` | Sufficient for same-origin posts (including Inertia). `Strict` would break cross-page navigations carrying the cookie. `None` weakens CSRF without benefit. |
| Path | `/` | App-wide. |
| Domain | _(unset)_ | Browser defaults to current host; avoids cross-subdomain leakage. |
| Max-Age / Expires | _(session)_ | Matches session token lifetime. |

**Alternatives considered**:

- **`HttpOnly=true`**: Defeats the entire purpose. Rejected.
- **`SameSite=Strict`**: Breaks navigation flows in some Inertia patterns. Rejected.
- **`SameSite=None; Secure`**: Only needed for cross-site POSTs, which we don't support and which would weaken the protection. Rejected.
- **Plain (non-URL-encoded) value**: Would require Inertia consumers to override axios behavior. Rejected.

---

## Decision 3 — Token comparison logic

**Decision**: `hash_equals()` against the URL-decoded value of `X-XSRF-TOKEN`, with the existing `_csrf_token` POST field and `X-CSRF-Token` header paths preserved unchanged. Order of precedence is "any-of" (first match wins, all sources compared via `hash_equals`).

**Rationale**: Constant-time comparison is the existing pattern; we preserve it for the new path. URL-decode happens once on the server before comparison so both pre-existing and new sources compare against the canonical session value.

**Alternatives considered**:

- **Replace `_csrf_token` with header-only**: Breaking change to existing forms. Rejected (C-002 spirit + FR-003).
- **Strict precedence (header overrides field, etc.)**: Adds complexity without observable benefit. Rejected.

---

## Decision 4 — Where the cookie writer lives in the middleware stack

**Decision**: Resolve during implement. Two acceptable shapes:

- **(a)** Extend `CsrfMiddleware` to write the cookie on its response path.
- **(b)** Introduce a separate `XsrfCookieMiddleware` that runs adjacent to `CsrfMiddleware` and only handles the response-side cookie write.

Implementer reads `packages/foundation/src/Kernel/HttpKernel.php` and the existing middleware contract first to decide. Both lead to identical observable behavior.

**Rationale for deferring**: The Explore pass confirmed middleware are priority-sorted but did not confirm whether the contract is onion-style (`$next($request)` + modify on the way out) or sequential. Either contract supports both shapes; the cleanest fit depends on the existing pattern.

**Alternatives considered**:

- **Write the cookie in `HttpKernel` itself**: Rejected. Couples kernel to CSRF concerns the user package owns.
- **Write the cookie in a controller helper**: Rejected. Would require every controller to opt in. Violates the framework's "out of the box" principle.

---

## Decision 5 — Integration test harness

**Decision**: Use the existing `tests/Integration/Phase13/SsrHttpKernelIntegrationTest.php` pattern. Add a new file `tests/Integration/Phase13/InertiaMultipartCsrfIntegrationTest.php` that:

1. Boots the full `HttpKernel`.
2. Issues a GET to a CSRF-protected route, observes the `Set-Cookie: XSRF-TOKEN=...` header in the response.
3. Issues a multipart POST to the same route with `X-XSRF-TOKEN` set to the URL-decoded cookie value, asserts 200/302.
4. Issues the same POST without the header, asserts 403.

**Rationale**: The existing harness already proves the kernel-boot pattern works. We extend it instead of inventing a new harness.

---

## Decision 6 — Cross-repo smoke procedure

**Decision**: see [quickstart.md](./quickstart.md) §5. Procedure uses Composer path repository in Giiken, runs the real Ingestion upload, captures evidence into `artifacts/`, and reverts the path-repo config.

**Rationale**: The hard acceptance gate (C-005, SC-4) requires the fix to actually reach the consumer surface. A path repo is the lightest reliable way to wire a local framework build into Giiken without publishing an alpha tag mid-mission.

**Alternatives considered**:

- **Publish a throwaway alpha tag**: Heavier, slower, pollutes Packagist. Rejected for in-mission verification (re-run after release on a tagged build is acceptable post-merge).
- **Manual `cp` of vendor files**: Brittle, inconsistent. Rejected.

---

## Decision 7 — Documentation surface

**Decision**: Add new file `docs/conventions/csrf-token-cookie.md`. Update `docs/specs/security-defaults.md` to link to it from the CSRF section.

**Rationale**: The convention is reusable and audience-spanning (Inertia + fetch + Twig). It deserves a standalone page rather than burial in `security-defaults.md`. The existing security doc adds a one-line pointer.

**Alternatives considered**:

- **Inline into `security-defaults.md` only**: Rejected. The convention page is referenced from the framework's broader docs and from the spec/plan; standalone is cleaner.
- **ADR**: Rejected for the convention itself (which is a pattern, not a one-time decision). The mission spec + plan already serve the ADR role here.

---

## Open questions

None at end of Phase 0. All decisions captured above.
