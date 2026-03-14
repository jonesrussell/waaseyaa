# waaseyaa/api

**Layer 4 — API**

JSON:API endpoint layer for Waaseyaa applications.

Provides `JsonApiController` with CRUD patterns, `ResourceSerializer` for entity-to-JSON:API serialization (with optional field-level access filtering), `SchemaPresenter` for JSON Schema output, and `DiscoveryApiHandler` for resource discovery. Access is enforced via route options processed by `AccessChecker`.

Key classes: `JsonApiController`, `ResourceSerializer`, `SchemaPresenter`, `DiscoveryApiHandler`.
