# waaseyaa/routing

**Layer 4 — API**

HTTP routing for Waaseyaa applications.

Wraps Symfony Routing with a `RouteBuilder` fluent API and adds route-level access options (`_public`, `_permission`, `_role`, `_gate`) evaluated by `AccessChecker`. Includes language negotiation middleware (`UrlPrefixNegotiator`, `AcceptHeaderNegotiator`).

Key classes: `RouteBuilder`, `Router`, `AccessChecker`.
