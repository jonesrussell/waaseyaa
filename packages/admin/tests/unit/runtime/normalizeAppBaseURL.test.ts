import { describe, expect, it } from 'vitest'
import { normalizeAppBaseURL } from '~/runtime/normalizeAppBaseURL'

describe('normalizeAppBaseURL', () => {
  it('uses / when base is missing, blank, or non-string', () => {
    expect(normalizeAppBaseURL(undefined)).toBe('/')
    expect(normalizeAppBaseURL(null)).toBe('/')
    expect(normalizeAppBaseURL('')).toBe('/')
    expect(normalizeAppBaseURL('   ')).toBe('/')
  })

  it('adds trailing slash and single leading slash for path bases', () => {
    expect(normalizeAppBaseURL('/admin')).toBe('/admin/')
    expect(normalizeAppBaseURL('admin')).toBe('/admin/')
  })

  it('collapses duplicate slashes in the path (no //segment)', () => {
    expect(normalizeAppBaseURL('//admin//')).toBe('/admin/')
    expect(normalizeAppBaseURL('/admin//catalog//')).toBe('/admin/catalog/')
  })
})
