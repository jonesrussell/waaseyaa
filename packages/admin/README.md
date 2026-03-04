# @waaseyaa/admin

Schema-driven admin SPA for Waaseyaa (Nuxt 3 + Vue 3).

## i18n

Translations live in `app/i18n/` and are consumed by `useLanguage()`.

### Current locales
- `en` (`app/i18n/en.json`)
- `fr` (`app/i18n/fr.json`)

### Add a new locale
1. Create `app/i18n/<locale>.json` with the same keys used in `en.json`.
2. Import and register it in `app/composables/useLanguage.ts`:
   - `import <locale> from '~/i18n/<locale>.json'`
   - extend the `Locale` union and `messages` map.
3. The topbar language selector in `components/layout/AdminShell.vue` will render it automatically from `useLanguage().locales`.
4. Add/update unit tests in:
   - `tests/unit/composables/useLanguage.test.ts`
   - `tests/components/layout/AdminShell.test.ts`
