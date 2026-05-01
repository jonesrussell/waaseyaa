# waaseyaa/ingestion

**Layer 0 — Foundation**

Payload validation and ingestion utilities for Waaseyaa applications.

`EnvelopeValidator` enforces the canonical ingestion envelope shape (envelope version, source identification, dedupe key, payload, signature); `PayloadValidatorInterface` is the per-domain hook for content-specific validation. `ValidationResult` carries structured error codes — `ENVELOPE_*`, `PAYLOAD_SCHEMA_NOT_FOUND`, `PAYLOAD_SCHEMA_LOAD_FAILED`, etc. — for editorial dashboard surfacing rather than free-form strings.

Key classes: `EnvelopeValidator`, `PayloadValidatorInterface`, `ValidationResult`.
