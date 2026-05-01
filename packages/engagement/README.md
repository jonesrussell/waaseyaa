# waaseyaa/engagement

**Layer 2 — Content Types**

Social engagement entities for Waaseyaa: reactions, comments, follows.

Provides three content entities (`Reaction`, `Comment`, `Follow`) with their own access policies that respect parent-content visibility — comments on a draft post are not visible to anonymous readers even if the comment itself is public. `EngagementAccessPolicy` enforces the parent-cascade.

Key classes: `Reaction`, `Comment`, `Follow`, `EngagementAccessPolicy`, `EngagementServiceProvider`.
