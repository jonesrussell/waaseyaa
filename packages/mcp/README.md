# waaseyaa/mcp

**Layer 6 — Interfaces**

Model Context Protocol (MCP) endpoint for Waaseyaa applications.

Exposes Waaseyaa entity types and content operations via the MCP specification, enabling AI agents and IDE tools (e.g. Claude Code) to read and write application data. Depends on the API and entity layers.

Key classes: `McpServerCard` (route controller at `/.well-known/mcp.json`), `McpRouteProvider`, `EditorialTools`. See `docs/specs/mcp-endpoint.md`.
