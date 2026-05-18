# Broadcasting End-to-End

**Mission:** `broadcasting-end-to-end-01KRW8YB`
**Status:** Spec
**Target branch:** `main`
**Closes:** #1497

## Why this mission exists

`packages/foundation/src/Broadcasting/` ships `BroadcasterInterface`, `BroadcastMessage`, and an in-memory `SseBroadcaster`. `packages/api/src/Controller/BroadcastController.php` and `packages/foundation/src/Http/Router/BroadcastRouter.php` exist. None of it is wired to a real route or a real event source. The whole subsystem is a coherent scaffold that emits nothing.

5 baseline entries for `SseBroadcaster` document this gap. Owner directive: complete the wiring so a downstream consumer can `curl /broadcast/events` and receive a live stream of domain events. No deletion; finish the architecture.

## User scenarios

### Primary flow: consumer subscribes to an event stream

1. A consumer (admin SPA, ops dashboard, any HTTP client) opens `GET /broadcast/{channel}` with `Accept: text/event-stream`.
2. The server responds with `200 OK`, `Content-Type: text/event-stream`, keeps the connection open.
3. As domain events fire (entity saved, config changed, job completed), the server emits SSE-formatted messages on the appropriate channel.
4. Client receives messages as standard `EventSource` events with `id`, `event`, and `data` lines.
5. When the client closes the connection (or the server times out the subscription), state is cleaned up — the subscriber list shrinks by one.

### Event source flow: server-side, an entity-save triggers a broadcast

1. Code somewhere in the system calls `EntityRepository::save($entity)` (existing behavior).
2. `EntitySaved` event dispatches (existing).
3. An event listener (`BroadcastEntitySavedListener`, new) calls `BroadcasterInterface::broadcast(BroadcastMessage::for('entities:saved', payload))`.
4. All currently-subscribed clients on the `entities` channel receive the SSE message.

### Edge cases

- Two subscribers on the same channel: both receive each message.
- Subscriber on a different channel: no message.
- No subscribers at all: `broadcast()` is a no-op (no error).
- Broadcast called from inside a CLI command (no HTTP context): logged + dropped, not raised. SSE only fires in HTTP context.
- Empty channel name: 400 Bad Request.
- Authentication: GET /broadcast/{channel} requires an authenticated session (existing auth middleware).

## Requirements

### Functional

| ID | Status | Requirement |
|---|---|---|
| FR-001 | Mandatory | `BroadcasterInterface::broadcast(BroadcastMessage)` is callable from any L0+ code path. |
| FR-002 | Mandatory | `GET /broadcast/{channel}` returns `200 OK` with `Content-Type: text/event-stream` and streams messages emitted on that channel after subscription. |
| FR-003 | Mandatory | Each broadcast message MUST reach every active subscriber on the matching channel. |
| FR-004 | Mandatory | Broadcasts to a channel with zero subscribers MUST be a no-op (no error, no exception). |
| FR-005 | Mandatory | At least one production event source MUST call `BroadcasterInterface::broadcast()` after this mission lands. Reference implementation: an `EntitySaved` listener that broadcasts to the `entities` channel. |
| FR-006 | Mandatory | The `/broadcast/{channel}` route MUST be registered via `BroadcastRouter` (existing) wired into the kernel's route chain. |
| FR-007 | Mandatory | `SseBroadcaster::subscriberCount(channel)` returns the live subscriber count for that channel at the time of the call. |
| FR-008 | Mandatory | Closing the HTTP connection MUST remove the subscriber from the broadcaster's internal state. |
| FR-009 | Mandatory | `BroadcastController` MUST require an authenticated session (`_account` non-anonymous) per `AccessChecker` rules. |

### Non-functional

| ID | Status | Threshold |
|---|---|---|
| NFR-001 | Mandatory | A broadcast emitted while N subscribers are active is delivered to all N within 100 ms p95 (in-process, no network). |
| NFR-002 | Mandatory | The SSE response sets `Cache-Control: no-cache`, `Connection: keep-alive`, and `X-Accel-Buffering: no` to ensure proxies don't buffer the stream. |
| NFR-003 | Mandatory | Heartbeat comments (`: ping\n\n`) are emitted every 30 seconds to keep connections through reverse-proxy timeouts. |
| NFR-004 | Mandatory | Connection idle without messages does NOT block other PHP-FPM workers (use periodic flush + small sleep, OR detect dropped connection via `connection_aborted()`). |

### Constraints

| ID | Status | Constraint |
|---|---|---|
| C-001 | Mandatory | The Broadcasting subsystem cannot import from any layer higher than L0 (foundation owns it). |
| C-002 | Mandatory | The `BroadcastController` lives at L4 (api package), `BroadcastEntitySavedListener` lives at L2 or higher (it consumes entity events). No downward imports. |
| C-003 | Mandatory | All currently-baselined `SseBroadcaster` methods (`broadcast`, `clearLog`, `clearSubscribers`, `getMessageLog`, `getSubscribedChannels`, `hasSubscribers`, `subscriberCount`) must have at least one production caller after this mission lands. |
| C-004 | Mandatory | The dead-code baseline must drop by exactly the five `SseBroadcaster` entries currently in `phpstan-dead-code-baseline.neon`. No new entries may appear. |

## Success criteria

| ID | Metric | How verified |
|---|---|---|
| SC-001 | An integration test subscribes to `/broadcast/entities`, saves an entity in the same test, and asserts the SSE response contains the broadcast message. | `tests/Integration/Phase??/BroadcastingE2ETest.php` passes. |
| SC-002 | A unit test asserts `SseBroadcaster::broadcast()` to a channel with two subscribers emits to both. | `SseBroadcasterTest::testBroadcastToMultipleSubscribers` passes. |
| SC-003 | `composer check-dead-code` reports zero `SseBroadcaster` baseline entries after merge. | `grep -c 'SseBroadcaster' phpstan-dead-code-baseline.neon` returns 0. |
| SC-004 | `composer verify` is green on the merge commit. | CI status check `verify` passes. |
| SC-005 | The broadcasting contract is documented at the framework spec level. | `docs/specs/infrastructure.md` has a Broadcasting section OR new `docs/specs/broadcasting.md` exists with the full contract. |
| SC-006 | Issue #1497 closes via the `Closes #1497` footer in the merge commit. | GitHub auto-closes on merge. |

## Key entities

| Entity | Role | Net change |
|---|---|---|
| `BroadcasterInterface` | Existing. No change. | — |
| `BroadcastMessage` | Existing value object. No change. | — |
| `SseBroadcaster` | Existing. Gains real streaming behavior + improved cleanup. | edit |
| `BroadcastController` | Existing. Gains real implementation (was stub). | edit |
| `BroadcastRouter` | Existing. Registered into the route chain. | edit |
| `BroadcastEntitySavedListener` (new) | Reference event-source consumer. Listens to `EntitySaved`, broadcasts on `entities` channel. | +1 file |
| Service provider edit | Wires the listener + binds broadcaster as singleton. | edit |

## Assumptions

- The framework's HTTP entry point (`public/index.php` + `HttpKernel`) can sustain a long-lived response. If not, this mission documents the gap and PRs a basic version anyway.
- The existing `EventDispatcherInterface` pattern carries `EntitySaved` events (verified — `packages/entity/src/Event/EntitySaved.php` exists).
- An in-process `SseBroadcaster` is acceptable for v1.0; multi-process pub/sub (Redis, NATS) is a follow-up.
- Heartbeat interval of 30s is conventional; tunable via config if needed.

## Out of scope

- Cross-process / cross-server broadcasting (in-process only for v1.0).
- WebSocket transport (SSE only).
- Authorization beyond "is authenticated" (no per-channel ACLs in this mission).
- Replay of missed messages (no message persistence).
- Backpressure or message ordering guarantees beyond best-effort.

## WP outline (for /spec-kitty.plan)

- **WP01 — SseBroadcaster:** Replace stub methods with real subscriber registry + emit loop. Unit tests for subscribe/broadcast/unsubscribe.
- **WP02 — BroadcastController:** Implement real SSE streaming response (header set, write loop, heartbeat, connection-aborted detection). Unit test with mock broadcaster.
- **WP03 — Routing:** Wire `BroadcastRouter` into kernel's `RouteProviderInterface` chain. Confirm `GET /broadcast/{channel}` reaches the controller.
- **WP04 — Event source:** New `BroadcastEntitySavedListener`. Subscribe to `EntitySaved`, broadcast on `entities` channel. DI binding in the right service provider.
- **WP05 — Integration test:** E2E test: subscribe, save entity, assert SSE message received.
- **WP06 — Wrap-up:** Spec updates, baseline regen confirming 5 SseBroadcaster entries dropped, CHANGELOG entry, full `composer verify`.

## References

- Issue: https://github.com/waaseyaa/framework/issues/1497
- Existing scaffolding: `packages/foundation/src/Broadcasting/SseBroadcaster.php`
- Existing controller stub: `packages/api/src/Controller/BroadcastController.php`
- Existing router: `packages/foundation/src/Http/Router/BroadcastRouter.php`
- Dead-code audit: `docs/audits/2026-05-17-dead-code-baseline-audit.md`
