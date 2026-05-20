# Research: Scheduler Entry Auto-Discovery

**Mission**: `scheduler-entry-auto-discovery-01KS3SE3`
**Date**: 2026-05-20

---

## R-001: `PackageManifestCompiler` discovery patterns

**Decision**: Use interface-implementation detection (string constant), not a new attribute.

**Rationale**: `ScheduleEntriesInterface` is spec'd as interface-based (FR-002, C-001). The compiler already has the `CAPABILITY_HAS_NATIVE_COMMANDS` pattern using `class_implements()` against a string constant to avoid upward layer imports. This is the correct pattern for `ScheduleEntriesInterface` discovery.

`filterDiscoveryClasses()` currently gates on attribute presence only. It must be extended to **also** pass interface implementors. The existing scan loop in `compile()` collects candidates from classmap+PSR-4; the filter runs after. Adding an `implements` check to the filter is a localized, low-risk change.

**Alternative considered**: New `#[AsScheduleEntries]` attribute. Rejected — the spec explicitly says interface, not attribute (FR-002). The closure-invoker pattern in `AgentScheduleEntries` also makes attribute-based instantiation awkward (constructor args are real services, not attribute-injectable).

---

## R-002: Boot sequence placement

**Decision**: `bootScheduleEntries()` is called after `discoverAccessPolicies()` in `AbstractKernel::boot()`.

**Rationale**: Access policies are wired before schedule entries so that any schedule-entry class that reads access results (unlikely but possible) has a consistent handler. The `Schedule` object must be available at this point — it's wired during provider boot (providers run before both). Placement mirrors the `discoverAccessPolicies()` call at line 156.

**Boot sequence** (with M-D additions):
```
bootDatabase()
bootEntityTypeManager()
compileManifest()
bootMigrations()
discoverAndRegisterProviders()
loadAppEntityTypes()
validateContentTypes()
bootProviders()
discoverAccessPolicies()
bootScheduleEntries()   ← M-D addition
validateQueryDefinitions()
bootKnowledgeExtensionRunner()
finalizeBoot()
```

---

## R-003: M-B resolver adoption

**Decision**: M-D adopts `Waaseyaa\Foundation\Kernel\Bootstrap\PolicyDependencyResolverInterface`.

**Rationale**: The M-B contract document explicitly states: "M-D SHALL reference `PolicyDependencyResolverInterface` by its FQCN and reuse `KernelPolicyDependencyResolver` without modification." The `entityTypes` parameter in `resolveParameter()` can be passed as `[]` for schedule-entry classes (they have no entity-type affinity). The resolver's rules 2–5 cover all schedule-entry constructor patterns (service injection, nullable, default, scalar).

**Fallback**: If M-B has not landed when WP02 starts, introduce a structurally identical `ScheduleEntryDependencyResolverInterface` in the same `Bootstrap/` namespace and document the pending consolidation in a `// TODO(M-B): consolidate` comment.

---

## R-004: `BroadcastStorage::prune()` existence

**Decision**: Verify existence before WP03 starts; add if missing.

**Rationale**: The spec says `BroadcastStorage::prune()` "exists but is never called on a schedule." WP03 implementer should confirm the method signature matches `prune(int $retentionDays = 7): void`. If signature differs, adjust `BroadcastStorageScheduleEntries` accordingly. If the method doesn't exist, WP03 adds it.

---

## R-005: Integration test phase number

**Decision**: Verify the next unused phase in `tests/Integration/` before creating `Phase13/`.

**Rationale**: Phase numbers must be sequential with existing phases. Implementer runs `ls tests/Integration/` to confirm `Phase13/` doesn't exist. If it does, use `Phase14/`, etc.

---

## R-006: `schedule:list` command

**Decision**: The command exists. No new command needed in WP01.

**Finding**: `packages/cli/src/Handler/ScheduleListHandler.php` confirmed present. WP05 extends it to group by owning class and show disabled entries. WP01 scope is interface + manifest only.

---

## R-007: `AgentScheduleEntries` manual wiring

**Decision**: No existing manual wiring to remove.

**Finding**: `AgentScheduleEntries` was never wired anywhere (that's why #1512 is open). The `grep -r "AgentScheduleEntries" packages/*/src/` search in WP04 will confirm no `register()` calls exist in service providers. WP04 only adds `implements ScheduleEntriesInterface`.
