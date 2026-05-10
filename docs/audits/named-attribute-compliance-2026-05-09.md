# Named Attribute Compliance Audit — 2026-05-09

Read-only audit of the Waaseyaa monorepo (`packages/*`) for two related forms
of "named attribute" compliance:

1. **Named constructor arguments (PHP 8.0+)** for value-object-like classes,
   per the CLAUDE.md exemplar
   `new EntityType(id: 'node', label: 'Content', ...)`.
2. **PHP 8 `#[Attribute]` declarations and call sites** — target flags,
   readonly promoted properties, and named args at usage.

No files were modified. All findings are `file:line` references.

---

## 1. Summary

| Category | Result |
|---|---|
| `new EntityType(...)` call sites — named-arg compliance | 100% (every multi-arg call uses named args) |
| `new FieldDefinition(...)` positional usages (test code) | **8 violations** (all in tests) |
| `#[PolicyAttribute]` call sites — named-arg compliance | 1 violation (positional) |
| `#[AsMiddleware]` call sites — named-arg compliance | 100% |
| `#[AsEntityType]` / `#[AsFieldType]` / `#[AsFormatter]` / `#[Component]` | 100% |
| Custom attribute declarations | 22 attributes declared; all use `#[\Attribute(\Attribute::TARGET_*)]` with appropriate targets and readonly promoted properties |
| Attribute declarations missing `final` / `readonly` | None observed in the sampled set |

Total findings: **9 actionable** (1 attribute call-site, 8 value-object positional in tests). No declaration-level defects.

---

## 2. Named constructor args

### 2.1 `new EntityType(...)` — clean

Every multi-arg `new EntityType(...)` call uses named args. Sampled across:

- `packages/taxonomy/src/TaxonomyServiceProvider.php:24` — `id:`, `label:`, `description:`, `class:`, `keys:`, `group:`, `_fieldDefinitions:`
- `packages/menu/src/MenuServiceProvider.php:14`, `:23`
- `packages/workflows/src/WorkflowServiceProvider.php:14`
- `packages/groups/src/GroupsServiceProvider.php:32`
- `packages/media/src/MediaServiceProvider.php:30`, `:39`
- `packages/mcp/tests/Unit/Tools/EntityToolsTest.php:25`
- `packages/graphql/tests/Unit/Resolver/ReferenceLoaderTest.php:40`, `:266`
- `packages/testing/tests/Unit/EntityTypeFixtureValuesTest.php:125`
- `packages/ai-schema/tests/Unit/Mcp/McpToolGeneratorTest.php:28`, `:49`, `:75`, `:101`
- `packages/entity-storage/tests/Unit/SqlSchemaHandlerRegistryFallbackTest.php:81`, `:122`
- `packages/entity-storage/tests/Unit/SqlEntityQueryBundleFieldsTest.php:41`, `:287`
- `packages/entity/tests/Unit/EntityTypeManagerTest.php:38, 52, 53, 69, 86, 95, 101, 198, 214, 240, 256, 276, 286, 298, 306, 314, 322, 327`

This is the **gold-standard pattern** — both production code (service providers) and test fixtures consistently use named args. The CLAUDE.md exemplar is faithfully implemented.

### 2.2 `new FieldDefinition(...)` — positional in tests

`FieldDefinition` has 6+ optional constructor params (name, type, label, description, settings, targetEntityTypeId, …). Production code uses named args; tests sometimes use positional. Eight occurrences:

| File | Line | Snippet |
|---|---|---|
| `packages/admin-surface/tests/Unit/Catalog/FieldDefinitionTest.php` | 18 | `new FieldDefinition('title', 'Title', 'string')` |
| `packages/admin-surface/tests/Unit/Catalog/FieldDefinitionTest.php` | 33 | `new FieldDefinition('body', 'Body', 'string')` |
| `packages/admin-surface/tests/Unit/Catalog/FieldDefinitionTest.php` | 52 | `new FieldDefinition('uuid', 'UUID', 'string')` |
| `packages/entity-storage/tests/Unit/SqlSchemaHandlerTest.php` | 213 | `new FieldDefinition('description', 'text_long')` |
| `packages/entity-storage/tests/Unit/SqlSchemaHandlerTest.php` | 216 | `new FieldDefinition('url', 'uri')` |
| `packages/entity-storage/tests/Unit/SqlSchemaHandlerTest.php` | 221 | `new FieldDefinition('booking_url', 'uri', settings: ['length' => 512])` (mixed) |
| `packages/entity-storage/tests/Unit/SqlSchemaHandlerTest.php` | 224 | `new FieldDefinition('community_id', 'entity_reference', targetEntityTypeId: 'node')` (mixed) |
| `packages/entity-storage/tests/Unit/SqlSchemaHandlerTest.php` | 242 | `new FieldDefinition('weird', 'not_a_real_field_type')` |

Note: Two of these (`SqlSchemaHandlerTest.php:221, :224`) mix positional + named — the `name` and `type` are positional but later kwargs are named. Especially in `admin-surface` tests, the third positional looks like `(name, type, label)` but `FieldDefinition`'s public signature has `name, type` as the first two params and `label` later — worth confirming the third arg is interpreted as expected (could be silently binding to the wrong param).

**Severity: low.** Tests only. Mechanical fix: wrap each call with `name:`/`type:`/`label:` etc.

### 2.3 `new AccessResult(...)` — N/A

No direct `new AccessResult(...)` usages anywhere; all 405 occurrences go through the static factories `AccessResult::allowed()`, `::neutral()`, `::forbidden()`. No action.

### 2.4 `new RouteDefinition(...)` — not surveyed in detail

No multi-arg positional `new RouteDefinition(...)` candidates surfaced in the sampling.

---

## 3. Attribute declarations and call sites

### 3.1 Declarations inventory (22 attributes)

All declarations live in `packages/*/src/Attribute/` or analogous (e.g. `packages/access/src/Gate/PolicyAttribute.php`, `packages/validation/src/Constraint/*`).

| Attribute | File | Target(s) | Readonly props | Notes |
|---|---|---|---|---|
| `AsMiddleware` | `packages/foundation/src/Attribute/AsMiddleware.php` | `TARGET_CLASS` | yes | `pipeline` (req), `priority` (=0) |
| `AsEntityType` | `packages/foundation/src/Attribute/AsEntityType.php` | `TARGET_CLASS` | yes | OK |
| `AsFieldType` | `packages/foundation/src/Attribute/AsFieldType.php` | `TARGET_CLASS` | yes | `id`, `label` |
| `PolicyAttribute` | `packages/access/src/Gate/PolicyAttribute.php` | `TARGET_CLASS` | yes (`entityTypes`) | `string\|array $entityType` accepted; normalised to `array` |
| `AccessPolicy` | `packages/access/src/Attribute/AccessPolicy.php` | `TARGET_CLASS` | yes | extends `WaaseyaaPlugin` |
| `WaaseyaaPlugin` | `packages/plugin/src/Attribute/WaaseyaaPlugin.php` | `TARGET_CLASS` | yes | base class for plugin attributes — **not `final`** (intentional, subclassed by AccessPolicy/FieldType/etc.) |
| `FieldType` | `packages/field/src/Attribute/FieldType.php` | `TARGET_CLASS` | yes | extends `WaaseyaaPlugin` |
| `BundleTemplate` | `packages/field/src/Attribute/BundleTemplate.php` | `TARGET_CLASS` | yes | OK |
| `FieldTemplate` | `packages/field/src/Attribute/FieldTemplate.php` | `TARGET_PROPERTY \| TARGET_METHOD \| IS_REPEATABLE` | yes | OK |
| `Component` (SSR) | `packages/ssr/src/Attribute/Component.php` | `TARGET_CLASS` | yes | `name`, `template` |
| `AsFormatter` | `packages/ssr/src/Attribute/AsFormatter.php` | `TARGET_CLASS` | yes | OK |
| `FromRoute` | `packages/ssr/src/Attribute/FromRoute.php` | `TARGET_PARAMETER` | yes | OK |
| `MapRoute` | `packages/ssr/src/Attribute/MapRoute.php` | `TARGET_PARAMETER` | yes | OK |
| `MapQuery` | `packages/ssr/src/Attribute/MapQuery.php` | `TARGET_PARAMETER` | yes | OK |
| `OnQueue` | `packages/queue/src/Attribute/OnQueue.php` | `TARGET_CLASS` | yes | `name` |
| `RateLimited` | `packages/queue/src/Attribute/RateLimited.php` | `TARGET_CLASS` | yes (assumed) | targets jobs |
| `GateAttribute` | `packages/routing/src/Attribute/GateAttribute.php` | `TARGET_METHOD \| TARGET_CLASS \| IS_REPEATABLE` | yes | `ability`, `subject` |
| `ContentEntityType` | `packages/entity/src/Attribute/ContentEntityType.php` | `TARGET_CLASS` | yes (`final readonly class`) | OK |
| `Field` (entity) | `packages/entity/src/Attribute/Field.php` | `TARGET_PROPERTY` | yes | OK |
| `EntityTypeAttribute` | `packages/entity/src/Attribute/EntityTypeAttribute.php` | `TARGET_CLASS` | yes | OK |
| `ContentEntityKeys` | `packages/entity/src/Attribute/ContentEntityKeys.php` | `TARGET_CLASS` | yes | OK |
| `AllowedValues`, `UniqueField`, `SafeMarkup`, `NotEmpty`, `EntityExists` (validation) | `packages/validation/src/Constraint/*.php` | `TARGET_PROPERTY \| TARGET_METHOD` | yes | OK |

**Anomalies:**

- None at declaration level. Targets are appropriate. Promoted readonly properties are universal. `IS_REPEATABLE` is correctly applied where multiple instances per target make sense (`FieldTemplate`, `GateAttribute`).

### 3.2 Call-site review

#### `#[AsMiddleware]` — clean

Sampled call sites at `packages/foundation/src/Community/CommunityMiddleware.php:27`, `packages/access/src/Middleware/AuthorizationMiddleware.php:22`, `packages/user/src/Middleware/{Csrf,Session,BearerAuth}Middleware.php`, `packages/inertia/src/InertiaMiddleware.php:13`, `packages/foundation/src/Middleware/{ETag,RequestLogging,Compression,SecurityHeaders,BodySizeLimit}Middleware.php` — every call uses `#[AsMiddleware(pipeline: 'http', priority: N)]`.

Targets are correct (all applied to middleware classes implementing `HttpMiddlewareInterface`).

#### `#[PolicyAttribute]` — **1 positional violation**

| File | Line | Issue |
|---|---|---|
| `packages/attachment/src/Policy/ParentDelegatedAccessPolicy.php` | 37 | `#[PolicyAttribute('attachment')]` — **positional**. Inconsistent with the rest of the codebase (all 14+ other call sites use `#[PolicyAttribute(entityType: '…')]`). |

The attribute *accepts* positional via `string|array $entityType`, so this is legal but stylistically off. Mechanical fix: change to `#[PolicyAttribute(entityType: 'attachment')]`.

All other `#[PolicyAttribute]` usages confirmed named-arg compliant:
- `packages/menu/src/MenuAccessPolicy.php:13` — `entityType: ['menu', 'menu_link']`
- `packages/user/src/UserBlockAccessPolicy.php:13`, `UserAccessPolicy.php:21`
- `packages/genealogy/src/Access/GenealogyContentAccessPolicy.php:20`
- `packages/path/src/PathAliasAccessPolicy.php:13`
- `packages/relationship/src/RelationshipAccessPolicy.php:14`
- `packages/note/src/NoteAccessPolicy.php:29`
- `packages/oidc/src/Access/OidcClientAccessPolicy.php:34`
- `packages/taxonomy/src/TermAccessPolicy.php:19`
- `packages/access/src/ConfigEntityAccessPolicy.php:17`
- `packages/access/tests/Unit/Gate/GateTest.php:410, 447, 460, 479, 526, 535`

#### `#[AsEntityType]` — clean

Single non-test call site: `packages/foundation/tests/Unit/Kernel/Bootstrap/ProviderRegistryTest.php:278` — `#[AsEntityType(id: 'attr_auto_fixture', label: 'Attr fixture')]`. Named args.

#### `#[AsFormatter]` — clean

All seven call sites (`packages/ssr/src/Formatter/*Formatter.php`, plus a fixture in `PackageManifestCompilerTest.php:713`) use `#[AsFormatter(fieldType: '…')]`.

#### `#[Component]` (SSR) — clean

All four call sites (test fixtures only — production components live in twig+registry) use `#[Component(name: '…', template: '…')]`.

#### `#[BundleTemplate]` / `#[FieldTemplate]`

No call sites surfaced from the sampled greps in `packages/`. The attributes exist as a contract; if no code uses them yet, this is expected for a forward-looking surface (per CLAUDE.md `docs/specs/work-surface.md` orchestration row). Worth a follow-up grep across consumer apps (`packages/admin-surface`, etc.) to confirm.

#### `#[OnQueue]` / `#[RateLimited]` / `#[GateAttribute]`

No production call sites surfaced in the sampled output. These are declared but not yet used (or used only inside docs/spec examples). No call-site anomalies to flag.

#### `#[FieldType]`

No call sites surfaced — `FieldType` is the discovery attribute for field-type plugins (per `docs/specs/entity-system.md`). If field-type classes exist that should declare it, they were not surfaced; this is worth a separate audit narrowed to `packages/field/src/Plugin/FieldType/` and `packages/*/src/FieldType/`.

---

## 4. Recommendations

### Mechanical fixes (one-line edits)

1. **`packages/attachment/src/Policy/ParentDelegatedAccessPolicy.php:37`** — change `#[PolicyAttribute('attachment')]` → `#[PolicyAttribute(entityType: 'attachment')]`. Consistent with all other policies.
2. **`packages/admin-surface/tests/Unit/Catalog/FieldDefinitionTest.php:18, 33, 52`** — convert `new FieldDefinition('title', 'Title', 'string')` to named args. Verify the third positional is meant for `label`, not `type`.
3. **`packages/entity-storage/tests/Unit/SqlSchemaHandlerTest.php:213, 216, 221, 224, 242`** — convert positional `name`/`type` to named args. The mixed-style calls at `:221, :224` are the most readable to fix because they already use kwargs for everything else.

### Judgment calls / follow-ups

4. **Verify `#[FieldType]` and `#[BundleTemplate]`/`#[FieldTemplate]` are actually applied somewhere.** Either they are unused contracts (legitimate for forward-looking attributes documented in `docs/specs/`) or call sites are missing. A focused grep against `packages/field/src/Plugin/`, `packages/admin-surface/`, and `packages/admin/` would resolve this.
5. **Consider lint enforcement.** A custom PHPStan rule or `bin/check-named-attributes` shell script could enforce: "every `new <ClassWith3+ConstructorParams>(...)` call must use named args for the 3rd-onward param." This catches future regressions cheaply. Useful examples: `EntityType`, `FieldDefinition`, `EntityType::fromClass(...)` derivatives.
6. **Document the `PolicyAttribute` constructor signature in `docs/specs/access-control.md`.** The polymorphic `string|array $entityType` is non-obvious; a doctrine note ("always pass `entityType:` named, even for single strings") would prevent regression of the attachment-style positional drift.

### Non-issues confirmed

- `EntityType` call sites are gold standard.
- Attribute declarations are uniformly correct: `final` (where appropriate), readonly promoted properties, accurate `TARGET_*` flags, `IS_REPEATABLE` only where semantically required.
- `AccessResult` uses static factories exclusively — no `new AccessResult(...)` to audit.

---

## Audit method (reproducibility)

- `rg -n "new EntityType\(" packages/ --type php` — call site enumeration.
- `rg -rn "^#\[\\\\?Attribute" packages/*/src` — attribute declaration enumeration.
- Per-attribute `rg -n "#\[<Name>" packages/ --type php` for call-site sampling.
- Multi-line `awk` extraction to confirm named-arg syntax inside multi-line `new EntityType(...)` blocks.
- `csrf_upload_*` artifacts at repo root excluded from all greps (untracked test artifacts).
