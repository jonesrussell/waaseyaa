// packages/admin/tests/unit/composables/useAuth.test.ts
import { describe, it, expect, vi } from 'vitest'
import { useAuth } from '~/composables/useAuth'

describe('useAuth', () => {
  it('isAuthenticated is false when no user is loaded', () => {
    const { isAuthenticated } = useAuth()
    expect(isAuthenticated.value).toBe(false)
  })

  it('fetchMe sets currentUser and isAuthenticated on success', async () => {
    const mockFetch = vi.fn().mockResolvedValue({
      data: { id: 1, name: 'alice', email: 'alice@example.com', roles: ['admin'] },
    })
    vi.stubGlobal('$fetch', mockFetch)

    const { fetchMe, isAuthenticated, currentUser } = useAuth()
    await fetchMe()

    expect(isAuthenticated.value).toBe(true)
    expect(currentUser.value?.name).toBe('alice')
    expect(mockFetch).toHaveBeenCalledWith('/api/user/me')
  })

  it('fetchMe leaves isAuthenticated false on 401', async () => {
    const mockFetch = vi.fn().mockRejectedValue(Object.assign(new Error('Unauthorized'), { status: 401 }))
    vi.stubGlobal('$fetch', mockFetch)

    const { fetchMe, isAuthenticated } = useAuth()
    await fetchMe()

    expect(isAuthenticated.value).toBe(false)
  })

  it('login calls POST /api/auth/login with credentials', async () => {
    const mockFetch = vi.fn().mockResolvedValue({
      data: { id: 2, name: 'bob', email: 'bob@example.com', roles: [] },
    })
    vi.stubGlobal('$fetch', mockFetch)

    const { login, isAuthenticated, currentUser } = useAuth()
    await login('bob', 'secret')

    expect(mockFetch).toHaveBeenCalledWith('/api/auth/login', {
      method: 'POST',
      body: { username: 'bob', password: 'secret' },
    })
    expect(isAuthenticated.value).toBe(true)
    expect(currentUser.value?.name).toBe('bob')
  })

  it('login throws on failed credentials', async () => {
    const mockFetch = vi.fn().mockRejectedValue(Object.assign(new Error('Unauthorized'), { status: 401 }))
    vi.stubGlobal('$fetch', mockFetch)

    const { login } = useAuth()
    await expect(login('bad', 'creds')).rejects.toThrow()
  })

  it('logout calls POST /api/auth/logout and clears user', async () => {
    const mockFetch = vi.fn()
      .mockResolvedValueOnce({ data: { id: 1, name: 'alice', email: '', roles: [] } })
      .mockResolvedValueOnce({ meta: { message: 'Logged out.' } })
    vi.stubGlobal('$fetch', mockFetch)

    const { fetchMe, logout, isAuthenticated } = useAuth()
    await fetchMe()
    expect(isAuthenticated.value).toBe(true)

    await logout()
    expect(mockFetch).toHaveBeenLastCalledWith('/api/auth/logout', { method: 'POST' })
    expect(isAuthenticated.value).toBe(false)
  })
})
