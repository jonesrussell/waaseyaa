# waaseyaa/workflows

**Layer 3 — Services**

Editorial workflow and content visibility for Waaseyaa applications.

`EditorialVisibilityResolver` determines whether an entity can be rendered based on its publish status, the requesting account's permissions, and preview mode. Returns `allowed`, `neutral`, or `forbidden` results consumed by `SsrPageHandler`. Also provides workflow state transition primitives for moderation queues.

Key classes: `EditorialVisibilityResolver`, `WorkflowState`, `WorkflowTransition`.
