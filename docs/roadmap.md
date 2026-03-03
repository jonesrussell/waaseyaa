# Waaseyaa Roadmap

## Access Control Pipeline

The access control system has four layers, being built bottom-up:

| # | Layer | Status | Description |
|---|-------|--------|-------------|
| 1 | Entity access policies | Done | `NodeAccessPolicy`, `TermAccessPolicy`, `ConfigEntityAccessPolicy` wired into `EntityAccessHandler` |
| 2 | Gate wiring | Done | `EntityAccessGate` instantiated with policies, passed to `AccessChecker` in `index.php` |
| 3 | Route-level `_gate` options | Done | `_gate` attached to entity CRUD routes; `AccessChecker` rejects at the route level |
| 4 | Uncovered entity policies | Done | `UserAccessPolicy`, `MediaAccessPolicy` added; `path_alias`, `menu`, `menu_link` covered by `ConfigEntityAccessPolicy` |
| 5 | Policy auto-discovery | Done | `#[PolicyAttribute]` on policy classes; `PackageManifestCompiler` discovers and `index.php` instantiates from manifest |

## Authentication

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| 1 | Session-based auth | Done | `SessionMiddleware` + `AuthorizationMiddleware` pipeline |
| 2 | JWT / API key auth | Planned | Bearer token middleware for machine clients |

## Media & Files

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| 1 | Media entity type | Done | Registered with `InMemoryFileRepository` |
| 2 | Disk-backed file storage | Planned | `LocalFileRepository` implementation |
| 3 | Upload endpoint | Planned | `POST /api/media/upload` with file picker widget in admin SPA |

## Admin SPA

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| 1 | Entity CRUD views | Done | List, create, edit, delete for all entity types |
| 2 | i18n locales beyond English | Planned | Infrastructure exists (`useLanguage` composable), needs locale files |
