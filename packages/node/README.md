# waaseyaa/node

**Layer 2 — Content Types**

Node (content) entity type for Waaseyaa applications.

Defines the `node` entity type — the primary content entity. Supports arbitrary bundles (content types), editorial workflow states (published/unpublished), and field-based rendering via the SSR layer. Access control follows the standard pattern: `administer nodes` for admin, `access content` for viewing published nodes.

Key classes: `Node`, `NodeAccessPolicy`, `NodeServiceProvider`.
