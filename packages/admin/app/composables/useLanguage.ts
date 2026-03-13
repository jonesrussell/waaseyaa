import en from '~/i18n/en.json'
import fr from '~/i18n/fr.json'

type Messages = Record<string, string>
type Locale = 'en' | 'fr'

const messages: Record<Locale, Messages> = { en, fr }
const defaultLocale: Locale = 'en'
const COOKIE_NAME = 'waaseyaa.admin.locale'

function isValidLocale(value: unknown): value is Locale {
  return value === 'en' || value === 'fr'
}

export function useLanguage() {
  const localeCookie = useCookie<Locale>(COOKIE_NAME, {
    default: () => defaultLocale,
    sameSite: 'lax',
  })
  const currentLocale = useState<Locale>('waaseyaa.admin.locale', () => {
    return isValidLocale(localeCookie.value) ? localeCookie.value : defaultLocale
  })

  function t(key: string, replacements: Record<string, string> = {}): string {
    const msg = messages[currentLocale.value]?.[key] ?? key
    return Object.entries(replacements).reduce(
      (result, [token, value]) => result.replace(`{${token}}`, value),
      msg,
    )
  }

  function setLocale(locale: string) {
    if (!isValidLocale(locale)) {
      return
    }
    currentLocale.value = locale
    localeCookie.value = locale
  }

  function entityLabel(id: string, fallback: string): string {
    const key = `entity_type_${id}`
    const msg = messages[currentLocale.value]?.[key]
    return msg !== undefined ? msg : fallback
  }

  const locale = computed(() => currentLocale.value)
  const locales = computed(() => Object.keys(messages) as Locale[])

  return { t, entityLabel, locale, locales, setLocale }
}
