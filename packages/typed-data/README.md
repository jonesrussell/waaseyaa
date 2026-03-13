# waaseyaa/typed-data

**Layer 0 — Foundation**

Typed data validation and coercion for Waaseyaa applications.

Provides a type system for entity field values with runtime coercion (string→int, null→default, etc.) and validation constraints. Integrates with Symfony Validator for constraint-based validation. Used by the field system to validate entity values before persistence.

Key classes: `TypedDataInterface`, `DataDefinition`, `TypedDataManager`.
