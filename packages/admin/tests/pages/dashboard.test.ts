import { describe, it, expect, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import Dashboard from '~/pages/index.vue'
import { entityTypes } from '../fixtures/entityTypes'

describe('Dashboard onboarding', () => {
  it('shows onboarding prompt when no content types exist', async () => {
    vi.stubGlobal('$fetch', vi.fn((url: string) => {
      if (url === '/api/entity-types') {
        return Promise.resolve({ data: entityTypes })
      }
      if (url.startsWith('/api/node_type')) {
        return Promise.resolve({ data: [], meta: { total: 0 } })
      }
      return Promise.reject(new Error(`Unexpected fetch: ${url}`))
    }))

    const wrapper = await mountSuspended(Dashboard)
    await flushPromises()

    expect(wrapper.text()).toContain('Get started with your first content type')
    expect(wrapper.text()).toContain('Use Note (built-in)')
  })
})
