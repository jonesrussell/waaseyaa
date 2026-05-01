# waaseyaa/debug

**Layer 6 — Interfaces**

Debug toolbar, dump helpers, and dev-only diagnostics for Waaseyaa.

`DebugToolbarMiddleware` injects a development-only toolbar on HTML responses showing routing, query, and event traces; `ErrorPreviewController` renders any captured exception in a sandbox page for designer review. All entry points refuse to wire in production environments — the kernel boot guard rejects `APP_DEBUG=true` outside `APP_ENV=local`.

Key classes: `DebugServiceProvider`, `DebugToolbarMiddleware`, `ErrorPreviewController`.
