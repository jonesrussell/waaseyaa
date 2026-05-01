# waaseyaa/analytics

**Layer 0 — Foundation**

Shared Umami analytics integration for Waaseyaa apps.

Provides a server-side `UmamiClient` that proxies pageview and event records through the host server (avoiding browser-side ad-blocker collisions) and a tiny JS helper for client-side event reporting. No tracking scripts are loaded automatically — hosts opt in by injecting the bundled Twig partial and configuring the Umami URL.

Key classes: `UmamiClient`.
