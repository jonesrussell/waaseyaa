export default defineNuxtRouteMiddleware(async (to) => {
  // Auth check runs client-side only. The PHP backend is the authoritative
  // security layer; the Nuxt middleware is a UX redirect guard.
  if (!import.meta.client) {
    return
  }

  if (to.path === '/login') {
    return
  }

  const { isAuthenticated, checkAuth } = useAuth()

  await checkAuth()

  if (!isAuthenticated.value) {
    return navigateTo('/login')
  }
})
