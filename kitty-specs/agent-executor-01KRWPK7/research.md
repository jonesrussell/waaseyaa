# Phase 0 Research

This document records decisions made during planning for the six
outstanding-for-plan items called out in `spec.md`. No additional
research agents were required — the doctrine spec
(`docs/specs/agent-executor.md`) resolved the architectural questions
during the brainstorming pass, and every plan-time decision below
maps onto an existing project convention or has a strongly favoured
default.

---

## R-001 — Per-category dispositions for `occurrence_map.yaml`

**Decision:** Use the standard defaults from the bulk-edit template,
with three path exceptions (see `occurrence_map.yaml`).

**Rationale:**
- `code_symbols`, `import_paths`, `filesystem_paths`, `tests_fixtures`
  default to `rename` — all five renamed/moved symbols are internal
  PHP classes / paths with zero external consumers (verified by WP-01
  pre-deletion grep).
- `serialized_keys` defaults to `do_not_change` — the four new HTTP
  endpoints introduce *new* JSON keys; no existing API response key
  changes name.
- `cli_commands` defaults to `do_not_change` — the new `ai:*` commands
  are net-new; no prior command in the `ai:*` namespace exists.
- `user_facing_strings` defaults to `manual_review` — admin SPA
  strings are TBD this phase; PR reviewers flag any leakage.
- `logs_telemetry` defaults to `do_not_change` — new telemetry uses
  new event names; existing log/metric labels preserved.

**Alternatives considered:**
- More aggressive `rename` defaults across `serialized_keys` and
  `logs_telemetry`. **Rejected** because any API/telemetry rename
  would break external consumers (admin SPA history dashboards;
  Prometheus alerting). The bulk-edit gate's `do_not_change` posture
  is conservative-correct.
- `rename` default on `user_facing_strings`. **Rejected** because
  user-facing strings need translation review, not mechanical rename.
  `manual_review` is the right action.

---

## R-002 — `agent.run.approve` capability default

**Decision:** `agent.run.approve` equals `agent.run` (one capability).
Admins may split via permission config later if a separate
approval-only role becomes necessary.

**Rationale:**
- v1 personas are operators and admins; neither needs a split. A user
  with `agent.run` already has authority to start runs; granting them
  approval authority on their own runs is the principle of least
  surprise.
- Splitting introduces operational friction (more permission rows to
  manage) with no v1 consumer demanding it.
- Audit log records the approver's account_id distinctly from the
  initiator's — the split can be reintroduced cheaply later via a new
  capability + route option without database changes.

**Alternatives considered:**
- Separate `agent.run.approve` capability seeded with admin-only
  grants. **Deferred to v2** — useful when organisations want
  four-eyes approval; not v1.

---

## R-003 — Static price-table location

**Decision:** `packages/ai-observability/src/Pricing/ModelPriceTable.php`.
Class FQCN `Waaseyaa\AI\Observability\Pricing\ModelPriceTable`.

**Rationale:**
- The price table is consumed only by the observability listener
  computing cost from token usage. Lives with its consumer.
- Namespace pattern matches the package's existing `Waaseyaa\AI\Observability\*`.
- Static table for v1 (the spec defers live-pricing-API integration to
  v2); updates land via PRs alongside model additions.

**Schema sketch (referenced by data-model.md):**

```php
final class ModelPriceTable
{
    /** @var array<string, array{input_cents_per_mtok: int, output_cents_per_mtok: int}> */
    private const PRICES = [
        'anthropic:claude-opus-4-7' => ['input_cents_per_mtok' => 1500, 'output_cents_per_mtok' => 7500],
        'anthropic:claude-sonnet-4-6' => ['input_cents_per_mtok' => 300, 'output_cents_per_mtok' => 1500],
        // ...
    ];

    public function costCents(string $modelRef, int $tokensIn, int $tokensOut): ?int
    {
        // Returns null when model is unknown; observability records null cost rather than 0.
    }
}
```

**Alternatives considered:**
- Embed in `packages/ai-agent` near `AgentResult`. **Rejected** —
  observability owns the cost concept; the agent shouldn't know about
  pricing.
- Live API integration. **Deferred to v2** — out of scope per spec.

---

## R-004 — `transcript_json` column type

**Decision:** Doctrine DBAL `Doctrine\DBAL\Types\Types::TEXT` (the
default `TextType`), which Doctrine maps backend-specifically:
MEDIUMTEXT on MySQL (16 MB), TEXT on SQLite / PostgreSQL (unbounded).
Application-layer truncation at `config.ai.transcript_max_bytes`
(default 256 KB / 262144) is the deterministic cap; the column type
is the floor.

**Rationale:**
- MySQL's default TEXT column is 65,535 bytes — below the 256 KB cap.
  MEDIUMTEXT comfortably accommodates it.
- SQLite / PostgreSQL TEXT is unbounded; same Types::TEXT mapping
  works.
- Application-layer truncation guarantees the cap is independent of
  backend choice and provides a deterministic `[truncated]` marker.

**Alternatives considered:**
- JSON column type. **Rejected** — partial support across backends
  (PostgreSQL native; MySQL emulated; SQLite TEXT); the row contents
  are *internally* JSON but the column doesn't need backend-side
  validation.
- LONGTEXT (MySQL 4 GB). **Rejected** — overkill for a 256 KB cap.

---

## R-005 — WP-01 pre-deletion verification grep

**Decision:** WP-01 ships a new script
`bin/check-external-consumers ai-agent-orphans` (one-shot, mission-only;
deleted after mission completes) that greps `packages/`, `tests/`,
`docs/`, `kitty-specs/` for the to-be-deleted symbols and asserts
zero matches outside the mission's own scope.

**Targets:**

```
McpToolDefinition
Waaseyaa\\AI\\Schema\\Mcp\\McpToolDefinition
Waaseyaa\\AI\\Agent\\AgentInterface
AgentInterface  (with care to disambiguate)
packages/mcp/src/Tools/EntityTools
packages/mcp/src/Tools/DiscoveryTools
packages/mcp/src/Tools/TraversalTools
packages/mcp/src/Tools/EditorialTools
accessCheck(false)
```

Each match must be inside one of:
- A file scheduled for deletion in WP-01.
- This mission's planning docs (`kitty-specs/agent-executor-01KRWPK7/`).
- The doctrine spec (`docs/specs/agent-executor.md`).
- The archived predecessor mission (`kitty-specs/archive/ai-agent-end-to-end-01KRW91P/`).

Any other match blocks WP-01 review.

**Rationale:**
- The check is concrete and automatable.
- Aligns with existing `bin/check-package-layers` / `bin/check-dead-code`
  pattern.
- Deleted after mission completes — it's a one-shot, not a permanent
  gate.

**Alternatives considered:**
- Manual grep at review time. **Rejected** — too easy to miss a
  consumer; automation is cheap.
- Permanent retention as `bin/check-ai-agent-orphans`. **Rejected** —
  once the orphans are gone, the check has no permanent value.

---

## R-006 — `McpToolExecutor::accessCheck(false)` removal — security posture ADR

**Decision:** WP-01 includes
`docs/adr/0XX-mcp-tool-access-enforcement.md` documenting the
deliberate behavioural change: removing the `accessCheck(false)`
bypass means MCP tool calls (whether driven by external MCP clients
through `McpController` or by internal agent runs through
`AgentExecutor`) now enforce entity-level access against the tool
caller's authenticated account.

**Rationale:**
- The bypass was originally added for the deleted orphan
  `Waaseyaa\AI\Agent\McpServer`. Its presence in `McpToolExecutor`
  affects all callers including the live `McpController`.
- The change *strengthens* security: callers can no longer access
  entities they would otherwise lack permission to read or modify.
- Any consumer relying on the bypass was already broken by design
  (they were silently bypassing access policies).

**Action:**
- The ADR documents the change with rationale, deprecation history
  (#1508 deleted the orphan), and the explicit assertion that no
  legitimate consumer depends on the bypass.
- WP-01 acceptance includes verifying via grep that no `McpController`
  consumer test relies on bypass behaviour.

**Alternatives considered:**
- Keep the bypass and add an opt-in flag. **Rejected** — adds
  complexity for no clear benefit; the only consumer was the deleted
  orphan.
- Delete the bypass without an ADR. **Rejected** — the behavioural
  change in `McpController`'s tool dispatch warrants explicit
  documentation. Future readers (and external integrators) should be
  able to find this decision.

---

## Items NOT requiring research

The following items were resolved during the brainstorming pass and
locked in the doctrine spec; they are not re-opened here:

- Worker-native hybrid consumer shape (vs CLI-only or HTTP-only).
- Bundle paradigm (vs registered-class-only).
- Identity model (vs separate service account or per-definition choice).
- Tool model (attribute-driven, shared package vs in-mcp duplicate).
- Remote MCP server consumption (vs local-only tools).
- HITL granularity (vs static-only or interactive-only).
- Provider config (hybrid env + entity vs env-only or entity-only).
- Retention policy (30-day TTL vs forever or 7-day).
- Observability scope (tokens + cost + tool-counts + latency vs minimum or
  full suite).

If any of these resurface during implementation, file an ADR rather
than re-running the brainstorming pass.
