# waaseyaa/config

**Layer 1 — Core Data**

Configuration management for Waaseyaa applications.

Provides `ConfigFactoryInterface`, `ConfigInterface`, and a file-backed `ConfigFactory` that reads YAML/PHP config files from a sync directory. `MemoryStorage` is available for tests. Config keys follow a `config_name.key.path` dot-notation convention.

Key classes: `ConfigFactoryInterface`, `ConfigFactory`, `MemoryStorage`.
