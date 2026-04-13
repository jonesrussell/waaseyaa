# Bimaaji — Application Graph & Agent Mutation Layer

## Purpose

Bimaaji provides machine-readable introspection of a booted Waaseyaa application and a safe mutation protocol for AI agents. It answers: "What does this application contain, and how can an agent safely change it?"

The name comes from Anishinaabemowin *bimaaji* — "to give life to" — reflecting its role in making the application's structure visible and actionable.

## Architecture

Bimaaji sits at **Layer 5 (AI)** alongside `ai-schema`, `ai-agent`, `ai-pipeline`, and `ai-vector`. It reads from lower layers (entity system, routing, access control, admin surface) but never writes to them directly — mutations go through a validated protocol.

```
┌─────────────────────────────────────────────┐
│                 ApplicationGraph             │
│  ┌──────────┬──────────┬──────────┬───────┐  │
│  │ entities │ routing  │  jsonapi │ admin │  │
│  │ section  │ section  │  section │ sect. │  │
│  ├──────────┼──────────┼──────────┼───────┤  │
│  │sovereignty│ public  │ spec    │  ...  │  │
│  │ section  │ surface  │ index   │       │  │
│  └──────────┴──────────┴──────────┴───────┘  │
├─────────────────────────────────────────────┤
│         GraphSectionProviderInterface        │
├─────────────────────────────────────────────┤
│  MutationRequest → Validator → MutationResult│
│  TaskDSL → MutationRequest → PatchSet        │
└─────────────────────────────────────────────┘
```

## Core Concepts

### Application Graph

A versioned, deterministic JSON document describing the full application structure. Built by `ApplicationGraphGenerator` from registered `GraphSectionProviderInterface` implementations.

**Key types:**
- `GraphSection` — immutable DTO: `key`, `version`, `data`
- `GraphSectionProviderInterface` — `getKey(): string`, `provide(): GraphSection`
- `ApplicationGraph` — versioned container of sections, `toArray()` serializes to JSON-safe structure
- `ApplicationGraphGenerator` — composes providers, handles failures (log+skip unless strict mode)

### Graph Sections

Each section maps a subsystem:

| Section key | Source | Provider |
|-------------|--------|----------|
| `entities` | `EntityTypeManager::getDefinitions()` | `EntityIntrospectionProvider` |
| `jsonapi` | Route collection + `ResourceSerializer` | `JsonApiIntrospectionProvider` |
| `admin` | `AbstractAdminSurfaceHost::buildCatalog()` | `AdminIntrospectionProvider` |
| `routing` | `RouteCollection` options/defaults | `RoutingIntrospectionProvider` |
| `sovereignty` | `SovereigntyProfile` enum | `SovereigntyIntrospectionProvider` |
| `public_surface` | SSR paths + auth classification | `PublicSurfaceProvider` |
| `spec_index` | `docs/specs/*` file index | `SpecIndexProvider` |

### Mutation Protocol

Request/result types for agent-safe changes. No filesystem writes — the protocol validates intent against the application graph.

- `MutationRequest` — what the agent wants to change (entity type, field, route, etc.)
- `MutationResult` — success/failure with error codes, validated against graph
- Sovereignty violations delegated to guardrail rules

### Patch Generator

Converts accepted `MutationResult` into reviewable patches:
- PHP files: AST-safe via `nikic/php-parser`, round-trip tested
- Non-PHP: constrained operations with risk flags
- Output: file path, content hashes, diff text

### Task DSL

Versioned YAML/JSON DSL mapping high-level tasks (`add_field`, `add_entity_type`) to `MutationRequest` → `PatchSet` pipelines. JSON Schema validated.

### Sovereignty Guardrails

Declarative rules that disallow mutations violating the deployment posture per `SovereigntyProfile`. Integrated as mutation validators.

## File Layout

```
packages/bimaaji/
├── src/
│   ├── Graph/
│   │   ├── ApplicationGraph.php
│   │   ├── ApplicationGraphGenerator.php
│   │   ├── GraphSection.php
│   │   └── GraphSectionProviderInterface.php
│   ├── Introspection/
│   │   ├── Entity/
│   │   ├── JsonApi/
│   │   ├── Admin/
│   │   ├── Routing/
│   │   ├── Sovereignty/
│   │   └── PublicSurface/
│   ├── Mutation/
│   │   ├── MutationRequest.php
│   │   └── MutationResult.php
│   ├── Patch/
│   ├── Dsl/
│   ├── Policy/
│   └── Spec/
├── tests/
│   ├── Unit/
│   └── Integration/
└── resources/
    └── schema/
```

## Dependencies

- `waaseyaa/foundation` — `SovereigntyProfile`, `LoggerInterface`
- `waaseyaa/entity` — `EntityTypeManagerInterface`, `EntityTypeInterface`
- `waaseyaa/routing` — route collection access
- `waaseyaa/api` — `ResourceSerializer` mapping
- `waaseyaa/admin-surface` — catalog introspection
- `nikic/php-parser` — AST-safe patch generation

## Design Decisions

1. **Read-only introspection, write-only mutation** — Bimaaji never modifies application state during introspection. Mutations are separate, validated, and produce patches for human review.
2. **Non-fatal provider failures** — A broken introspection provider logs a warning and is omitted from the graph, unless strict mode is enabled.
3. **Versioned graph schema** — The top-level graph and each section carry version strings for backward compatibility.
4. **No spec bodies in graph** — `spec_index` contains file paths and metadata, not full spec content, to keep the graph compact.
