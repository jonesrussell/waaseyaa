import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { readFile } from "node:fs/promises";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const PACKAGE_ROOT = join(__dirname, "..");
const README_PATH = join(PACKAGE_ROOT, "README.md");

const server = new McpServer({
  name: "bimaaji",
  version: "1.0.0",
});

server.registerTool(
  "bimaaji_ping",
  {
    description:
      "Health check for the Bimaaji MCP server. Use this to verify the dev toolchain (Node + installed deps under vendor/waaseyaa/bimaaji/mcp) is wired correctly from Minoo.",
  },
  async () => ({
    content: [
      {
        type: "text",
        text: "bimaaji MCP ok. Run `composer bimaaji-mcp-install` in Minoo if tools fail to load.",
      },
    ],
  }),
);

server.registerTool(
  "bimaaji_about",
  {
    description:
      "Package orientation: Bimaaji roadmap and scope from waaseyaa/bimaaji README (graph introspection, agent-safe mutation).",
  },
  async () => {
    let text;
    try {
      text = await readFile(README_PATH, "utf-8");
    } catch {
      text = "README.md not found next to mcp/ (unexpected layout).";
    }
    return { content: [{ type: "text", text }] };
  },
);

const transport = new StdioServerTransport();
await server.connect(transport);
