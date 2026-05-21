---
work_package_id: WP04
title: AiRunCommand --watch SSE Consumer
dependencies:
- WP01
- WP03
requirement_refs:
- FR-008
- FR-009
- FR-013
- NFR-003
- C-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
subtasks:
- T024
- T025
- T026
- T027
agent: "claude:sonnet:implementer:implementer"
shell_pid: "805213"
history:
- date: '2026-05-20T23:57:13Z'
  event: created
authoritative_surface: packages/cli/src/Command/Ai/
execution_mode: code_change
owned_files:
- packages/cli/src/Command/Ai/AiRunCommand.php
- packages/cli/tests/Unit/Command/Ai/AiRunCommandWatchTest.php
- packages/cli/composer.json
tags: []
---

# WP04 — AiRunCommand --watch SSE Consumer

**Closes**: #1513
**Depends on**: WP03 (broadcaster must be consolidated; smoke test benefits from full-stack wiring)
**Implement command**: `spec-kitty agent action implement WP04 --agent <name>`

## Objective

Replace the stub `--watch` implementation in `AiRunCommand::runAsync` (which currently prints a single message and exits) with a real SSE consumer using `packages/http-client/`'s `StreamHttpClient`. The consumer connects to `/broadcast?channels=agent.run.<id>`, prints each event's name + payload to stdout as the agent run progresses, and exits cleanly when the `terminated` event arrives. SIGINT (Ctrl-C) tears down the HTTP stream without killing the server-side run.

## Context

Current stub at `packages/cli/src/Command/Ai/AiRunCommand.php` line 157–162:
```php
if ($watch) {
    $io->writeln(sprintf(
        'watch: SSE consumer would attach to /broadcast?channels=agent.run.%s (--watch is informational here).',
        $runId
    ));
}
```
This is the entire `--watch` implementation. FR-008 requires it to actually consume events.

The SSE endpoint is `/broadcast?channels=agent.run.<id>` per the framework's `BroadcastRouter`. The `StreamHttpClient` in `packages/http-client/src/StreamHttpClient.php` provides chunk-by-chunk streaming.

Layer discipline (C-003): the CLI consumer is in L6 (`packages/cli/`) and connects via HTTP to the broadcast endpoint — no direct import of L5 broadcaster classes.

## Subtasks

### T024 — Implement real SSE consumer in `AiRunCommand::runAsync`

**File**: `packages/cli/src/Command/Ai/AiRunCommand.php`

**Purpose**: Replace the stub with a blocking SSE consumer loop that reads `data:` lines from the broadcast endpoint and prints event names + payloads until the run terminates.

**Steps**:
1. Open `packages/cli/src/Command/Ai/AiRunCommand.php`.
2. Verify `packages/http-client/` is in `packages/cli/composer.json` `require` (add if missing: `"waaseyaa/http-client": "^<current-tag>"`).
3. Inject `StreamHttpClient` (or `HttpClientInterface` if streaming is abstracted) into `AiRunCommand`'s constructor. Check current constructor to determine if http-client is already injected.
4. Replace the stub block with a streaming consumer:

   ```php
   if ($watch) {
       $url = sprintf('%s/broadcast?channels=agent.run.%s', $this->getBaseUrl(), $runId);
       $io->writeln(sprintf('<info>Watching agent run %s…</info>', $runId));
       $this->consumeSseStream($url, $io);
   }
   ```

5. Add private method `consumeSseStream(string $url, CliIO $io): void`:
   ```php
   private function consumeSseStream(string $url, CliIO $io): void
   {
       $stream = $this->httpClient->streamGet($url, [
           'Accept' => 'text/event-stream',
           'Cache-Control' => 'no-cache',
       ]);
   
       $eventName = '';
       foreach ($stream->chunks() as $chunk) {
           foreach (explode("\n", $chunk) as $line) {
               $line = rtrim($line, "\r");
               if (str_starts_with($line, 'event:')) {
                   $eventName = trim(substr($line, 6));
               } elseif (str_starts_with($line, 'data:')) {
                   $data = trim(substr($line, 5));
                   $io->writeln(sprintf('[%s] %s', $eventName ?: 'message', $data));
               } elseif ($line === '') {
                   // End of SSE message block; reset event name
                   $eventName = '';
               }
           }
           if ($this->isTerminatedEvent($eventName)) {
               break;
           }
       }
   }
   
   private function isTerminatedEvent(string $eventName): bool
   {
       return in_array($eventName, ['agent.run.terminated', 'terminated'], true);
   }
   ```

6. `getBaseUrl()`: Determine how the CLI resolves the base URL. Check existing command code for any `$this->baseUrl` or app config. If not already present, inject `string $baseUrl` into the constructor (or read from `$_ENV['WAASEYAA_BASE_URL']`).

7. Verify `StreamHttpClient::streamGet()` method signature — adapt the call to match the actual API in `packages/http-client/src/StreamHttpClient.php`.

**Edge cases**:
- If the stream ends without a `terminated` event (server closed), the loop exits cleanly.
- If the HTTP request fails (connection refused), catch `HttpRequestException` and print an error message, exit 1.

**Validation**:
- [ ] `--watch` flag causes the command to connect and print events
- [ ] Loop exits when `terminated` event received
- [ ] Graceful fallback if stream ends prematurely

---

### T025 — Wire SIGINT / Ctrl-C teardown

**File**: `packages/cli/src/Command/Ai/AiRunCommand.php`

**Purpose**: When the operator presses Ctrl-C, the SSE consumer disconnects the HTTP stream cleanly and the command exits 0. The server-side agent run continues.

**Steps**:
1. Check if `ext-pcntl` is available in the CLI environment. Add to `packages/cli/composer.json`:
   ```json
   "suggest": {
       "ext-pcntl": "Required for clean SIGINT handling in --watch mode"
   }
   ```
   Do NOT add to hard `require` (may not be available in all environments).

2. Register a SIGINT handler before starting the SSE consumer loop:
   ```php
   private bool $interrupted = false;
   
   private function registerSigintHandler(): void
   {
       if (!function_exists('pcntl_signal')) {
           return; // Graceful degradation if pcntl not available
       }
       pcntl_signal(SIGINT, function () {
           $this->interrupted = true;
       });
   }
   ```

3. In `consumeSseStream`, check the interrupt flag and call `pcntl_signal_dispatch()` inside the chunk loop:
   ```php
   foreach ($stream->chunks() as $chunk) {
       if (function_exists('pcntl_signal_dispatch')) {
           pcntl_signal_dispatch(); // Process pending signals
       }
       if ($this->interrupted) {
           $io->writeln('<comment>Interrupted — agent run continues server-side.</comment>');
           $stream->close(); // Close the HTTP stream
           return;
       }
       // ... process chunk ...
   }
   ```

4. Call `$this->registerSigintHandler()` before `$this->consumeSseStream(...)` in `runAsync`.

5. Ensure `$stream->close()` (or equivalent in `StreamHttpClient`) closes the HTTP connection cleanly. Check `StreamHttpClient` for a `close()` or `abort()` method.

**Validation**:
- [ ] SIGINT handler registered before stream starts
- [ ] `$this->interrupted = true` set on SIGINT
- [ ] Stream closed and command exits 0 on interrupt
- [ ] Server-side run is NOT terminated (the close is client-side only)
- [ ] Graceful degradation if `pcntl` not available

---

### T026 — Write `AiRunCommandWatchTest` (FR-013)

**File**: `packages/cli/tests/Unit/Command/Ai/AiRunCommandWatchTest.php`

**Purpose**: Unit regression for the `--watch` path. Uses a mock HTTP client to simulate SSE streams.

**Steps**:
1. Create `packages/cli/tests/Unit/Command/Ai/AiRunCommandWatchTest.php`.
2. Use `#[Test]`, `#[CoversClass(AiRunCommand::class)]` attributes.

   **Test 1 — `watchConnectsAndPrintsEvents`**:
   - Create a mock `HttpClientInterface` (or `StreamHttpClient`) that returns a pre-scripted stream:
     ```
     event: agent.run.started
     data: {"run_id":"abc-123"}
     
     event: agent.run.terminated
     data: {"run_id":"abc-123","outcome":"success"}
     
     ```
   - Run `AiRunCommand` with `--watch` flag via Symfony's `CommandTester`.
   - Assert output contains `agent.run.started` and `agent.run.terminated` lines.
   - Assert exit code 0.

   **Test 2 — `watchExitsOnTerminatedEvent`**:
   - Stream contains multiple events followed by `agent.run.terminated`.
   - Assert the command exits after the `terminated` event (does not block).
   - Assert exit code 0.

   **Test 3 — `watchHandlesStreamConnectionFailure`**:
   - Mock HTTP client throws `HttpRequestException` on `streamGet`.
   - Assert command exits with non-zero code and prints an error.

   **Test 4 — `watchWithoutFlagRunsNormally`** (regression):
   - Run without `--watch` flag.
   - Assert no SSE connection is attempted (mock `streamGet` is never called).

3. For SIGINT test: the signal test is noted as "where testable" per FR-013. If `pcntl` is available in the test environment, add a test that simulates the interrupt flag being set. If not, skip with `#[RequiresPhpExtension('pcntl')]`.

**Validation**:
- [ ] Four test methods (plus optional SIGINT test)
- [ ] All passing via `./vendor/bin/phpunit packages/cli/tests/Unit/Command/Ai/AiRunCommandWatchTest.php`
- [ ] No live network calls

---

### T027 — Record smoke-test result in WP notes

**File**: This WP prompt file (`tasks/WP04-airuncommand-watch-sse-consumer.md`)

**Purpose**: SC-001 requires operator verification that `--watch` prints live events and terminates cleanly. The implementer runs the smoke test and appends the result here.

**Steps**:
1. Start the dev server: `composer dev` (or `bin/waaseyaa serve`).
2. In a separate terminal, run: `bin/waaseyaa ai:run "summarize: hello world" --watch`.
3. Observe the output — should see `agent.run.started`, one or more iteration events, and `agent.run.terminated`.
4. Press Ctrl-C mid-stream — confirm the command exits and the server run continues.
5. Append results to this file under a `## Smoke Test Results` heading:

```markdown
## Smoke Test Results

**Date**: YYYY-MM-DD
**Operator**: <name>
**Command**: `bin/waaseyaa ai:run "summarize: hello world" --watch`
**Result**: PASS/FAIL
**Events observed**: <list of event names seen>
**SIGINT test**: PASS/FAIL — command exited on Ctrl-C, run continued server-side
```

**Validation**:
- [ ] Smoke test section appended to this file
- [ ] Result is PASS

---

## Branch Strategy

**Planning/base branch**: `main`
**Merge target**: `main`
**Execution**: Worktree allocated per `lanes.json`. Scheduled after WP03 to allow smoke-testing against a clean broadcaster.

## Definition of Done

- [ ] `AiRunCommand::runAsync` with `--watch` connects to `/broadcast?channels=agent.run.<id>`
- [ ] Events printed to stdout as they arrive: `[event.name] {payload}`
- [ ] Command exits 0 when `terminated` event received
- [ ] SIGINT tears down the HTTP stream; command exits 0; server run continues
- [ ] `AiRunCommandWatchTest` passes (4 methods)
- [ ] Smoke test recorded (SC-001)
- [ ] `ext-pcntl` added to `suggest` in `packages/cli/composer.json`
- [ ] PHPStan clean, CS-Fixer clean
- [ ] Layer discipline: no direct L5 imports in L6 CLI code

## Risks

| Risk | Mitigation |
|---|---|
| `StreamHttpClient` API doesn't support chunk iteration | Read `packages/http-client/src/StreamHttpClient.php` before implementing; adapt to actual API |
| `pcntl_signal_dispatch()` not called frequently enough | Call inside chunk loop iteration |
| Base URL not available in CLI context | Check existing command config; inject via constructor or env var |
| Test mock stream format differs from real SSE | Test real SSE parsing logic with exact `event:\ndata:\n\n` line endings |

## Reviewer Guidance

1. Verify the SSE parser handles the `event:` / `data:` / blank-line protocol correctly.
2. Check that SIGINT does NOT send a termination signal to the server-side agent run.
3. Confirm `--watch` without pcntl doesn't crash (graceful degradation).
4. Review `AiRunCommandWatchTest` — mock stream must simulate real SSE wire format.
5. SC-001 smoke test result should be in the WP file.

## Activity Log

- 2026-05-21T01:11:58Z – claude:sonnet:implementer:implementer – shell_pid=805213 – Started implementation via action command
- 2026-05-21T01:21:13Z – claude:sonnet:implementer:implementer – shell_pid=805213 – SSE consumer via SseLineStreamInterface+PhpStreamSseClient in packages/http-client; SIGINT teardown; informational stub removed; 4/4 unit tests pass; PHPStan+CS clean
