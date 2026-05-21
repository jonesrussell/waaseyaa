# Bimaaji Public PHP Surface

**Date gathered**: 2026-05-20
**Source**: `packages/bimaaji/src/` (25 PHP files)
**Package description**: "Bimaaji — application graph introspection and agent-safe mutation for Waaseyaa"

---

## Public Classes/Interfaces

| FQCN | Type | Key public methods | @api? |
|------|----|-------------------|-------|
| `Waaseyaa\Bimaaji\Bimaaji` | final class | (no public methods — empty facade) | No |
| `Waaseyaa\Bimaaji\Graph\ApplicationGraph` | final readonly class | `getSection(string $key): ?GraphSection`, `toArray(): array` | **Yes** |
| `Waaseyaa\Bimaaji\Graph\ApplicationGraphGenerator` | class | (generator for building the graph) | No |
| `Waaseyaa\Bimaaji\Graph\GraphSection` | class | value object wrapping a named graph section | No |
| `Waaseyaa\Bimaaji\Graph\GraphSectionProviderInterface` | interface | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Introspection\Entity\EntityIntrospectionProvider` | final class implements `GraphSectionProviderInterface` | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Introspection\JsonApi\JsonApiIntrospectionProvider` | final class implements `GraphSectionProviderInterface` | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Introspection\Admin\AdminIntrospectionProvider` | final class implements `GraphSectionProviderInterface` | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Introspection\Routing\RoutingIntrospectionProvider` | final class implements `GraphSectionProviderInterface` | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Introspection\Sovereignty\SovereigntyIntrospectionProvider` | final class implements `GraphSectionProviderInterface` | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Introspection\PublicSurface\PublicSurfaceProvider` | final class implements `GraphSectionProviderInterface` | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Spec\SpecIndexProvider` | final class implements `GraphSectionProviderInterface` | `getKey(): string`, `provide(): GraphSection` | No |
| `Waaseyaa\Bimaaji\Mutation\MutationRequest` | final readonly class | `toArray(): array` | No |
| `Waaseyaa\Bimaaji\Mutation\MutationResult` | final readonly class | `isSuccess(): bool`, `toArray(): array` | No |
| `Waaseyaa\Bimaaji\Mutation\MutationValidator` | final class | `validate(MutationRequest $request): MutationResult` | No |
| `Waaseyaa\Bimaaji\Dsl\TaskDefinition` | final readonly class | `toArray(): array` | **Yes** |
| `Waaseyaa\Bimaaji\Dsl\TaskParser` | class | DSL parsing | No |
| `Waaseyaa\Bimaaji\Dsl\TaskPipeline` | class | pipeline execution | No |
| `Waaseyaa\Bimaaji\Dsl\TaskPipelineResult` | class | pipeline result value object | No |
| `Waaseyaa\Bimaaji\Patch\PatchEntry` | class | single patch operation | No |
| `Waaseyaa\Bimaaji\Patch\PatchGenerator` | class | generates PHP file patches | No |
| `Waaseyaa\Bimaaji\Patch\PatchSet` | class | collection of patch entries | No |
| `Waaseyaa\Bimaaji\Patch\PhpFileBuilder` | class | PHP AST file builder (uses nikic/php-parser) | No |
| `Waaseyaa\Bimaaji\Policy\GuardrailRule` | class | policy rule value object | No |
| `Waaseyaa\Bimaaji\Policy\SovereigntyGuardrails` | class | validates mutations against sovereignty rules | No |

---

## Graph Operation Candidates (for Option 2)

These operations are natural MCP tool candidates if Option 2 is chosen:

1. **`bimaaji_graph_section`** — call `ApplicationGraph::getSection(string $key)` to retrieve a named introspection section (entities, routes, API endpoints, sovereignty). An agent could use this to understand available entity types, their fields, and exposed API routes without reading source.
2. **`bimaaji_graph_dump`** — call `ApplicationGraph::toArray()` to return the full application graph as a structured array. High value for agent onboarding ("what does this application expose?").
3. **`bimaaji_mutation_validate`** — call `MutationValidator::validate(MutationRequest)` to validate a proposed code mutation before applying it. Agents proposing code changes could validate before committing.
4. **`bimaaji_dsl_run`** — execute a `TaskPipeline` via the DSL. Would allow agents to issue structured commands (TaskDefinition) and receive a TaskPipelineResult.
5. **`bimaaji_patch_generate`** — call `PatchGenerator` to produce a PatchSet from a description. Agents could request safe code patches for known patterns.

The introspection providers (entity, JSON API, routing, admin, sovereignty, public surface, spec index) each map naturally to a namespaced tool or a parameterized `bimaaji_graph_section` call.

---

## HTTP API Coverage

`packages/bimaaji/` does **not** register its own HTTP routes and has no route file or `RouteProviderInterface` implementation. The package exposes its graph data only through the PHP class surface. `packages/mcp/src/Tools/TraversalTools.php` exists in the MCP package — this is the closest existing bridge (see `mcp-capability.md`).

No existing HTTP API endpoint wraps `ApplicationGraph` or `MutationValidator` as of 2026-05-20.

---

## Implications per option

- **Option 1**: The HTTP API does not already expose bimaaji's graph surface; agents would need to add routes separately — still feasible but not already done.
- **Option 2**: The 5 graph operation candidates above map cleanly to PHP-callable MCP tools; the surface is well-defined and bounded.
- **Option 3**: Node sidecar would need to invoke these same PHP operations via a subprocess or HTTP bridge — adds an integration layer on top of an already-complete PHP surface.
