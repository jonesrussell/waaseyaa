---
work_package_id: WP10
title: 'Cross-cutting: end-to-end test, docs, CHANGELOG, metapackage'
dependencies:
- WP02
- WP03
- WP04
- WP06
- WP07
- WP09
requirement_refs:
- FR-016
- FR-017
- NFR-005
- NFR-006
- NFR-007
planning_base_branch: main
merge_target_branch: main
branch_strategy: Plan from main; lane execution branch is allocated by finalize-tasks via lanes.json. Final merge target is main.
subtasks:
- T044
- T045
- T046
- T047
- T048
- T049
history:
- date: '2026-04-27'
  note: Generated from plan.md + spec.md Success Criterion 5.
authoritative_surface: tests/Integration/PhaseSingleEntityWorkSurface/
execution_mode: code_change
mission_id: 01KQ7M1PHWD8QAQPJC91RAVE0T
mission_slug: single-entity-work-surface-01KQ7M1P
owned_files:
- tests/Integration/PhaseSingleEntityWorkSurface/SingleEntityWorkSurfaceTest.php
- tests/Integration/PhaseSingleEntityWorkSurface/Fixtures/**
- docs/specs/work-surface.md
- docs/specs/entity-system.md
- docs/specs/api-layer.md
- docs/specs/access-control.md
- CHANGELOG.md
- composer.json
- packages/full/composer.json
- CLAUDE.md
tags: []
---

# WP10 — Cross-cutting: end-to-end test, docs, CHANGELOG, metapackage

## Objective

Land the cross-primitive integration test (Success Criterion 5), publish subsystem documentation, write all CHANGELOG.md entries for the mission in one consolidated edit, update the root `composer.json` and `waaseyaa/full` metapackage to include the two new packages, and run all gate scripts before requesting review.

This is the last WP. It collapses every preceding WP's contribution into a single proven-end-to-end deliverable.

## Context (read first)

- **spec.md** Success Criterion 5 — the integration test specification.
- **plan.md** § Project Structure — files to update (`docs/specs/*`, `composer.json`, etc.).
- **quickstart.md** — the integration test mirrors this walkthrough.
- **DIR-001** § "No silent breaking changes" — CHANGELOG.md + UPGRADING.md required for the `FieldDefinition` change. UPGRADING was authored in WP01; CHANGELOG is consolidated here.
- **DIR-002** § "Keep documentation synchronized" — `docs/specs/*.md` updates are mandatory for subsystem changes.
- **CLAUDE.md** orchestration table — needs a row for the new `work-surface` spec.

## Branch Strategy

- **Planning base**: `main` (after WP02, WP03, WP04, WP06, WP07, WP09 land)
- **Final merge target**: `main`
- Lane via `finalize-tasks`. Use `spec-kitty agent action implement WP10 --agent <name> --mission single-entity-work-surface-01KQ7M1P`.

## Subtasks

### T044 — End-to-end integration test (Success Criterion 5)

**File**: `tests/Integration/PhaseSingleEntityWorkSurface/SingleEntityWorkSurfaceTest.php`

**Setup**: real kernel boot with:
- `DBALDatabase::createSqlite()` (`:memory:` is fine — no concurrency in this test).
- `node` entity type registered (use existing `Waaseyaa\Node` package).
- A fixture `BundleTemplate` class for `(node, profile)` with 5 `#[FieldTemplate]` properties matching quickstart.md Step 1.
- Fixture `EditWorkspaceController` recording the entity it received.
- Fixture access policy granting view/update to a fixture account.

**Test method** — one large method covering all six primitives in sequence:

```php
#[Test]
#[CoversNothing]
public function singleEntityWorkSurfaceEndToEnd(): void
{
    // 1. F2: Bundle template compiled at boot.
    $registry = $this->kernel->resolve(FieldDefinitionRegistryInterface::class);
    $fields = $registry->bundleFieldsFor('node', 'profile');
    self::assertCount(5, $fields);
    self::assertSame(['name', 'bio', 'birthplace', 'website', 'is_published'], array_keys($fields));
    self::assertSame(['name', 'display name', 'full name'], $fields['name']->getPromptAliases());

    // 2. F1: Deep-link route registered.
    $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
        ->controller(FixtureEditController::class . '::view')
        ->build();
    $router = $this->kernel->resolve(WaaseyaaRouter::class);
    $router->addRoute('test.edit_node', $route);

    // 3. Create a node and hit the deep-link route.
    $node = $this->createTestNode(['bundle' => 'profile', 'title' => 'Initial']);
    $response = $this->httpRequest('GET', '/edit/node/' . $node->id());
    self::assertSame(200, $response->getStatusCode());
    self::assertInstanceOf(Node::class, FixtureEditController::$lastEntity);

    // 4. F3: Auto-save five fields via PUT.
    foreach (['name' => 'A', 'bio' => 'B', 'birthplace' => 'BP', 'website' => 'W', 'is_published' => 'true'] as $key => $value) {
        $response = $this->httpRequest('PUT', "/api/node/{$node->id()}/field/$key", ['value' => $value]);
        self::assertSame(200, $response->getStatusCode(), "PUT failed for field $key");
    }
    $reloaded = $this->kernel->resolve(EntityRepositoryInterface::class)->find((string) $node->id());
    self::assertSame('A', $reloaded->get('name')->value);

    // 5. F4: Three attachments + setActive.
    $attachmentRepo = $this->kernel->resolve(AttachmentRepository::class);
    $attachmentIds = [];
    foreach (range(1, 3) as $i) {
        $a = new Attachment([
            'parent_entity_type' => 'node',
            'parent_entity_id' => (string) $node->id(),
            'filename' => "file$i.txt",
            'is_active' => false,
        ]);
        $a->enforceIsNew();
        $attachmentRepo->save($a);
        $attachmentIds[] = $a->id();
    }
    $attachmentRepo->setActive($attachmentIds[1]);   // second attachment
    $active = $attachmentRepo->getActive('node', (string) $node->id());
    self::assertSame($attachmentIds[1], $active->id());

    // 6. F5: Import a markdown table.
    $payload = "| Field | Value |\n| --- | --- |\n| Display Name | Aanikoobijigan |\n| Biography | Storyteller. |\n| Born In | Naotkamegwanning |\n| Website | https://example.test |\n| Status | Active |\n";
    $importer = $this->kernel->resolve(StructuredImporterInterface::class);
    $result = $importer->import($payload, 'node', 'profile');
    self::assertCount(4, $result->matched);
    self::assertCount(1, $result->unmatched);
    self::assertSame('Status', $result->unmatched[0]->prompt);

    // 7. F6: Build form descriptors.
    $builder = $this->kernel->resolve(FormDescriptorBuilder::class);
    $descriptors = $builder->build($reloaded, 'profile');
    self::assertCount(5, $descriptors);
    self::assertSame('name', $descriptors[0]->name);
    self::assertSame('about', $descriptors[1]->group);   // bio is in 'about' group
}
```

**Validation**: test passes against `:memory:` SQLite in under 2 seconds.

### T045 — Subsystem doc + orchestration table

**Files**:
- `docs/specs/work-surface.md` (NEW)
- `CLAUDE.md` (EDIT — orchestration table)

**`docs/specs/work-surface.md`** — write a subsystem doc covering:
- Overview (six primitives, packages they live in)
- Public PHP API surface for each primitive
- Wire-up steps (mirrors quickstart.md but as a reference doc, not a tutorial)
- Cross-references to `entity-system.md`, `api-layer.md`, `access-control.md`
- Security considerations (parent-delegated policy semantics, body size cap)
- Performance budgets (NFR-001, NFR-003, NFR-004 from spec)

**`CLAUDE.md`** — add a row to the orchestration table:

```
| `packages/attachment/*`, `packages/structured-import/*`, `packages/field/src/Form/*`, `packages/field/src/Attribute/*`, `packages/routing/src/EntityDeepLinkRouteBuilder.php`, `packages/api/src/Controller/FieldAutoSaveController.php` | — | `docs/specs/work-surface.md` |
```

Place it alphabetically by package or grouped with related entries — match existing table structure.

### T046 — Update existing specs

**Files**:
- `docs/specs/entity-system.md` — add a section "Field templates and the bundle registry" describing `BundleTemplate`/`FieldTemplate` attributes and `BundleTemplateCompiler`. Note that `FieldDefinition` now carries `group` and `promptAliases`.
- `docs/specs/api-layer.md` — add the auto-save endpoint to the route catalog. Cross-reference `contracts/README.md` § F3 for the status code matrix.
- `docs/specs/access-control.md` — add a section "Parent-delegated policies" describing the pattern with `ParentDelegatedAccessPolicy` as the canonical example. Cross-reference `field-access.md` for field-level enforcement (already documented).

Each edit should be small (≤ 50 lines added per spec). The specs are reference material; keep them concise and link out to the contracts/quickstart for examples.

### T047 — CHANGELOG.md

**File**: `CHANGELOG.md`

Add a single `## [Unreleased]` section (or append to existing) with:

```markdown
### Added

- **Single-Entity Work Surface** — six new primitives for downstream apps building per-entity editing workspaces. Full subsystem doc: `docs/specs/work-surface.md`. Mission: `single-entity-work-surface-01KQ7M1P`.
  - `Waaseyaa\Routing\EntityDeepLinkRouteBuilder` — deep-link route helper for `/<segment>/<entity_type>/{id}`.
  - `Waaseyaa\Field\Attribute\BundleTemplate` and `Waaseyaa\Field\Attribute\FieldTemplate` — declarative bundle field registration.
  - `Waaseyaa\Field\BundleTemplateCompiler` — attribute-driven field discovery → `FieldDefinitionRegistry`.
  - `PUT /api/{entityType}/{id}/field/{key}` — per-field auto-save endpoint.
  - `Waaseyaa\Field\Form\FormDescriptorBuilder` — schema-driven form descriptor builder (structured arrays, no HTML).
- **`waaseyaa/attachment`** — new package at Layer 2. Content entity for files attached to a parent entity, with at-most-one-active invariant. Includes `ParentDelegatedAccessPolicy`.
- **`waaseyaa/structured-import`** — new package at Layer 3. `StructuredImporterInterface` + `GfmTableImporter` (in-house GFM 2-column table parser, no CommonMark dependency).

### Changed

- **`Waaseyaa\Field\FieldDefinition`** constructor gained two trailing optional parameters: `string $group = ''` and `array $promptAliases = []`. `FieldDefinitionInterface` gained `getGroup(): string` and `getPromptAliases(): array`. Custom interface implementations must add the two new methods. See `UPGRADING.md` § "FieldDefinition constructor parameters added". Per `DIR-003`, no compatibility shim is provided. ([single-entity-work-surface-01KQ7M1P](kitty-specs/single-entity-work-surface-01KQ7M1P/spec.md))
```

### T048 — Root composer + metapackage updates

**Files**:
- `composer.json` (root) — add path repositories and `@dev` constraints for `waaseyaa/attachment` and `waaseyaa/structured-import`.
- `packages/full/composer.json` — add both as `require` entries (the `full` metapackage installs everything; new packages must be listed).

If `cms` or `core` metapackages should also include them, evaluate per layer:
- `attachment` is L2 — fits `cms` if `cms` includes other L2 content types.
- `structured-import` is L3 — fits `core` if `core` includes L3 services. Probably out-of-scope for `core`; prefer `cms`-only or `full`-only.

Pick the conservative route: include in `full` only. `cms` and `core` can be amended in future missions if downstream apps demand it.

### T049 — Run all gate scripts

**Steps**:

```bash
# From the lane workspace.
composer dump-autoload --optimize
composer phpstan
composer cs-check
bin/check-package-layers
bin/check-composer-policy
bin/audit-require-dev-layers
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
```

All must pass. If any fails:
1. **PHP-CS / PHPStan / unit-test failure** in code from another WP — file a `review-feedback` against the responsible WP and request that WP be re-implemented, **do not paper over in WP10**.
2. **Integration test failure in T044** — root-cause inside this WP and fix.
3. **Layer or composer-policy failure** — likely in WP10's own root `composer.json` edits; fix in this WP.
4. **`audit-require-dev-layers` warning-only output** — review and document; don't necessarily fix unless clearly egregious.

Once all gates pass, commit and move WP10 to `for_review`.

## Definition of Done

- [ ] End-to-end integration test passes against `:memory:` SQLite in < 2 seconds.
- [ ] `docs/specs/work-surface.md` exists and is referenced in `CLAUDE.md`'s orchestration table.
- [ ] `docs/specs/entity-system.md`, `docs/specs/api-layer.md`, `docs/specs/access-control.md` updated.
- [ ] `CHANGELOG.md` has consolidated `## [Unreleased]` entries (Added: 5 primitives + 2 packages; Changed: `FieldDefinition` breaking change cross-referencing UPGRADING).
- [ ] Root `composer.json` registers both new packages.
- [ ] `packages/full/composer.json` requires both new packages.
- [ ] All gate scripts pass.
- [ ] `composer phpstan`, `composer cs-check`, full PHPUnit suite pass on `main`-equivalent state.
- [ ] No code changes outside `owned_files`.

## Risks

| Risk | Mitigation |
|---|---|
| Integration test surfaces interaction bugs that passed isolated WP review | Acceptable — that's the test's purpose. File feedback against the responsible WP; do not silently fix in WP10. The test's role is to *catch* cross-WP issues, not to mask them. |
| `composer.json` root edit conflicts with concurrent missions | If git conflict on root composer.json at merge time, rebase + resolve. The two new packages are additive entries. |
| `CLAUDE.md` orchestration table edit drifts from existing structure | Read the current table; match its column order and formatting exactly. |
| `audit-require-dev-layers` flags expected upward dev-only edges | Warning-only by design (charter). Document any flagged edges in this PR description. |

## Reviewer guidance

- Verify the integration test exercises **all six primitives** and not just a subset.
- Verify `CHANGELOG.md` cross-references `UPGRADING.md` for the breaking change.
- Verify `docs/specs/work-surface.md` is concise (< 250 lines); it should be a reference, not a tutorial (tutorial content lives in `quickstart.md`).
- Verify `CLAUDE.md` orchestration table includes the new spec mapping.
- Verify gate scripts were actually run (commit message or PR description should list them).
- Verify no `@deprecated` annotations or `Legacy*` references introduced in any prior WP — DIR-003 final check.

## Implementation command

```bash
spec-kitty agent action implement WP10 --agent <agent-name> --mission single-entity-work-surface-01KQ7M1P
```

Depends on WP02, WP03, WP04, WP06, WP07, WP09 — all must be approved before WP10 starts.
