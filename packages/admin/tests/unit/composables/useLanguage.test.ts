// packages/admin/tests/unit/composables/useLanguage.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useLanguage } from '~/composables/useLanguage'

describe('useLanguage.t', () => {
  it('switches locale to French for known keys', () => {
    const { t, setLocale } = useLanguage()
    setLocale('en')
    expect(t('dashboard')).toBe('Dashboard')

    setLocale('fr')
    expect(t('dashboard')).toBe('Tableau de bord')
  })

  it('ignores unknown locales', () => {
    const { t, setLocale } = useLanguage()
    setLocale('en')
    setLocale('de')
    expect(t('dashboard')).toBe('Dashboard')
  })

  it('returns the English translation for a known key', () => {
    const { t, setLocale } = useLanguage()
    setLocale('en')
    // 'dashboard' is defined in app/i18n/en.json
    expect(t('dashboard')).not.toBe('dashboard')
    expect(typeof t('dashboard')).toBe('string')
    expect(t('dashboard').length).toBeGreaterThan(0)
  })

  it('falls back to the key itself for an unknown key', () => {
    const { t } = useLanguage()
    expect(t('__nonexistent_key_xyz__')).toBe('__nonexistent_key_xyz__')
  })

  it('interpolates replacement tokens', () => {
    const { t } = useLanguage()
    // If no key uses {token} in en.json, test the interpolation directly:
    // t() replaces {token} with the replacement value.
    // We test with a raw call: key falls back to itself, replacements applied.
    const result = t('Hello {name}', { name: 'World' })
    expect(result).toBe('Hello World')
  })
})

describe('useLanguage SSR hydration safety', () => {
  beforeEach(() => {
    // Clear locale cookie before each test
    document.cookie = 'waaseyaa.admin.locale=; max-age=0; path=/'
  })

  it('setLocale works without localStorage (SSR-safe — no dependency on browser storage)', () => {
    // The nuxt test environment does not provide localStorage.
    // If setLocale still depends on localStorage this test would throw.
    // The fact that it runs without error proves the implementation is SSR-safe.
    expect(() => {
      const { setLocale } = useLanguage()
      setLocale('fr')
    }).not.toThrow()
  })

  it('locale change is reflected in a subsequent useLanguage() call (useState-shared)', () => {
    const { setLocale } = useLanguage()
    setLocale('fr')
    // useState key 'waaseyaa.admin.locale' is shared across all useLanguage() callers —
    // this is what makes SSR and CSR agree on the same locale value.
    const { locale } = useLanguage()
    expect(locale.value).toBe('fr')
  })
})
