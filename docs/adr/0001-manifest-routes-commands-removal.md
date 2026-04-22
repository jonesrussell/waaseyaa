# ADR 0001: Remove `manifest.routes` and `manifest.commands` from the package manifest

## Status

Accepted — implemented in `waaseyaa/foundation`.

## Context

`PackageManifestCompiler` merged `extra.waaseyaa.routes` and `extra.waaseyaa.commands` from Composer metadata into the cached `PackageManifest`. The HTTP kernel never registered routes from that list, and the console kernel never instantiated commands from it. The surface was **compiled but ignored**, which misled integrators and duplicated the real contract (`ServiceProvider::routes()`, `ServiceProvider::commands()`, and the core `CliCommandRegistry`).

## Decision

1. **Stop compiling** `routes` and `commands` into the manifest.
2. **Remove** the corresponding properties from `PackageManifest` and from `toArray()` output.
3. **Backward compatibility:** `PackageManifest::fromArray()` strips legacy `commands` and `routes` keys so existing `storage/framework/packages.php` caches continue to load until the next `optimize:manifest` / fingerprint-driven recompile.
4. **Deprecation:** If any installed package or root `composer.json` still declares `extra.waaseyaa.commands` or `extra.waaseyaa.routes`, the compiler logs a **warning** listing the sources and points to this ADR.

## Consequences

- Apps must register HTTP routes only via service providers (or future explicit APIs), not via Composer `routes` lists.
- Console commands from packages must use `ServiceProvider::commands()` or live in the core CLI registry—not `extra.waaseyaa.commands`.
- **No-op provider cleanup:** `CliServiceProvider`, `ErrorHandlerServiceProvider`, and `GeoServiceProvider` were removed from `extra.waaseyaa.providers` in their respective packages because `register()` was empty and nothing in the kernel depended on those classes for side effects. The PHP classes remain in the packages for optional manual registration if needed.

## Migration

1. Remove `commands` / `routes` from `extra.waaseyaa` in `composer.json` (or ignore the warning until you do).
2. Run `php bin/waaseyaa optimize:manifest` to refresh the cache without legacy keys.
