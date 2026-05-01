# waaseyaa/inertia

**Layer 6 — Interfaces**

Server-side Inertia.js v3 protocol adapter for Waaseyaa.

`Inertia::render($component, $props)` produces an `InertiaResponse` that distinguishes initial full-page loads (HTML root template) from XHR navigation (JSON payload). `InertiaMiddleware` reads the `X-Inertia-*` request headers and switches the response shape accordingly. `OptionalProp` and `PropResolver` defer expensive prop computation when partial reloads only request specific keys.

Key classes: `Inertia`, `InertiaResponse`, `InertiaMiddleware`, `InertiaServiceProvider`, `OptionalProp`, `PropResolver`.
