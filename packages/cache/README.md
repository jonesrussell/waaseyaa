# waaseyaa/cache

**Layer 0 — Foundation**

Cache abstraction for Waaseyaa applications.

Provides a backend-agnostic `CacheInterface` with a `MemoryBackend` for tests and a filesystem backend for production. Supports atomic file writes (write-to-temp-then-rename) to prevent partial reads. Also includes `CacheConfigResolver` for cache-control header computation.

Key classes: `CacheInterface`, `MemoryBackend`, `FilesystemBackend`, `CacheConfigResolver`.
