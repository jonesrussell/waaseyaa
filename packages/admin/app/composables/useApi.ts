export function useApi() {
  const config = useRuntimeConfig()
  const appBase = (config.app.baseURL as string) || '/'
  const baseURL = appBase.endsWith('/') ? appBase : `${appBase}/`

  async function apiFetch<T>(path: string, options: Record<string, unknown> = {}): Promise<T> {
    return $fetch<T>(path, {
      baseURL,
      credentials: 'include',
      ...options,
    } as any)
  }

  return { apiFetch }
}
