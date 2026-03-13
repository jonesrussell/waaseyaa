# waaseyaa/cli

**Layer 6 — Interfaces**

Command-line interface for Waaseyaa applications.

Provides Symfony Console commands for entity management (`entity:create`), configuration export/import (`config:export`, `config:import`), schema checking, health diagnostics, and the `optimize:manifest` command that runs `PackageManifestCompiler`. Entry point: `bin/waaseyaa`.

Key classes: `ConsoleKernel`, health and schema check commands.
