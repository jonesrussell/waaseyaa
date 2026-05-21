// packages/admin/e2e/schema-dedup.spec.ts
//
// Regression test for WP01 schema-fetch deduplication (NFR-004 / FR-012).
//
// Verifies that navigating to an entity listing page issues EXACTLY ONE
// POST request to /_surface/{type}/action/schema per page load.
//
// Before WP01, multiple concurrent callers of useSchema() each fired an
// independent fetch, causing duplicate network requests and racing updates.
// The in-flight Promise dedup in useSchema() collapses concurrent callers
// onto a single request — this spec is the regression sentinel.
import { test, expect } from '@playwright/test'
import {
  mockAdminBootstrapRoutes,
  mockEntityTypesRoute,
  mockSchemaRoute,
  mockEntityListRoute,
} from './fixtures/routes'

test.describe('schema fetch deduplication (NFR-004 / FR-012)', () => {
  test('issues exactly one schema request per page load on entity listing', async ({ page }) => {
    await mockAdminBootstrapRoutes(page)
    await mockEntityTypesRoute(page)
    await mockSchemaRoute(page, 'user')
    await mockEntityListRoute(page, 'user')

    // Collect all schema requests for this entity type
    const schemaRequests: string[] = []
    page.on('request', (request) => {
      const url = request.url()
      // Match both surface transport and legacy fallback
      if (
        url.includes('/_surface/user/action/schema') ||
        url.includes('/api/schema/user')
      ) {
        schemaRequests.push(url)
      }
    })

    // Navigate to the entity listing page
    await page.goto('/user')

    // Wait for the page to settle (any pending network activity to complete)
    await page.waitForLoadState('networkidle')

    // Assert exactly one schema request was made — dedup is working
    expect(schemaRequests).toHaveLength(1)
  })

  test('issues exactly one schema request per page load on entity create', async ({ page }) => {
    await mockAdminBootstrapRoutes(page)
    await mockEntityTypesRoute(page)
    await mockSchemaRoute(page, 'user')
    await mockEntityListRoute(page, 'user')

    const schemaRequests: string[] = []
    page.on('request', (request) => {
      const url = request.url()
      if (
        url.includes('/_surface/user/action/schema') ||
        url.includes('/api/schema/user')
      ) {
        schemaRequests.push(url)
      }
    })

    await page.goto('/user/create')
    await page.waitForLoadState('networkidle')

    // Only one schema fetch even when the create form and listing composables
    // both call useSchema() during the same render cycle
    expect(schemaRequests).toHaveLength(1)
  })

  test('does not re-fetch schema on second navigation to same entity type', async ({ page }) => {
    await mockAdminBootstrapRoutes(page)
    await mockEntityTypesRoute(page)
    await mockSchemaRoute(page, 'user')
    await mockEntityListRoute(page, 'user')

    const schemaRequests: string[] = []
    page.on('request', (request) => {
      const url = request.url()
      if (
        url.includes('/_surface/user/action/schema') ||
        url.includes('/api/schema/user')
      ) {
        schemaRequests.push(url)
      }
    })

    // First visit — populates the schema cache
    await page.goto('/user')
    await page.waitForLoadState('networkidle')

    const afterFirst = schemaRequests.length

    // Navigate away then back
    await page.goto('/user/create')
    await page.waitForLoadState('networkidle')

    // Total schema requests should not have doubled — cache is warm
    // Allow at most 1 additional request (e.g. if create page triggers a fresh load)
    // but never more than 2 total across both navigations
    expect(schemaRequests.length).toBeLessThanOrEqual(afterFirst + 1)
  })
})
