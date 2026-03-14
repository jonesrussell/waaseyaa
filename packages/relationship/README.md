# waaseyaa/relationship

**Layer 2 — Content Types**

Entity relationship modeling for Waaseyaa applications.

Defines the `relationship` entity type for typed connections between entities (e.g. author→article, tag→post). `RelationshipDiscoveryService` and `RelationshipTraversalService` power relationship-aware rendering in the SSR layer. `RelationshipAccessPolicy` is auto-discovered via `#[PolicyAttribute]`. See `docs/specs/relationship-modeling.md`.

Key classes: `Relationship`, `RelationshipDiscoveryService`, `RelationshipTraversalService`, `RelationshipAccessPolicy`, `RelationshipServiceProvider`.
