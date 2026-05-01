# waaseyaa/oauth-provider

**Layer 0 — Foundation**

OAuth 2.0 provider abstraction for Waaseyaa applications.

`OAuthProviderInterface` is the per-IdP contract (Google, GitHub, Apple, etc.); `ProviderRegistry` resolves a registered provider by name. `OAuthStateManager` issues and validates anti-CSRF state tokens via `SessionInterface`; `OAuthToken` and `OAuthUserProfile` are value objects carried through the OAuth callback. Concrete providers live under `Provider/` and consumer apps register additional ones at boot.

Key classes: `OAuthProviderInterface`, `ProviderRegistry`, `OAuthStateManager`, `OAuthToken`, `OAuthUserProfile`.
