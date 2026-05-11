import { describe, it, expect } from 'vitest'
import en from '../../../app/i18n/en.json'
import fr from '../../../app/i18n/fr.json'

const REQUIRED_KEYS = [
  'workflow_history_title',
  'workflow_history_empty',
  'workflow_history_by',
  'workflow_history_at',
] as const

describe('workflow transition-history i18n', () => {
  it.each(REQUIRED_KEYS)('en defines %s', (key) => {
    expect((en as Record<string, string>)[key]).toBeTruthy()
  })

  it.each(REQUIRED_KEYS)('fr defines %s', (key) => {
    expect((fr as Record<string, string>)[key]).toBeTruthy()
  })

  it('en and fr have matching key sets for transition history', () => {
    const enKeys = REQUIRED_KEYS.filter(k => (en as Record<string, string>)[k])
    const frKeys = REQUIRED_KEYS.filter(k => (fr as Record<string, string>)[k])
    expect(enKeys).toEqual(frKeys)
  })
})
