# HTTP Contract: CSRF Token Cookie + Accepted Headers

**Mission**: `inertia-file-upload-csrf-01KQZJQJ`
**Phase**: 1 (design)
**Binding**: tests in `packages/user/tests/Unit/Middleware/` and `tests/Integration/Phase13/InertiaMultipartCsrfIntegrationTest.php` MUST pin every observable behavior in this document.

---

## 1. Cookie write contract

### When the framework MUST set the cookie

On any response with a `Content-Type` whose primary type is `text/html` (case-insensitive, considering only the part before any `;`), the response MUST include exactly one `Set-Cookie` header for `XSRF-TOKEN` with the attributes below.

### When the framework MUST NOT set the cookie

- Responses with `Content-Type: application/json`, `application/vnd.api+json`, `application/octet-stream`, or any non-`text/html` primary type.
- Responses with no body / HEAD method responses.
- Responses where the cookie has already been set explicitly by an earlier middleware (idempotency; framework MUST NOT double-set).

### Cookie attributes (exact)

| Attribute | Value | Notes |
|---|---|---|
| Name | `XSRF-TOKEN` | Exact case. Inertia's axios looks for this name. |
| Value | `urlencode($_SESSION['_csrf_token'])` | URL-encoded. Server URL-decodes before comparison. |
| `HttpOnly` | absent / `false` | JavaScript MUST be able to read it. |
| `Secure` | `true` if request is HTTPS, `false` otherwise | Detected via the request's `X-Forwarded-Proto` honored by the existing trusted-proxy logic, falling back to `$_SERVER['HTTPS']`. |
| `SameSite` | `Lax` | Hard-coded. Don't make this configurable in this mission. |
| `Path` | `/` | App-wide. |
| `Domain` | _(unset)_ | Browser defaults to current host. |
| `Max-Age` / `Expires` | _(unset — session cookie)_ | Lives for the browser session, matches session token lifetime. |

### Reference Set-Cookie header

```
Set-Cookie: XSRF-TOKEN=<urlencoded-token>; Path=/; SameSite=Lax
```

Over HTTPS:

```
Set-Cookie: XSRF-TOKEN=<urlencoded-token>; Path=/; SameSite=Lax; Secure
```

---

## 2. Accepted CSRF token sources (request side)

For any state-changing method (`POST`, `PUT`, `PATCH`, `DELETE`) on a non-exempt Content-Type, the middleware MUST consider the following sources in order, accepting on the first that matches via `hash_equals`:

| Order | Source | Pre-comparison transform |
|---|---|---|
| 1 | `_csrf_token` POST field | _(none — read as-is)_ |
| 2 | `X-CSRF-Token` request header | _(none — read as-is)_ |
| 3 | `X-XSRF-TOKEN` request header | URL-decode (`urldecode()`) before comparison |

If **none** match, the middleware MUST return the existing 403 Invalid Security Token response.

The order above is "any-of" — the middleware MUST NOT prefer one source over another in a way that lets a wrong header override a correct field. All sources are tried; first match wins; if no match, reject.

### Exempt Content-Types (unchanged)

| Content-Type | Behavior |
|---|---|
| `application/json` | Exempt (existing) |
| `application/vnd.api+json` | Exempt (existing) |

All other Content-Types (including `multipart/form-data` and `application/x-www-form-urlencoded`) require a valid token from one of the three sources above.

---

## 3. Negative behavior contract

| Case | Expected response |
|---|---|
| `multipart/form-data` POST, no token in any source | 403 Invalid Security Token |
| `multipart/form-data` POST, `X-XSRF-TOKEN` set to wrong value | 403 Invalid Security Token |
| `multipart/form-data` POST, `X-XSRF-TOKEN` set to correctly URL-encoded current token | 200/302 (request authorized) |
| `multipart/form-data` POST, both `_csrf_token` field (correct) AND `X-XSRF-TOKEN` (wrong) | 200/302 (any-of rule; field wins) |
| `multipart/form-data` POST, `X-XSRF-TOKEN` correct but token has been rotated by login since | 403 (next HTML response carries new cookie; client must retry with fresh value) |
| `application/json` POST, no token anywhere | 200 (exempt, unchanged) |

---

## 4. Idempotency and ordering

- Setting the `XSRF-TOKEN` cookie on every HTML response is idempotent. Multiple calls within a single request lifecycle MUST collapse to one `Set-Cookie` header.
- The cookie write MUST happen after `CsrfMiddleware` has run (or as part of it on the response side), so the cookie always reflects the current session token, including immediately after a rotation in the same request.

---

## 5. Backward compatibility guarantees

- Existing `_csrf_token` POST field acceptance: **preserved**.
- Existing `X-CSRF-Token` header acceptance: **preserved**.
- Existing `application/json` exemption: **preserved**.
- Existing 403 response shape (status code, body): **preserved**.
- No new configuration is required for existing apps to pick up the new behavior. No opt-out is provided in this mission (consistent with the framework's "out of the box" principle).

---

## 6. Test pinning (every line above MUST have at least one test)

Test files responsible for pinning this contract:

- `packages/user/tests/Unit/Middleware/CsrfMiddlewareTest.php` — request-side contract (§2, §3, §5).
- `packages/user/tests/Unit/Middleware/XsrfCookieMiddlewareTest.php` _(only if separate middleware path chosen)_ — cookie-write contract (§1, §4).
- `tests/Integration/Phase13/InertiaMultipartCsrfIntegrationTest.php` — end-to-end (§1+§2+§3 through full kernel).
