/**
 * Coercion helpers for Nuxt runtime-config boundary values.
 *
 * Nuxt's runtime-config serializer may coerce digit-string env vars to numbers.
 * These helpers normalize values to their intended TypeScript types at the
 * useAdminConfig() boundary. Do NOT use string-compare coercions at call sites.
 *
 * @api FR-007
 */

/**
 * Coerces a runtime-config value to boolean.
 *
 * Truthy inputs (case-insensitive): true, 1, '1', 'true', 'yes', 'on'
 * All other inputs → false.
 *
 * @api
 */
export function asBoolean(value: unknown, defaultValue: boolean = false): boolean {
  if (value === undefined || value === null) return defaultValue
  if (typeof value === 'boolean') return value
  if (typeof value === 'number') return value === 1
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase()
    return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on'
  }
  return defaultValue
}

/**
 * Coerces a runtime-config value to string.
 * Returns defaultValue if value is undefined, null, or empty string.
 *
 * @api
 */
export function asString(value: unknown, defaultValue: string = ''): string {
  if (value === undefined || value === null) return defaultValue
  const str = String(value).trim()
  return str.length > 0 ? str : defaultValue
}

/**
 * Coerces a runtime-config value to a URL string.
 * Trims trailing slashes. Returns defaultValue if empty.
 *
 * @api
 */
export function asUrl(value: unknown, defaultValue: string = ''): string {
  const str = asString(value, defaultValue)
  // Strip trailing slash only for non-root paths (bare '/' is a valid root URL)
  return str.length > 1 && str.endsWith('/') ? str.slice(0, -1) : str
}
