// packages/admin/tests/unit/composables/useNavGroups.test.ts
import { describe, it, expect } from 'vitest'
import { groupEntityTypes, type EntityTypeInfo } from '~/composables/useNavGroups'

const K = { id: 'id', label: 'label' }

describe('groupEntityTypes', () => {
  it('places user into the people group', () => {
    const groups = groupEntityTypes([{ id: 'user', label: 'User', keys: K }])
    const people = groups.find((g) => g.key === 'people')
    expect(people?.entityTypes).toEqual([{ id: 'user', label: 'User', keys: K }])
  })

  it('places node and node_type into the content group', () => {
    const groups = groupEntityTypes([
      { id: 'node', label: 'Content', keys: K },
      { id: 'node_type', label: 'Content Type', keys: K },
    ])
    const content = groups.find((g) => g.key === 'content')
    expect(content?.entityTypes.map((e) => e.id)).toEqual(['node', 'node_type'])
  })

  it('omits groups that have no matching entity types', () => {
    const groups = groupEntityTypes([{ id: 'user', label: 'User', keys: K }])
    const keys = groups.map((g) => g.key)
    expect(keys).toContain('people')
    expect(keys).not.toContain('content')
    expect(keys).not.toContain('taxonomy')
  })

  it('places unknown entity types into an other group', () => {
    const groups = groupEntityTypes([{ id: 'custom_thing', label: 'Custom', keys: K }])
    expect(groups).toHaveLength(1)
    expect(groups[0].key).toBe('other')
    expect(groups[0].entityTypes).toEqual([{ id: 'custom_thing', label: 'Custom', keys: K }])
  })

  it('returns empty array for empty input', () => {
    expect(groupEntityTypes([])).toEqual([])
  })

  it('provides humanized fallback label for unknown group key', () => {
    const types: EntityTypeInfo[] = [
      { id: 'elder_profile', label: 'Elder Profile', keys: K, group: 'elders' },
    ]
    const groups = groupEntityTypes(types)
    expect(groups).toHaveLength(1)
    expect(groups[0].key).toBe('elders')
    expect(groups[0].labelKey).toBe('nav_group_elders')
    expect(groups[0].label).toBe('Elders') // humanized fallback
  })

  it('handles all 12 registered entity types without an other group', () => {
    const all = [
      { id: 'user', label: 'User', keys: K },
      { id: 'node', label: 'Content', keys: K },
      { id: 'node_type', label: 'Content Type', keys: K },
      { id: 'taxonomy_term', label: 'Term', keys: K },
      { id: 'taxonomy_vocabulary', label: 'Vocabulary', keys: K },
      { id: 'media', label: 'Media', keys: K },
      { id: 'media_type', label: 'Media Type', keys: K },
      { id: 'path_alias', label: 'Path Alias', keys: K },
      { id: 'menu', label: 'Menu', keys: K },
      { id: 'menu_link', label: 'Menu Link', keys: K },
      { id: 'workflow', label: 'Workflow', keys: K },
      { id: 'pipeline', label: 'Pipeline', keys: K },
    ]
    const groups = groupEntityTypes(all)
    const keys = groups.map((g) => g.key)
    expect(keys).not.toContain('other')
    const total = groups.reduce((sum, g) => sum + g.entityTypes.length, 0)
    expect(total).toBe(12)
  })
})
