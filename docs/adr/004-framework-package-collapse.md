# ADR-004: Collapse `packages/framework` into the monorepo root

**Status:** Accepted — partial execution 2026-04-16. Deletion of `packages/framework/` and the invariant in §2 landed. The `replace`-block / `type: library` rewrite in §5 was deferred: `PackageManifestCompiler` and related discovery paths enumerate first-party packages via `vendor/composer/installed.json`, which `replace` semantics empty out. A follow-up ADR will fix discovery (synthesize installed entries from the root's replace block + path repositories) before the rewrite lands.
**Date:** 2026-04-16
**Repos:** waaseyaa/framework

## 1. Decision

The composer package `waaseyaa/framework` is canonicalized as the **monorepo root**. The `packages/framework/` subtree is deleted. The root `composer.json` adopts Symfony's pattern: `type: "library"` with a `replace` block enumerating every first-party component.

## 2. Invariant

**There is exactly one composer.json in this repository that declares `"name": "waaseyaa/framework"`. It is the repository root.**

No meta-package subtree may re-declare the monorepo's own name. Split matrix entries may never target a remote whose name equals this repository's name (enforced by `split.yml` guard since 2026-04-16).

## 3. Motivation

Three artifacts shared the name `waaseyaa/framework`:

| Artifact | Type | Role |
|---|---|---|
| `github.com/waaseyaa/framework` | Git repo | Monorepo host |
| `composer.json` (root) | `type: project` | Workspace + identity |
| `packages/framework/composer.json` | `type: metapackage` | Aggregated install surface |

On every `v*` tag, `split.yml` ran `splitsh-lite --prefix=packages/framework` and force-pushed the result to `${REPO_OWNER}/framework`, which was the monorepo itself. The monorepo's `main` branch was overwritten with the meta-package's single `composer.json`. The first reported occurrence is 2026-04-16 release `v0.1.0-alpha.144` (incident: monorepo main reduced to 2.6 KB; no data loss — restored from local working tree).

The underlying cause is architectural, not procedural. Two composer.json files sharing the canonical name is a standing invitation to bugs of this class. A workflow guard is defense in depth, not a fix.

## 4. Why "Collapse" over "Rename"

Considered alternative: rename the monorepo repository (e.g., `waaseyaa/framework` → `waaseyaa/monorepo`), freeing the `framework` remote for the meta-package split.

Rejected on four grounds:

1. **Conceptual minimality.** Collapse yields one `waaseyaa/framework` composer.json. Rename preserves two — the collision potential persists, relying on the split matrix to continue avoiding the monorepo's name forever.
2. **Industry precedent.** Symfony, the dominant PHP monorepo reference, uses the collapse pattern: the monorepo root *is* `symfony/symfony`, components are split out but never split in.
3. **Semantic honesty.** `waaseyaa/framework` is "the whole framework". That bundling concern is the monorepo's root identity. A separate `packages/framework` subtree that re-declares the same bundle is ceremonial.
4. **Free semantic upgrade.** Collapse enables moving from `type: metapackage` to a `replace` block. `metapackage` aggregates dependencies but permits a consumer to end up with `waaseyaa/framework:0.1.0` alongside a divergent `waaseyaa/access:0.1.3`. `replace` makes the monorepo's component versions authoritative — composer treats the bundled components as already provided, preventing version drift. This is stricter and correct for a monorepo release.

Rename's only advantage is smaller touch-count on CI secrets and webhooks. That is not an architectural argument.

## 5. Target Shape

After migration, `composer.json` at repo root:

```json
{
    "name": "waaseyaa/framework",
    "description": "Waaseyaa — entity-first, AI-native PHP framework.",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "replace": {
        "waaseyaa/access": "self.version",
        "waaseyaa/admin-surface": "self.version",
        ...every published first-party package...
    },
    "require": {
        "php": "^8.4",
        ...union of runtime deps across components...
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\Access\\": "packages/access/src/",
            ...
        }
    },
    "repositories": [
        ...existing path entries retained for monorepo dev...
    ]
}
```

`packages/framework/` no longer exists. `split.yml`'s matrix does not list it. Consumers who `composer require waaseyaa/framework` pull the monorepo as a single unit.

## 6. Migration Plan

Preconditions: `origin/main` restored to `9f9c77da8` or later intact commit.

1. **Draft `replace` block** — enumerate every package with a `composer.json` in `packages/*/`. Script: `ls packages/*/composer.json | xargs -I{} jq -r '.name' {}`. Exclude the two that will cease to exist (`packages/framework`).
2. **Draft `autoload.psr-4` union** — walk every `packages/*/composer.json`'s `autoload.psr-4`, merge into the root. Conflicts are a bug — resolve by inspecting the duplicate.
3. **Draft `require` union** — walk every `packages/*/composer.json`'s `require`, take the tightest constraint per key. `php`, ext-*, and framework-level third-party deps (doctrine/dbal, symfony/*) will dominate.
4. **Edit `composer.json`** — change `type` from `project` to `library`, add `replace`, merge `require` and `autoload` per above. Remove `"version": "1.1.0"` (packagist reads from tags).
5. **Delete `packages/framework/`** directory.
6. **Edit `split.yml`** — delete the comment explaining the removed matrix entry (the condition no longer exists). Retain the self-split guard and `verify-monorepo-main-intact` job.
7. **Verify locally** — in a scratch directory, `composer require waaseyaa/framework:dev-main` pointing at a path repo. Confirm: no components get a second install entry; `vendor/waaseyaa/access` etc. resolve from the bundled source.
8. **Release** — cut `v0.2.0-alpha.1` (minor bump: surface and install semantics changed). Announce in CHANGELOG under a "BREAKING" heading: meta-package replaced by monorepo-as-library.
9. **Packagist** — no action; `waaseyaa/framework` on packagist already points at `github.com/waaseyaa/framework`, and the root composer.json remains canonical.
10. **Post-release** — confirm the `verify-monorepo-main-intact` job passes on the release run. Confirm no sub-repo has a stale `packages/framework` reference.

## 7. Breaking Changes

Consumers who currently install `waaseyaa/framework:^0.1` via the meta-package route continue to work transparently — they still get every component. The behavior change is:

- Composer no longer permits overriding an individual component's version when `waaseyaa/framework` is installed. A project requiring `waaseyaa/framework:0.2.*` AND `waaseyaa/access:0.2.3` will resolve `waaseyaa/access` to whatever the framework release declares, not 0.2.3. This is the intended stricter semantic.
- The composer package type changes from `metapackage` to `library`. A consumer parsing the type field for tooling will see the change.

These are minor in practice. The `replace` semantic is closer to what users actually expect when they install "the framework".

## 8. Follow-up Work Enabled

This ADR makes the following future work cleaner:

- **Per-component release cadence.** With components as first-class split repos and the monorepo root as the bundle identity, independent component versions become coherent (see forthcoming spec on semver trains).
- **`waaseyaa/core`, `waaseyaa/cms`, `waaseyaa/full`** can follow the same pattern — defined inside the monorepo root as named `replace`-style bundles or retained as thin sub-meta-packages. Out of scope for this ADR.
- **Branch protection ruleset on `main`.** A separate operational task; not architectural.

## 9. Status of Tactical Guards

The 2026-04-16 changes to `split.yml` (self-split guard step, `verify-monorepo-main-intact` job, removal of the `packages/framework` matrix entry) remain in place after migration. The guards are retained as defense in depth — they cost nothing and prevent future regressions from a class of mistake that isn't architectural (e.g., someone adding a new package named the same as the monorepo).
