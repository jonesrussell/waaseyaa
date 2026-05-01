# waaseyaa/http-client

**Layer 0 — Foundation**

Minimal HTTP client for JSON APIs and webhooks.

`HttpClientInterface` defines the GET/POST/PUT/DELETE/PATCH surface; `StreamHttpClient` is the production implementation backed by PHP streams. Returns `HttpResponse` value objects (no shared state) and throws `HttpRequestException` on transport failures. Designed as an injectable seam — tests replace `HttpClientInterface` with a fake rather than mocking PHP's stream layer.

Key classes: `HttpClientInterface`, `StreamHttpClient`, `HttpResponse`, `HttpRequestException`.
