# waaseyaa/access

**Layer 1 — Core Data**

Access control primitives for Waaseyaa applications.

Provides `AccessPolicyInterface`, `AccessResult` (allowed / neutral / forbidden), `AccountInterface`, and the `#[PolicyAttribute]` attribute used for auto-discovery of entity-level and field-level access policies. The `AccessGate` evaluates registered policies and the `AccessChecker` enforces route-level constraints (`_public`, `_permission`, `_role`, `_gate`).

Key classes: `AccessPolicyInterface`, `FieldAccessPolicyInterface`, `AccessResult`, `AccountInterface`, `AccessGate`, `AccessChecker`.
