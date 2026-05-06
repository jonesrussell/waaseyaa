# Data Model: CSRF for Inertia File Uploads

**Mission**: `inertia-file-upload-csrf-01KQZJQJ`
**Phase**: 1 (design)

---

## Summary

**This mission introduces no new domain entities.** It ships HTTP-level infrastructure: a response-side cookie writer and an additional accepted header in the existing CSRF middleware. Existing entities (`User`, `Account`, session storage, etc.) are unaffected.

This document exists to make that explicit. The absence of a domain-model section in the plan is intentional, not an oversight.

---

## State changes (none persistent)

| Surface | Existing | After this mission |
|---|---|---|
| `$_SESSION['_csrf_token']` | 64-char hex string, generated lazily, rotated on auth boundary | Unchanged |
| Token storage backend | PHP session driver | Unchanged |
| CSRF token shape | `bin2hex(random_bytes(32))` (256 bits of entropy, 64 hex chars) | Unchanged |
| Rotation policy | On login/logout via `CsrfMiddleware::regenerate()` | Unchanged |
| Outgoing HTTP responses (HTML) | No CSRF-related cookie | New `Set-Cookie: XSRF-TOKEN=<urlencoded-token>; Path=/; SameSite=Lax[; Secure]` |
| Outgoing HTTP responses (JSON/API) | No CSRF cookie | Still no cookie (skip rule documented in contract) |
| Inbound CSRF token sources | `_csrf_token` POST field, `X-CSRF-Token` header | Above + `X-XSRF-TOKEN` header (URL-decoded before comparison) |

---

## Cookie value lifecycle

```
[User opens app]
  → first GET to any HTML route
    → CsrfMiddleware::token() lazily creates session token if absent
    → response carries Set-Cookie: XSRF-TOKEN=<urlencode(token)>

[User submits Inertia multipart form]
  → axios sees XSRF-TOKEN cookie, auto-sends X-XSRF-TOKEN: <urldecode(cookie)>
  → CsrfMiddleware reads X-XSRF-TOKEN, urldecodes, hash_equals against $_SESSION['_csrf_token']
  → match → 200/302
  → no match → 403

[User logs in or out]
  → CsrfMiddleware::regenerate() replaces $_SESSION['_csrf_token']
  → next HTML response carries Set-Cookie with the new token
  → axios picks up the new cookie automatically
```

No persistence beyond the existing session. No migration. No schema change.

---

## Entity dependencies

None.

---

## Relevant invariants (preserved by this mission)

- The session-side CSRF token is the single source of truth. Cookie + headers are only transports.
- Rotation is auth-bounded (login/logout), not per-request.
- `application/json` and `application/vnd.api+json` requests remain CSRF-exempt (FR-004).
- `_csrf_token` POST field continues to be accepted (FR-003).
- `X-CSRF-Token` header continues to be accepted (existing behavior).
