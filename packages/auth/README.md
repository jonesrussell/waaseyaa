# waaseyaa/auth

**Layer 1 — Core Data**

Headless authentication for Waaseyaa: login, registration, 2FA, password reset.

Owns `AuthManager` for credential verification and session establishment, `TwoFactorManager` for TOTP enrollment and verification, `RateLimiterInterface` plus `DatabaseRateLimiter` for per-account throttling, and the controller surface for password-reset and email-verification flows. Route registration for `/api/auth/*` lives in `Waaseyaa\Routing\AuthOidcRouteServiceProvider` so this package stays free of L4 routing imports.

Key classes: `AuthManager`, `AuthServiceProvider`, `TwoFactorManager`, `RateLimiterInterface`, `DatabaseRateLimiter`.
