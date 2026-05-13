# waaseyaa/graphql

**Layer 6 — Interfaces**

GraphQL endpoint for Waaseyaa with auto-generated schema from registered entity types.

`GraphQlEndpoint` accepts queries at the configured route (registered via `GraphQlRouteProvider`) and resolves them against `EntityTypeManagerInterface`-derived schemas. Connection-style pagination follows the Relay spec: `totalCount` reflects the full unfiltered dataset (matching JSON:API semantics — see #436), while `items` returns only the access-filtered subset. Field resolvers honour `FieldAccessPolicyInterface` so attribute-level access control matches the JSON:API surface.

Key classes: `GraphQlEndpoint`, `GraphQlRouteProvider`, `GraphQlServiceProvider`.

## Implementation gotchas

- **Reference fields keep storage field names**: A field defined as `author_id` with type `entity_reference` produces a GraphQL field named `author_id` (not `author`). It resolves to the nested entity object but the field name includes the `_id` suffix.
