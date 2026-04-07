import { defineEventHandler, proxyRequest } from 'h3'

export default defineEventHandler((event) => {
  const backendUrl = process.env.NUXT_BACKEND_URL ?? 'http://127.0.0.1:8080'
  return proxyRequest(event, `${backendUrl}${event.path}`)
})
