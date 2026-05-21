---
affected_files: []
cycle_number: 2
mission_slug: agent-executor-v1-1-audit-followups-01KS3S5M
reproduction_command:
reviewed_at: '2026-05-21T01:05:08Z'
reviewer_agent: unknown
verdict: rejected
wp_id: WP03
---

# WP03 Review — Cycle 1 — REJECT

**Reviewer:** claude:opus-4-7:reviewer:reviewer
**Commit reviewed:** b129223e6
**Verdict:** REJECT — runtime DI regression

## Summary

The deletion of `BroadcastStorageAdapter`, the test additions, and the doc cleanup
are all clean. However, the binding rewire is broken: `AgentRunBroadcasterInterface`
no longer has a production binding at all because the new
`AgentRunBroadcasterServiceProvider` is not declared in
`packages/ai-agent/composer.json`'s `extra.waaseyaa.providers`.

## Evidence

1. `git show b129223e6 -- packages/ai-agent/src/MessagingServiceProvider.php` removed:
   ```php
   $this->singleton(
       AgentRunBroadcasterInterface::class,
       fn(): AgentRunBroadcasterInterface => new BroadcastStorageAdapter(...),
   );
   ```
   replacing it with a comment claiming the binding is now in
   `AgentRunBroadcasterServiceProvider`.

2. `packages/ai-agent/composer.json` `extra.waaseyaa.providers` currently lists only:
   - `Waaseyaa\AI\Agent\AiAgentEntityServiceProvider`
   - `Waaseyaa\AI\Agent\AiAgentServiceProvider`
   - `Waaseyaa\AI\Agent\MessagingServiceProvider`

   `AgentRunBroadcasterServiceProvider` is missing. `git log --all` confirms it has
   *never* been registered — pre-existing gap that was previously masked by the
   `MessagingServiceProvider` adapter binding. Removing that binding without
   registering the replacement turns the gap into a hard runtime failure.

3. Consumers that will break: `RunAgentHandler::__construct` and
   `StalledRunReaper::__construct` both `resolve(AgentRunBroadcasterInterface::class)`
   from MessagingServiceProvider; with no binding at all the kernel will throw on
   first run-agent dispatch.

4. The PHPDoc in both `MessagingServiceProvider` and
   `AgentRunBroadcasterServiceProvider` asserts the latter is "registered after
   MessagingServiceProvider in composer.json so its singleton wins" — that
   statement is currently false.

## Required fix

Add `Waaseyaa\AI\Agent\Broadcast\AgentRunBroadcasterServiceProvider` to
`extra.waaseyaa.providers` in `packages/ai-agent/composer.json`, ordered after
`MessagingServiceProvider` (so the documented "later wins" intent holds).
Then re-run `composer dump-autoload` and `bin/waaseyaa optimize:manifest` (or
the equivalent CI manifest rebuild) to confirm the provider is picked up.

A small integration-style assertion that a booted kernel resolves
`AgentRunBroadcasterInterface` to a non-null `AgentRunBroadcaster` would prevent
this regression from recurring.

## What was good

- `BroadcastStorageAdapter.php` cleanly deleted; no residual references anywhere.
- `AgentRunBroadcaster`, its interface, and its provider have no WP-04/WP-05
  provenance comments left over.
- `AgentRunBroadcasterTest` is solid: real SQLite via `DBALDatabase::createSqlite(':memory:')`,
  asserts channel `agent.run.abc`, payload merge order, and a real
  `DROP TABLE _broadcast_log` to force a push failure that must be swallowed and
  logged (2 tests / 8 assertions, both pass).
- Diff scope is tight (1 deletion, 4 edits, 1 new test file).
- T023 OpenAPI shape: WP02 already corrected; no further drift.

## Out of scope / observations

- The new provider's `register()` constructs `new BroadcastStorage(...)` inline
  rather than resolving it from the container. Acceptable for now (no other
  consumer needs to substitute it) but flag for a future cleanup.
