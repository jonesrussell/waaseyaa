import { ref, computed } from 'vue'
import en from '~/i18n/en.json'
import fr from '~/i18n/fr.json'

type Messages = Record<string, string>
type Locale = 'en' | 'fr'

const messages: Record<Locale, Messages> = { en, fr }
const defaultLocale: Locale = 'en'
const currentLocale = ref<Locale>(defaultLocale)

if (
  import.meta.client
  && typeof globalThis.localStorage !== 'undefined'
  && typeof globalThis.localStorage.getItem === 'function'
) {
  const saved = globalThis.localStorage.getItem('waaseyaa.admin.locale')
  if (saved === 'en' || saved === 'fr') {
    currentLocale.value = saved
  }
}

export function useLanguage() {
  function t(key: string, replacements: Record<string, string> = {}): string {
    const msg = messages[currentLocale.value]?.[key] ?? key
    return Object.entries(replacements).reduce(
      (result, [token, value]) => result.replace(`{${token}}`, value),
      msg,
    )
  }

  function setLocale(locale: string) {
    if (locale !== 'en' && locale !== 'fr') {
      return
    }

    currentLocale.value = locale
    if (
      import.meta.client
      && typeof globalThis.localStorage !== 'undefined'
      && typeof globalThis.localStorage.setItem === 'function'
    ) {
      globalThis.localStorage.setItem('waaseyaa.admin.locale', locale)
    }
  }

  const locale = computed(() => currentLocale.value)
  const locales = computed(() => Object.keys(messages) as Locale[])

  return { t, locale, locales, setLocale }
}
