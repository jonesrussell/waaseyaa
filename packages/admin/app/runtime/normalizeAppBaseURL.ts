import { cleanDoubleSlashes, joinURL, withTrailingSlash } from 'ufo'

/**
 * Canonical app base for admin $fetch and routing: single leading slash, trailing slash,
 * duplicate path slashes collapsed (avoids //_surface/... on strict servers).
 */
export function normalizeAppBaseURL(raw: unknown): string {
  const trimmed = typeof raw === 'string' ? raw.trim() : ''
  const pathBase = trimmed !== '' ? cleanDoubleSlashes(trimmed) : '/'

  return withTrailingSlash(
    pathBase === '/' ? '/' : joinURL('/', pathBase),
  )
}
