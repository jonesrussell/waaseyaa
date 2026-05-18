# Quickstart: Running Your First Agent

This walks an operator through configuration and a first run after the
Agent Executor v1 mission lands. Assumes a working Waaseyaa
installation with `composer install` complete.

## Prerequisites

- Waaseyaa framework installed at the alpha that ships this mission.
- Environment variables set:
  - `WAASEYAA_DB` — path to your SQLite database (or DSN for MySQL / Postgres).
  - `ANTHROPIC_API_KEY` — for the Anthropic provider. (Skip if you'll use `NullLlmProvider` for smoke testing.)
- A Messenger transport configured in `config.queue.transports`.
- A user account with the `agent.run` capability and at least one `tool.<name>` capability (e.g. `tool.entity.read`).

## 1. Configure providers

Edit (or seed) the `config.ai.providers` config entity:

```bash
bin/waaseyaa config:edit config.ai.providers
```

Add at least one entry:

```yaml
- id: anthropic
  type: anthropic
  model_default: anthropic:claude-sonnet-4-6
  timeout_ms: 60000
  rate_limit_per_min: 60
  api_key_env_var: ANTHROPIC_API_KEY
```

Or, for offline smoke testing, configure the null provider:

```yaml
- id: null
  type: null
  model_default: null:echo
  timeout_ms: 5000
  rate_limit_per_min: 9999
  api_key_env_var: NONE
```

Apply the config:

```bash
bin/waaseyaa config:sync
```

## 2. Configure remote MCP servers (optional)

If you want to expose remote MCP tools to your agents, edit
`config.ai.mcp_servers`:

```yaml
- alias: github
  url: https://mcp.example.com/github
  auth_header_env_var: GITHUB_MCP_TOKEN
  enabled: true
  capability_prefix: tool.mcp.github
```

Apply, then grant the resulting capabilities (`tool.mcp.github.<tool>`)
to your users / roles.

## 3. Run your first inline agent (CLI)

For development and operator scripting, the inline mode bypasses
Messenger and runs in-process:

```bash
bin/waaseyaa ai:run "List the 5 most recently published nodes" \
  --inline \
  --agent=node_lister
```

If `node_lister` isn't registered, you can pass an inline bundle:

```bash
bin/waaseyaa ai:run "List the 5 most recently published nodes" \
  --inline \
  --tools=entity.list,entity.search \
  --model=null:echo
```

Inline runs stream `StreamChunk`s to stdout as the provider produces
them, and print a final summary on completion.

## 4. Run an agent through HTTP (async)

```bash
curl -X POST http://localhost:8080/api/ai/agent/run \
  -H 'Content-Type: application/json' \
  -b WAASEYAA_SESSION=... \
  -d '{
    "agent_id": "node_lister",
    "params": {},
    "destructive_approval": "none"
  }'
```

Response (HTTP 202):

```json
{
  "run_id": "01J6XW9KQT7M0YB3N4R5CQZ2EX",
  "stream_url": "/broadcast?channels=agent.run.01J6XW9KQT7M0YB3N4R5CQZ2EX",
  "status_url": "/api/ai/agent/run/01J6XW9KQT7M0YB3N4R5CQZ2EX",
  "approve_url": "/api/ai/agent/run/01J6XW9KQT7M0YB3N4R5CQZ2EX/approve"
}
```

Tail the SSE stream:

```bash
curl -N -H 'Accept: text/event-stream' \
  'http://localhost:8080/broadcast?channels=agent.run.01J6XW9KQT7M0YB3N4R5CQZ2EX'
```

You'll see events like:

```
event: run_started
data: {"run_id":"...","agent_id":"node_lister","started_at":"2026-05-18T05:00:00Z"}

event: iteration
data: {"run_id":"...","iteration":1,"tokens_used_so_far":42}

event: tool_call_started
data: {"run_id":"...","call_id":"call_01","tool_name":"entity.list","arguments_redacted":{"type":"node","limit":5}}

event: tool_call_completed
data: {"run_id":"...","call_id":"call_01","success":true,"duration_ms":34}

event: run_completed
data: {"run_id":"...","response":"...","token_usage":{"in":120,"out":340},"cost_cents":12}
```

## 5. Run a destructive agent with interactive approval

```bash
curl -X POST http://localhost:8080/api/ai/agent/run \
  -H 'Content-Type: application/json' \
  -b WAASEYAA_SESSION=... \
  -d '{
    "bundle": {
      "prompt": "Delete all draft nodes older than 30 days.",
      "tools": ["entity.list", "entity.delete"],
      "model": "anthropic:claude-sonnet-4-6"
    },
    "destructive_approval": "interactive"
  }'
```

When the agent reaches `entity.delete`, the SSE stream emits:

```
event: approval_required
data: {
  "run_id": "...",
  "call_id": "call_03",
  "tool_name": "entity.delete",
  "arguments": {"type":"node","id":"01J..."},
  "expires_at": "2026-05-18T05:05:00Z"
}
```

The run's status flips to `awaiting_approval`. Approve or deny:

```bash
curl -X POST 'http://localhost:8080/api/ai/agent/run/01J6XW9KQT7M0YB3N4R5CQZ2EX/approve' \
  -H 'Content-Type: application/json' \
  -b WAASEYAA_SESSION=... \
  -d '{"call_id":"call_03","decision":"approve"}'
```

If no decision arrives within `config.ai.hitl_timeout_seconds`
(default 5 minutes), the run fails with `error_code: approval_timeout`.

## 6. Cancel a run

```bash
curl -X DELETE 'http://localhost:8080/api/ai/agent/run/01J6XW9KQT7M0YB3N4R5CQZ2EX' \
  -b WAASEYAA_SESSION=...
```

Returns 204. The worker observes `status='cancelling'` between
iterations and transitions to `cancelled` within ~3 iteration
boundaries. SSE emits `run_cancelled`.

## 7. Run the worker

In production, run a Messenger consumer process under systemd or k8s:

```bash
bin/waaseyaa messenger:consume default --limit=1000 --time-limit=3600
```

Multiple workers may consume the same queue safely — Messenger
transport locking + a `started_at IS NULL` guard at handler entry
prevents double-execution.

## 8. Schedule the maintenance jobs

Enable the scheduler:

```bash
# Verify scheduler is enabled
bin/waaseyaa scheduler:list

# Should include:
#   - ai:purge-runs              daily @ 03:00 UTC
#   - ai:reap-stalled-runs       every 5 minutes
```

Run the scheduler:

```bash
bin/waaseyaa scheduler:run --daemon
```

These commands can also be invoked ad-hoc:

```bash
bin/waaseyaa ai:purge-runs --dry-run         # See what would be deleted
bin/waaseyaa ai:purge-runs --retention-days=14  # Tighter retention
bin/waaseyaa ai:reap-stalled-runs            # One-shot reaper sweep
```

## 9. Inspect telemetry

After a few runs, the `ai-observability` listeners will have populated
telemetry. Inspect via telescope:

```bash
bin/waaseyaa telescope:inspect agent.run.* --since='1 hour ago'
```

Per-run metrics include `token_usage_in`, `token_usage_out`,
`cost_cents`, `tool_call_count`, and `wall_clock_ms`.

## 10. Register a new agent (extension authors)

To ship a new `AgentDefinition` from a package:

```php
<?php

declare(strict_types=1);

namespace MyOrg\MyExtension;

use Waaseyaa\AI\Agent\Attribute\AsAgentDefinition;
use Waaseyaa\AI\Agent\HitlMode;

#[AsAgentDefinition(
    id: 'my_org.translate',
    label: 'Translate node',
    description: 'Translates the body field of a node into a target language.',
    prompt: 'Translate the body of node {node_id} into {language}.',
    system: 'You are a careful translator. Preserve formatting.',
    tools: ['entity.read', 'entity.update'],
    model: 'anthropic:claude-sonnet-4-6',
    maxIterations: 6,
    destructiveDefault: HitlMode::Interactive,
    requiresCapability: 'agent.translate',
)]
final class TranslateNodeAgent
{
    // Empty marker class; metadata lives on the attribute.
    // (Or implement custom procedural logic via AgentRunService::runInline()
    //  from a wrapping service if you need PHP outside the LLM loop.)
}
```

`PackageManifestCompiler` discovers the attribute at boot; the agent
is immediately runnable by ID via CLI / HTTP.

## 11. Register a new tool (extension authors)

```php
<?php

declare(strict_types=1);

namespace MyOrg\MyExtension\Tools;

use Waaseyaa\AI\Agent\AgentContext;
use Waaseyaa\AI\Tools\AbstractAgentTool;
use Waaseyaa\AI\Tools\AgentToolResult;
use Waaseyaa\AI\Tools\Attribute\AsAgentTool;

#[AsAgentTool(
    name: 'my_org.translate_text',
    capability: 'tool.my_org.translate_text',
    destructive: false,
    dryRunSupported: false,
    category: 'translation',
)]
final class TranslateTextTool extends AbstractAgentTool
{
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['text', 'language'],
            'properties' => [
                'text' => ['type' => 'string'],
                'language' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): AgentToolResult
    {
        // ... call a translation service, return AgentToolResult ...
    }
}
```

Discovered automatically. The new capability `tool.my_org.translate_text`
must be granted to relevant roles before users can invoke it.
