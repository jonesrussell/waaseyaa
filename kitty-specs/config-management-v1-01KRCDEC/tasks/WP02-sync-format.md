---
work_package_id: WP02
title: Sync-store YAML format + serializer/deserializer + repository
dependencies:
- WP01
requirement_refs:
- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
- FR-014
- FR-015
- FR-016
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During
  /spec-kitty.implement this WP may branch from a dependency-specific base, but completed
  changes must merge back into main unless the human explicitly redirects the landing
  branch.
base_branch: main
base_commit: 8f2f2c483d1819983bb56e654278d41bc2c76d57
created_at: '2026-05-16T00:00:00+00:00'
subtasks:
- T008
- T009
- T010
- T011
- T012
- T013
- T014
- T015
shell_pid: "70271"
history: []
authoritative_surface: packages/config/
execution_mode: code_change
owned_files:
- packages/config/src/Sync/ConfigSyncFile.php
- packages/config/src/Sync/ConfigSyncRepository.php
- packages/config/src/Sync/ConfigSyncSerializer.php
- packages/config/src/Sync/ConfigSyncDeserializer.php
- packages/config/src/Sync/FieldValueMapper.php
- packages/config/src/Sync/ConfigManifestEntry.php
- packages/config/src/Exception/ConfigSerializationException.php
- packages/config/tests/Contract/ConfigSyncRepositoryContractTest.php
- packages/config/tests/Unit/Sync/ConfigSyncFileTest.php
- packages/config/tests/Unit/Sync/ConfigSyncSerializerTest.php
- packages/config/tests/Unit/Sync/ConfigSyncDeserializerTest.php
- packages/config/tests/Unit/Sync/FieldValueMapperTest.php
- packages/config/tests/Unit/Sync/ConfigManifestEntryTest.php
- packages/config/tests/Unit/Exception/ConfigSerializationExceptionTest.php
agent: "claude:sonnet:python-implementer:implementer"
---

# Work Package Prompt: WP02 — Sync-store YAML format + serializer/deserializer + repository

## Mission context

- **Mission:** M-003 — Configuration Management v1 — Active/Sync Store Split (`config-management-v1-01KRCDEC`)
- **Spec:** [`../spec.md`](../spec.md) §3 (FRs), §8 (WP table), §5 (sync-store format)
- **Plan:** [`../plan.md`](../plan.md)
- **Governing ADR:** ADR 018 (CMI active/sync split, accepted 2026-05-11)

## Summary

Ship the sync-store YAML format on stable surface: `<entity_type>.<entity_id>.yml` files with a `_meta` block (`entity_type`, `uuid`, `dependencies`, `langcode`) and alphabetically-sorted field values. Implement `ConfigSyncFile` value object, `ConfigSyncRepository` (filesystem I/O under `storage/config-sync/`, configurable via `config.sync_path`), serializer/deserializer pair, and `FieldValueMapper` covering the spec §5.3 type table. Deterministic UUID fallback per research §10.3.

## Requirements covered

- FR-009
- FR-010
- FR-011
- FR-012
- FR-013
- FR-014
- FR-015
- FR-016

## Dependencies

This WP depends on: WP01.

## Subtasks

- T008 — Implement `ConfigSyncFile` value object (entity_type, entity_id, meta block, field-values map) (FR-009, FR-010).
- T009 — Implement `ConfigSyncSerializer` (entity → YAML, sorted keys, block-style multiline, deterministic output) (FR-011, FR-016).
- T010 — Implement `ConfigSyncDeserializer` (YAML → array; validates `_meta.entity_type` matches filename prefix) (FR-013).
- T011 — Implement `FieldValueMapper` covering the spec §5.3 type table (string, int, bool, datetime, json, text, uuid, entity_reference, field_list) (FR-011, FR-012).
- T012 — Implement `ConfigSyncRepository` (filesystem I/O under default `storage/config-sync/`, configurable via `config.sync_path`; warn-and-skip on files outside the naming convention) (FR-014, FR-015).
- T013 — Implement `ConfigManifestEntry` (derived from sync file: meta + path + hash) (FR-010).
- T014 — Implement `ConfigSerializationException` for filename↔`_meta.entity_type` mismatches and similar (FR-013).
- T015 — Contract + unit tests: round-trip per type, deterministic-emit snapshot, naming-rule enforcement, deterministic UUID fallback per research §10.3.

## Owned files

- `packages/config/src/Sync/ConfigSyncFile.php`
- `packages/config/src/Sync/ConfigSyncRepository.php`
- `packages/config/src/Sync/ConfigSyncSerializer.php`
- `packages/config/src/Sync/ConfigSyncDeserializer.php`
- `packages/config/src/Sync/FieldValueMapper.php`
- `packages/config/src/Sync/ConfigManifestEntry.php`
- `packages/config/src/Exception/ConfigSerializationException.php`
- `packages/config/tests/Contract/ConfigSyncRepositoryContractTest.php`
- `packages/config/tests/Unit/Sync/ConfigSyncFileTest.php`
- `packages/config/tests/Unit/Sync/ConfigSyncSerializerTest.php`
- `packages/config/tests/Unit/Sync/ConfigSyncDeserializerTest.php`
- `packages/config/tests/Unit/Sync/FieldValueMapperTest.php`
- `packages/config/tests/Unit/Sync/ConfigManifestEntryTest.php`
- `packages/config/tests/Unit/Exception/ConfigSerializationExceptionTest.php`

## Acceptance

- All listed FRs covered by tests within this WP's owned files.
- `composer phpstan` (level 5) green; `composer cs-check` clean.
- `bin/check-package-layers` green (no upward `waaseyaa/*` edges introduced).
- No modifications outside `owned_files` (other than rerun-of-generators where charter explicitly permits).

## Activity Log

- 2026-05-16T23:47:22Z – claude:sonnet:python-implementer:implementer – shell_pid=70271 – Started implementation via action command
- 2026-05-16T23:59:16Z – claude:sonnet:python-implementer:implementer – shell_pid=70271 – WP02 ready: ConfigSyncFile + ConfigSyncSerializer + ConfigSyncDeserializer + FieldValueMapper + ConfigSyncRepository + ConfigManifestEntry + ConfigSerializationException. Snapshot test on canonical role.coordinator fixture is byte-stable; round-trip through serializer+deserializer preserves content hash. Symfony Yaml options pinned (DUMP_MULTI_LINE_LITERAL_BLOCK, indent=2). Deterministic UUID v5 via sha-256(entity_type.entity_id). All FRs FR-009..FR-016 covered. 306 config tests pass; phpstan, cs-check, composer-policy, package-layers all green.
