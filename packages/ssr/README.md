# waaseyaa/ssr

**Layer 6 — Interfaces**

Server-side rendering layer for Waaseyaa applications.

Renders entity and page content as HTML using Twig templates. `SsrPageHandler` handles path alias resolution, editorial visibility checks, language negotiation, and cache headers. `RenderController` resolves template candidates (entity-specific, path-based, or fallback). `ThemeServiceProvider` manages the Twig environment with a theme chain loader. `EntityRenderer` produces field bags consumed by entity templates.

Twig functions: `asset()`, `env()`, `config()` (when wired), `csrf_token()` (when User middleware present).

Key classes: `SsrPageHandler`, `RenderController`, `ThemeServiceProvider`, `EntityRenderer`, `WaaseyaaExtension`.
