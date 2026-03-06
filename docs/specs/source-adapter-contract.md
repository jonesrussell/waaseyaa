# Source Adapter Contract (v1.6)

## Scope
- Issue: `#173`
- Surface: deterministic adapter payloads for multi-source ingestion.
- Goal: freeze provenance schema, URI normalization, diagnostics, identity, and optional metadata so downstream “connector → dedupe → merge” layers receive byte-stable inputs.

## Activation
- Each adapter must produce the canonical provenance block below, normalizing every URI before emission. The ingestion engine never re-normalizes URIs.

## Canonical provenance block (strict 8-field order)
1. `source_id` — 64-character lowercase hex SHA-256 of the normalized URI.
2. `source_uri` — the normalized URI (see normalization pipeline).
3. `adapter_type` — `{dataset|api|file|crawl}` (non-empty).
4. `ownership` — enum `{first_party, third_party, federated}`.
5. `synthetic_flag` — boolean.
6. `batch_id` — deterministic batch identifier (string).
7. `ingested_at` — ISO8601 timestamp or UNIX string.
8. `parser_version` — nullable string.

Any other fields must live in `metadata.adapter_extra` (see below).

## Optional metadata namespace
- Adapters may include additive metadata under `metadata.adapter_extra`.
- Rules for this namespace:
  - Flat key-value map; values must be scalar (string, number, boolean).
  - Keys sorted lexicographically; adapters are responsible for ordering.
  - Namespace is optional and isolated—nothing within participates in hashing or identity.
  - Recommended keys: `provider_id`, `provider_scope`, `provider_trace_id`.

Example:
```
metadata: {
  adapter_extra: {
    provider_id: "dataset-a",
    provider_scope: "internal",
    provider_trace_id: "abc123"
  }
}
```

## URI normalization pipeline (deterministic per-step order)
Adapters must transform raw upstream URIs following these steps:
1. **Scheme**
   - Lowercase.
   - Strip default port (`http:80`, `https:443`); preserve others.
2. **Host**
   - Lowercase host component.
   - Keep punycode as-is (no Unicode expansion).
3. **Path**
   - Collapse redundant slashes.
   - Remove trailing slash unless path is `/`.
   - Normalize percent encoding to uppercase hex.
4. **Query**
   - Drop empty query string entirely.
   - Normalize percent-encoding to uppercase hex.
   - Sort query parameters by key.
   - Preserve duplicate keys in observed order; do not reorder values.
5. **Fragment**
   - Strip `#fragment` entirely.
6. **Reconstruction**
   - Build URI with normalized components.
   - Ensure no trailing `?` or `#`, no empty path except `/`.

Adapters must emit one deterministic diagnostic when the raw URI mutates (see diagnostics section). If raw URI is already canonical, skip diagnostic emission.

## Canonical example (mutation path)
- **Raw URI**: `HTTP://Example.COM:80///path//to///Resource/?b=2&a=1&&c=&b=3#fragment`
- **Normalized URI**: `http://example.com/path/to/Resource?a=1&b=2&b=3`
- **source_id**: 64-char hex of SHA-256(normalized URI)
- **Provenance block**:
  ```
  {
    "source_id": "<hash>",
    "source_uri": "http://example.com/path/to/Resource?a=1&b=2&b=3",
    "adapter_type": "dataset",
    "ownership": "first_party",
    "synthetic_flag": false,
    "batch_id": "2026-03-05T17:55:00Z",
    "ingested_at": "2026-03-05T17:55:00Z",
    "parser_version": "1.0.0"
  }
  ```
- **Diagnostic**: `adapter.normalized_uri` when normalization mutates; includes `original_uri`, `normalized_uri`, and ordered list of applied steps (e.g., `scheme_lowercased`, `default_port_stripped`, `path_collapsed`, `trailing_slash_removed`, `query_sorted`, `empty_query_param_removed`, `fragment_stripped`).

## No-op example
- **Raw URI**: `https://example.com/path/to/resource?a=1&b=2&b=3`
- **Normalization**: no changes → same URI, no diagnostic.
- **source_id**: 64-char hex of SHA-256 of the unchanged URI.
- This fixture asserts adapters do not emit diagnostics when the input is canonical.

## Diagnostics
- `adapter.normalized_uri` emitted only when `normalized_uri != original_uri`.
- `message`: `Normalized source_uri to canonical form.`
- `location`: `/source_uri`
- `context`: scalar `original_uri`, scalar `normalized_uri`, and deterministic `normalization_steps[]` describing the ordered mutations that occurred.
- Context fields:
  - `original_uri` (string)
  - `normalized_uri` (string)
  - `normalization_steps` (ordered array of applied step tokens)

## Source identity
- `source_id = sha256(normalized_uri)` encoded as 64-character lowercase hex.
- No upstream IDs are permitted; the ingestion engine must derive `source_id` from the normalized URI.

## Fixtures
- Provide two fixture files under `tests/fixtures/ingestion` that encode the canonical mutation example and the no-op example (see `adapter-normalization-mutation.json` and `adapter-normalization-canonical.json`).
- Tests must assert:
  - deterministic `source_id`
  - fixed provenance block ordering
  - diagnostics only when mutations occur
  - no diagnostics for canonical URIs

With this spec locked, normalization implementation and diagnostics become a mechanical parse → normalize → hash pipeline, setting the stage for connectors (#174) to rely on deterministic adapter outputs.
