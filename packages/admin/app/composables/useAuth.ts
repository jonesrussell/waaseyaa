export interface AuthUser {
  id: number
  name: string
  email: string
  roles: string[]
}

const STATE_KEY = 'waaseyaa.auth.user'

export function useAuth() {
  const currentUser = useState<AuthUser | null>(STATE_KEY, () => null)
  const isAuthenticated = computed(() => currentUser.value !== null)

  async function fetchMe(): Promise<void> {
    try {
      const response = await $fetch<{ data: AuthUser }>('/api/user/me')
      currentUser.value = response.data ?? null
    }
    catch {
      currentUser.value = null
    }
  }

  async function login(username: string, password: string): Promise<void> {
    const response = await $fetch<{ data: AuthUser }>('/api/auth/login', {
      method: 'POST',
      body: { username, password },
    })
    currentUser.value = response.data ?? null
  }

  async function logout(): Promise<void> {
    await $fetch('/api/auth/logout', { method: 'POST' })
    currentUser.value = null
  }

  return { currentUser, isAuthenticated, fetchMe, login, logout }
}
