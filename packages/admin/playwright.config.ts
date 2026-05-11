// packages/admin/playwright.config.ts
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:3000/admin',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
  ],
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:3000/admin',
    reuseExistingServer: !process.env.CI,
    // 240s gives cold-start CI runners headroom over Nuxt's prepare + Vite
    // optimize + Nitro build sequence; locally `nuxt dev` is ready in seconds.
    timeout: 240 * 1000,
  },
})
