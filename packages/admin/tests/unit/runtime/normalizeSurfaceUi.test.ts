import { describe, it, expect } from 'vitest'
import { normalizeSurfaceUi } from '~/runtime/normalizeSurfaceUi'

describe('normalizeSurfaceUi', () => {
  it('returns empty arrays when ui is undefined', () => {
    expect(normalizeSurfaceUi(undefined)).toEqual({
      headerLinks: [],
      sidebarItems: [],
    })
  })

  it('filters invalid rows and keeps valid optional fields', () => {
    expect(
      normalizeSurfaceUi({
        headerLinks: [
          { label: ' A ', href: ' /x ' },
          { label: '', href: '/y' },
          { label: 'Z', href: '', external: true },
        ],
        sidebarItems: [
          { id: 'one', label: 'One', href: '/1', group: 'nav_group_custom', weight: 2 },
          { id: '', label: 'Bad', href: '/2' },
        ],
      }),
    ).toEqual({
      headerLinks: [{ label: 'A', href: '/x' }],
      sidebarItems: [{ id: 'one', label: 'One', href: '/1', group: 'nav_group_custom', weight: 2 }],
    })
  })
})
