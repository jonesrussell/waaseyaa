# Entity Vector-Indexed Attribute — Specification (STUB)

**Status**: Stub. Full spec via `/spec-kitty.specify` when ready to plan.
**Predecessor**: `attribute-first-entity-definition-01KQ6DXE` (recommended; depends on `#[Field]` infrastructure)

---

## Scope statement

Make the framework's "AI-first" claim concrete with a single end-to-end demo: a `#[VectorIndexed]` attribute on entity properties that auto-wires text content into `waaseyaa/ai-vector` for semantic search. App authors get embedding-backed similarity search by adding one line per searchable field — no glue code, no service-layer wiring, no separate index registration.

## Example shape

```php
#[ContentEntityType(id: 'note', label: 'Note')]
final class Note extends ContentEntityBase {
    #[Field, VectorIndexed] public string $title;
    #[Field, VectorIndexed(embedder: 'openai-3-small')] public ?string $body;
    #[Field] public ?string $author;
}

// Then anywhere in app code:
$results = $entityRepository->similarTo(Note::class, "transformer architectures", limit: 5);
```

## In-scope sketch

- `#[VectorIndexed]` attribute on entity properties, optional `embedder:` parameter (chooses an embedding model from `waaseyaa/ai-vector` registered providers).
- POST_SAVE event listener (or storage-driver hook) that extracts the indexed property values, generates embeddings via the configured provider, and writes vector + metadata to the vector store.
- Re-index on field-value change (skip if unchanged).
- DELETE event listener that removes vector entries when an entity is deleted.
- A simple repository method (e.g. `EntityRepository::similarTo(string $class, string $query, int $limit)`) that performs the round-trip embedding + vector search and returns hydrated entities.
- One R&D app uses it end-to-end as a demonstration.

## Out-of-scope sketch

- Vector store backend implementations (use whatever `waaseyaa/ai-vector` already supports).
- Embedding provider implementations (use what `waaseyaa/ai-agent` / `waaseyaa/ai-pipeline` already wire).
- Re-ranking, hybrid search (vector + keyword), filtered similarity. All future work.
- UI / admin surface for vector index management.

## Open design questions

- Sync vs async indexing: queue job (via `waaseyaa/queue`) for embedding generation, or inline in POST_SAVE? Inline is simpler; async scales better.
- Embedding-provider configuration: per-attribute, per-entity-type, or global default? Default to global with per-attribute override.
- Failure semantics: if the embedding provider is down, does save fail or does the vector index lag? Default: lag (queue retry).
- Versioning: when the embedder changes, do existing vectors get re-embedded? Probably yes, on a maintenance command.
- Privacy: does the attribute support a `redact:` callback or `excludeWhen:` predicate?

## Predecessor dependency

- M1 `attribute-first-entity-definition` (the `#[Field]` attribute is the natural sibling). Could in principle ship without M1 by being its own attribute, but consistency argues for waiting.
