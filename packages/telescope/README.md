# waaseyaa/telescope

**Layer 6 — Interfaces**

Request inspection and observability dashboard for Waaseyaa applications.

Telescope captures HTTP requests, responses, database queries, cache operations, and queue jobs for local development debugging. Rendered as an SSR Twig page at `/_telescope`. See `docs/specs/` for the context observability integration spec.

Key classes: `TelescopeRecorder`, `TelescopeServiceProvider`.
