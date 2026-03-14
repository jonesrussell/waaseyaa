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

  it('checkAuth calls fetchMe only once across multiple invocations', async () => {
    const mockFetch = vi.fn().mockResolvedValue({
      data: { id: 3, name: 'dave', email: '', roles: [] },
    })
    vi.stubGlobal('$fetch', mockFetch)

    const { checkAuth } = useAuth()
    await checkAuth()
    await checkAuth()
    await checkAuth()

    // fetchMe should only be called once — subsequent checkAuth calls are no-ops
    expect(mockFetch).toHaveBeenCalledTimes(1)
  })

  it('checkAuth calls fetchMe again after logout resets the checked flag', async () => {
    // authChecked may already be true from the previous test (useState persists).
    // This test specifically verifies that logout() resets the flag so the next
    // checkAuth() triggers a fresh fetchMe call.
    const mockFetch = vi.fn()
      .mockResolvedValueOnce({ meta: { message: 'Logged out.' } })
      .mockResolvedValueOnce({ data: { id: 3, name: 'dave', email: '', roles: [] } })
    vi.stubGlobal('$fetch', mockFetch)

    const { checkAuth, logout } = useAuth()
    await logout()     // resets authChecked to false
    await checkAuth()  // should now call fetchMe since flag was reset

    expect(mockFetch).toHaveBeenCalledTimes(2)
    expect(mockFetch).toHaveBeenLastCalledWith('/api/user/me')
  })
})
