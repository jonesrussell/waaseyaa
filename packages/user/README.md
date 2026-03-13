# waaseyaa/user

**Layer 1 — Core Data**

User entity type and authentication middleware for Waaseyaa applications.

Defines the `user` entity type. `SessionMiddleware` reads `$_SESSION['waaseyaa_uid']` and sets `_account` on the request (anonymous via `AnonymousUser` with `id: 0` when no session). Includes `CsrfMiddleware` for form protection. `AnonymousUser` and `DevAdminAccount` (id: `PHP_INT_MAX`, gated to `cli-server` SAPI) are the system sentinel accounts.

Key classes: `User`, `AnonymousUser`, `SessionMiddleware`, `CsrfMiddleware`, `UserServiceProvider`.
