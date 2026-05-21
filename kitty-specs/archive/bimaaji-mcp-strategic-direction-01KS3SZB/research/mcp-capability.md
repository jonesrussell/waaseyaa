# packages/mcp/ Capability Snapshot

**Date gathered**: 2026-05-20
**Source**: `packages/mcp/src/` (19 PHP files), `docs/specs/mcp-endpoint.md`

---

## PHP Tool Registration: YES (PARTIAL — via `packages/ai` bridge, not direct self-registration)

The `packages/mcp/` host supports PHP-tool registration, but the registration path runs through `Waaseyaa\AI\Tools\ToolRegistryInterface` (in `packages/ai-*`), not through a standalone `packages/mcp/`-internal API. The MCP endpoint exposes tools via its `Bridge/ToolRegistryInterface` and `Bridge/ToolExecutorInterface`, both marked `@api`. The concrete `AgentToolRegistryBridge` (also `@api`) adapts the framework-wide AI tool registry into the MCP endpoint.

The pattern is:
1. A tool is registered in `Waaseyaa\AI\Tools\ToolRegistryInterface` (the framework-wide agent tool registry, in `packages/ai-agent` or equivalent).
2. `AgentToolRegistryBridge` pulls all registered tools and exposes them via the MCP `tools/list` and `tools/call` RPC methods.
3. The MCP endpoint (`McpEndpoint`) holds `ToolRegistryInterface` + `ToolExecutorInterface` injected at construction, and delegates all tool calls through them.

There is also a standalone `abstract class McpTool` with four concrete subclasses: `DiscoveryTools`, `EditorialTools`, `EntityTools`, `TraversalTools`. These are direct tool implementations in `packages/mcp/src/Tools/` — not registered via the bridge, but showing that the host has its own tool grouping pattern too.

---

## Key Interfaces/Classes Found

| FQCN | Purpose | Relevant to Option 2? |
|------|---------|----------------------|
| `Waaseyaa\Mcp\Bridge\ToolRegistryInterface` | MCP-internal registry interface: `getTools(): array`, `getTool(string $name): ?AgentTool` | **Yes** — bimaaji tools must satisfy this contract |
| `Waaseyaa\Mcp\Bridge\ToolExecutorInterface` | MCP-internal executor: `execute(string $toolName, array $arguments): array` | **Yes** — bimaaji must implement or delegate here |
| `Waaseyaa\Mcp\Bridge\AgentToolRegistryBridge` | Adapts `Waaseyaa\AI\Tools\ToolRegistryInterface` → MCP bridge interfaces | **Yes** — the existing adapter bimaaji tools would flow through |
| `Waaseyaa\Mcp\Tools\McpTool` | Abstract base for tool groups; holds constructor DI | **Yes** — bimaaji could extend this pattern |
| `Waaseyaa\Mcp\Tools\TraversalTools` | Existing traversal tools in `packages/mcp/` | **Directly relevant** — already a graph-traversal tool group; bimaaji tools could live alongside or extend it |
| `Waaseyaa\Mcp\Tools\DiscoveryTools` | Discovery tools (entity/API discovery) | Overlaps with bimaaji's introspection section providers |
| `Waaseyaa\Mcp\McpEndpoint` | MCP JSON-RPC endpoint: `tools/list`, `tools/call` dispatch | Context only |
| `Waaseyaa\Mcp\McpServiceProvider` | Service provider wiring DI + routes | Registration hook location |

---

## Gap Assessment for Option 2

**The tool registration path exists but requires integration via `packages/ai-agent`.**

For bimaaji to register its graph operations as MCP tools, the following steps are needed:

1. **Register bimaaji tools in the framework-wide AI tool registry** (`Waaseyaa\AI\Tools\ToolRegistryInterface`). This registry lives in a Layer 5 package (`packages/ai-agent` or similar), and bimaaji is Layer 6 (Interfaces) — dependency direction is valid.
2. **OR** implement `ToolRegistryInterface` + `ToolExecutorInterface` directly in a `packages/bimaaji/src/Mcp/` subdirectory and wire it into `McpServiceProvider` — a self-contained approach that does not require touching `packages/ai-agent`.
3. Alternatively, add bimaaji operations as a new `McpTool` subclass in `packages/mcp/src/Tools/BimaajíTools.php` — keeping tool implementations centralized in `packages/mcp/`.

The `TraversalTools` class in `packages/mcp/src/Tools/` already suggests traversal/graph queries belong in the MCP package. Bimaaji's graph introspection operations could fit there or alongside it.

**No predecessor framework work is strictly required** — `ToolRegistryInterface` and `ToolExecutorInterface` are `@api` and designed for extension. The gap is an implementation gap (nobody has wired bimaaji yet), not an API gap.

**Framework PHP-first stance**: `packages/mcp/` is 100% PHP. There is no Node runtime, no JavaScript, no `package.json`. The CLAUDE.md and package structure confirm a PHP-only production stance. Adding bimaaji tools here would be consistent with that stance.

---

## Implications per option

- **Option 1**: The MCP host already has `DiscoveryTools` and `TraversalTools` that may partially overlap with bimaaji's graph introspection; Option 1 can rely on these without bimaaji-specific wiring.
- **Option 2**: Straightforward — `ToolRegistryInterface` and `ToolExecutorInterface` are `@api` and ready; bimaaji tools can be added as a new `McpTool` subclass or via the AI bridge with no new framework APIs required.
- **Option 3**: Node sidecar adds a parallel runtime to a PHP-only host; incompatible with the established architecture and unnecessary given the PHP tool registration path exists.
