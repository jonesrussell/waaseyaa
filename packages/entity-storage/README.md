# waaseyaa/entity-storage

**Layer 1 — Core Data**

SQL and in-memory entity storage for Waaseyaa applications.

`SqlEntityStorage` persists entities to a relational database using a `_data` JSON blob for non-schema columns. `SqlSchemaHandler` generates and synchronizes column definitions. `InMemoryEntityStorage` (in `waaseyaa/api/tests/Fixtures/`) provides a fast in-memory store for unit tests. Entity values are split on write and merged on read via `splitForStorage()` / `mapRowToEntity()`.

Key classes: `SqlEntityStorage`, `SqlSchemaHandler`, `EntityStorageInterface`.
