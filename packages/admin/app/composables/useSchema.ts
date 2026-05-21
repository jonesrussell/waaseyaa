import { ref, type Ref } from 'vue'
import type { SchemaProperty, EntitySchema } from '../contracts/schema'
import { requireAdminRuntime } from './useAdminRuntime'

export type { SchemaProperty, EntitySchema }

const schemaCache = new Map<string, EntitySchema>()
const inflightCache = new Map<string, Promise<EntitySchema>>()

export function useSchema(entityType: string) {
  const schema: Ref<EntitySchema | null> = ref(null)
  const loading = ref(false)
  const error: Ref<string | null> = ref(null)

  async function fetch() {
    if (schemaCache.has(entityType)) {
      schema.value = schemaCache.get(entityType)!
      return
    }

    // FR-001: return in-flight Promise if one exists for this entityType
    const inflight = inflightCache.get(entityType)
    if (inflight !== undefined) {
      schema.value = await inflight
      return
    }

    loading.value = true
    error.value = null

    try {
      // FR-001: register the in-flight Promise before awaiting.
      // requireAdminRuntime() call is inside try so a synchronous throw
      // (e.g. runtime unavailable) is caught and sets error.value.
      const promise = requireAdminRuntime()
        .transport.schema(entityType)
        .then((result: EntitySchema) => {
          schemaCache.set(entityType, result)
          inflightCache.delete(entityType) // clean up after resolution
          return result
        })
        .catch((e: unknown) => {
          inflightCache.delete(entityType) // FR-002: clear on rejection, no poison-caching
          throw e
        })

      inflightCache.set(entityType, promise)
      schema.value = await promise
    } catch (e: any) {
      error.value = e.detail ?? e.message ?? 'Failed to load schema'
    } finally {
      loading.value = false
    }
  }

  function invalidate() {
    schemaCache.delete(entityType)
    inflightCache.delete(entityType) // FR-003: clear in-flight on invalidate
  }

  /**
   * Return properties sorted by x-weight.
   *
   * When `editable` is true:
   *  - System readOnly fields (id, uuid — no x-access-restricted) are excluded.
   *  - Hidden widgets are excluded.
   *  - Access-restricted fields (readOnly + x-access-restricted) are kept — they
   *    render as disabled widgets so users can see but not edit the value.
   *
   * When false (default), all properties are returned.
   */
  function sortedProperties(editable = false) {
    if (!schema.value) return []

    const entries = Object.entries(schema.value.properties)

    const filtered = editable
      ? entries.filter(([, prop]) => {
          if (prop['x-widget'] === 'hidden') return false
          // System readOnly (no x-access-restricted) → exclude from form.
          if (prop.readOnly && !prop['x-access-restricted']) return false
          return true
        })
      : entries

    return filtered.sort(([, a], [, b]) => {
      const wa = a['x-weight'] ?? 0
      const wb = b['x-weight'] ?? 0
      return wa - wb
    })
  }

  return { schema, loading, error, fetch, invalidate, sortedProperties }
}
