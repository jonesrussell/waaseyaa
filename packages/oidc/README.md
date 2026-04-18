# waaseyaa/oidc

OpenID Connect issuer for the Waaseyaa ecosystem.

This package provides the authorization-server primitives used by a dedicated IdP app to act as the single sign-on provider for every Waaseyaa app (Giiken, Minoo, OIATC, NorthOps, etc.). Consumer apps do **not** install this package — they federate to the IdP via [`waaseyaa/oauth-provider`](../oauth-provider/)'s `GenericOidcProvider`.

## Scope

- Authorization endpoint (`/authorize`)
- Token endpoint (`/token`)
- UserInfo endpoint (`/userinfo`)
- Discovery (`/.well-known/openid-configuration`)
- JWKS (`/.well-known/jwks.json`)
- Revocation (`/revoke`)
- RP-initiated logout (`/end_session`)
- Signing-key storage + rotation

## Non-goals (v1)

- Multi-tenant realms
- Dynamic client registration (RFC 7591)
- SCIM provisioning
- Federation chaining

See [ADR-006](../../docs/adr/006-cross-app-identity-via-oidc.md) for full context, invariants, and migration plan.

## Status

**Scaffold only.** Implementation lands in follow-up PRs, TDD order per ADR-006 §7: discovery → JWKS → authorization code flow → token → userinfo → revocation → logout.

## Stack

- [`league/oauth2-server`](https://oauth2.thephpleague.com/) — OAuth 2.0 authorization server
- [`lcobucci/jwt`](https://github.com/lcobucci/jwt) — ID token JWT assembly

## License

GPL-2.0-or-later.
