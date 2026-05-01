# waaseyaa/mercure

**Layer 0 — Foundation**

Mercure hub publisher for real-time SSE push in Waaseyaa.

`MercurePublisher` posts updates to a configured Mercure hub URL with topic and JWT credentials. Publication failures are best-effort — they wrap exceptions through `LoggerInterface` rather than crashing the primary request, matching the framework convention for non-critical side effects.

Key classes: `MercurePublisher`, `MercureServiceProvider`.
