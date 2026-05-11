import withNuxt from './.nuxt/eslint.config.mjs'

// Baseline configuration for the admin SPA.
// Rules tuned permissively at adoption time so the suite passes at zero errors
// and surfaces existing tech debt as warnings. A follow-up "baseline cleanup"
// pass can tighten these rules incrementally.
export default withNuxt(
  {
    rules: {
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      '@typescript-eslint/no-require-imports': 'warn',
      'vue/no-v-html': 'warn',
      'vue/valid-template-root': 'warn',
      'vue/no-multiple-template-root': 'warn',
      'import/no-duplicates': 'warn',
    },
  },
)
