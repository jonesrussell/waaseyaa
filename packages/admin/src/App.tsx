/**
 * Aurora Admin SPA — Schema-driven admin interface.
 *
 * Architecture:
 * 1. Fetches OpenAPI spec from /api/openapi.json on startup
 * 2. Auto-generates navigation from discovered entity types
 * 3. Auto-generates list views from entity query endpoints
 * 4. Auto-generates forms from JSON Schema field definitions
 * 5. Integrates AI assistant via MCP tools from aurora/ai-schema
 *
 * All CRUD operations use the same JSON:API endpoints as external consumers.
 * No separate admin API — one API surface for humans, AI agents, and integrations.
 */
export function App() {
    return (
        <div>
            <h1>Aurora Admin</h1>
            <p>Schema-driven admin interface. Run <code>npm install && npm run dev</code> to start.</p>
        </div>
    );
}
