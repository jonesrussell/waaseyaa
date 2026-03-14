# waaseyaa/state

**Layer 0 — Foundation**

Application state management for Waaseyaa.

Provides a key-value state store for cross-request application state that doesn't belong in the config system (e.g. runtime flags, feature toggles, install status). Backed by the database or a simple file store.

Key classes: `StateInterface`, `MemoryState`, `SqlState`.
