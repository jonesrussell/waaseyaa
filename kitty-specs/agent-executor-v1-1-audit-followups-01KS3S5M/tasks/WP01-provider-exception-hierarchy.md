---
work_package_id: WP01
title: Provider Exception Hierarchy + Retry Semantics
dependencies: []
requirement_refs:
- FR-001
- FR-002
- FR-003
- FR-010
- FR-025
- NFR-001
- C-001
- C-003
planning_base_branch: main
merge_target_branch: main
branch_strategy: Planning artifacts for this feature were generated on main. During /spec-kitty.implement this WP may branch from a dependency-specific base, but completed changes must merge back into main unless the human explicitly redirects the landing branch.
base_branch: kitty/mission-agent-executor-v1-1-audit-followups-01KS3S5M
base_commit: c654a1198721c28a88a4fa2c568a378574a18c89
created_at: '2026-05-21T00:25:33.495326+00:00'
subtasks:
- T001
- T002
- T003
- T004
- T005
- T006
- T007
- T008
shell_pid: "705035"
agent: "claude:sonnet:implementer:implementer"
history:
- date: '2026-05-20T23:57:13Z'
  event: created
authoritative_surface: packages/ai-agent/src/Provider/
execution_mode: code_change
owned_files:
- packages/ai-agent/src/Provider/ProviderException.php
- packages/ai-agent/src/Provider/TransportException.php
- packages/ai-agent/src/Provider/ClientErrorException.php
- packages/ai-agent/src/Provider/RateLimitException.php
- packages/ai-agent/src/Provider/AnthropicProvider.php
- packages/ai-agent/src/Provider/OpenAiCompatibleProvider.php
- packages/ai-agent/src/AgentExecutor.php
- packages/ai-agent/tests/Unit/Provider/AgentExecutorRetryTest.php
tags: []
---

# WP01 — Provider Exception Hierarchy + Retry Semantics

**Closes**: #1509
**Depends on**: (none)
**Implement command**: `spec-kitty agent action implement WP01 --agent <name>`

## Objective

Replace the bare `\RuntimeException` throws in `AnthropicProvider` (and `OpenAiCompatibleProvider`) with a typed exception hierarchy so `AgentExecutor::callProviderWithRetry` can classify errors correctly: 4xx non-429 errors re-throw immediately (no retry budget burned); 5xx / network / transport errors and 429 rate-limit errors retry per the FR-025 budget.

## Context

`AgentExecutor::callProviderWithRetry` (line 582) currently has a `@todo` comment acknowledging the gap:

```
// @todo Narrow this catch once the provider exception
// hierarchy exists. AnthropicProvider throws bare \RuntimeException
// for both 4xx (non-429) and 5xx...
```

`RateLimitException` already exists at `packages/ai-agent/src/Provider/RateLimitException.php` and `extends \RuntimeException`. After this WP it must extend the new `ProviderException` abstract base.

Layer note: all new exception classes live in `packages/ai-agent/src/Provider/` (L5 ai-agent). No upward imports. Keep `declare(strict_types=1)` in every new file.

## Subtasks

### T001 — Create `ProviderException` abstract base class

**File**: `packages/ai-agent/src/Provider/ProviderException.php`

**Purpose**: Common ancestor for all AI provider exceptions. Allows a single catch clause for "any provider error" in defensive code, while subclasses carry the retry semantics.

**Steps**:
1. Create `packages/ai-agent/src/Provider/ProviderException.php`:
   ```php
   <?php
   declare(strict_types=1);
   
   namespace Waaseyaa\AI\Agent\Provider;
   
   /**
    * Base class for all AI provider errors.
    *
    * Subclasses carry retry semantics:
    * - {@see TransportException} — transient (5xx / network) — retryable
    * - {@see RateLimitException} — 429 rate limit — retryable with backoff
    * - {@see ClientErrorException} — 4xx non-429 — non-retryable
    */
   abstract class ProviderException extends \RuntimeException
   {
   }
   ```
2. `final class` is NOT used here because it is an abstract base intended for extension.
3. No constructor override needed — inherits `\RuntimeException`'s `(string $message = '', int $code = 0, ?\Throwable $previous = null)`.

**Validation**:
- [ ] Class is abstract, extends `\RuntimeException`
- [ ] `declare(strict_types=1)` present
- [ ] Namespace is `Waaseyaa\AI\Agent\Provider`

---

### T002 — Create `TransportException`

**File**: `packages/ai-agent/src/Provider/TransportException.php`

**Purpose**: Represents transient errors: 5xx HTTP responses, connection timeouts, network interruptions. These are retryable per FR-025.

**Steps**:
1. Create `packages/ai-agent/src/Provider/TransportException.php`:
   ```php
   <?php
   declare(strict_types=1);
   
   namespace Waaseyaa\AI\Agent\Provider;
   
   /**
    * Thrown for transient provider errors: 5xx HTTP responses, connection
    * timeouts, and network-level failures. Retryable per the FR-025 budget.
    */
   final class TransportException extends ProviderException
   {
   }
   ```
2. `final class` applies — this is a concrete leaf exception.

**Validation**:
- [ ] `final class TransportException extends ProviderException`
- [ ] `declare(strict_types=1)` present

---

### T003 — Create `ClientErrorException`

**File**: `packages/ai-agent/src/Provider/ClientErrorException.php`

**Purpose**: Represents 4xx non-429 errors (e.g., 400 Bad Request, 401 Unauthorized, 403 Forbidden, 422 Unprocessable). These are NOT retryable — retrying a client error burns quota on a non-recoverable failure.

**Steps**:
1. Create `packages/ai-agent/src/Provider/ClientErrorException.php`:
   ```php
   <?php
   declare(strict_types=1);
   
   namespace Waaseyaa\AI\Agent\Provider;
   
   /**
    * Thrown for 4xx HTTP errors that are NOT rate-limit (429) responses.
    * Non-retryable: the error is in the request, not the provider's availability.
    */
   final class ClientErrorException extends ProviderException
   {
   }
   ```

**Validation**:
- [ ] `final class ClientErrorException extends ProviderException`
- [ ] `declare(strict_types=1)` present

---

### T004 — Update `RateLimitException` to extend `ProviderException`

**File**: `packages/ai-agent/src/Provider/RateLimitException.php`

**Purpose**: `RateLimitException` currently extends `\RuntimeException` directly. After this WP it must extend `ProviderException` so the entire typed hierarchy has a common ancestor.

**Steps**:
1. Open `packages/ai-agent/src/Provider/RateLimitException.php`.
2. Change `extends \RuntimeException` to `extends ProviderException`.
3. Do NOT change the class signature or constructor — backward-compatible change (existing `catch (\RuntimeException)` clauses still work because `ProviderException extends \RuntimeException`).

**Validation**:
- [ ] `final class RateLimitException extends ProviderException`
- [ ] Existing PHPUnit test for `RateLimitException` (if any) still passes

---

### T005 — Update `AnthropicProvider` to throw typed exceptions

**File**: `packages/ai-agent/src/Provider/AnthropicProvider.php`

**Purpose**: Replace bare `throw new \RuntimeException(...)` for HTTP outcomes with the typed exceptions:
- 429 → `RateLimitException` (may already throw this — verify)
- 5xx + connection errors → `TransportException`
- 4xx non-429 (400, 401, 403, 422, ...) → `ClientErrorException`

**Steps**:
1. Open `packages/ai-agent/src/Provider/AnthropicProvider.php`.
2. Add `use` imports for `TransportException` and `ClientErrorException` (and verify `RateLimitException` is already imported).
3. Identify all HTTP response handling blocks that throw `\RuntimeException`.
4. For each throw site, classify by HTTP status range:
   - `$statusCode === 429` → already throws `RateLimitException` (keep; update to extend chain)
   - `$statusCode >= 500` or connection exceptions (curl errors, socket timeouts) → throw `new TransportException($message, $code, $previous)`
   - `$statusCode >= 400 && $statusCode < 500 && $statusCode !== 429` → throw `new ClientErrorException($message, $code, $previous)`
5. Verify no bare `throw new \RuntimeException(...)` remain for HTTP outcomes. Non-HTTP-outcome exceptions (programming errors, serialization failures) may remain as `\RuntimeException` or `\InvalidArgumentException`.

**Validation**:
- [ ] No `throw new \RuntimeException` for HTTP status codes
- [ ] 429 → `RateLimitException`, 5xx/network → `TransportException`, 4xx non-429 → `ClientErrorException`
- [ ] PHPStan clean (no type errors in new throws)

---

### T006 — Update `OpenAiCompatibleProvider` to throw typed exceptions

**File**: `packages/ai-agent/src/Provider/OpenAiCompatibleProvider.php`

**Purpose**: Same pattern as T005. `OpenAiCompatibleProvider` shares the same HTTP-status classification logic for retryable vs non-retryable errors.

**Steps**:
1. Open `packages/ai-agent/src/Provider/OpenAiCompatibleProvider.php`.
2. Apply the same classification as T005:
   - 429 → `RateLimitException`
   - 5xx / connection → `TransportException`
   - 4xx non-429 → `ClientErrorException`
3. If the provider's error handling is minimal or non-existent (check implementation), add the classification at the HTTP call site.

**Note**: The spec (§Out of scope) limits this mission to `AnthropicProvider` and any currently present providers. Future providers adopt the hierarchy when added.

**Validation**:
- [ ] Same three-class throw pattern applied
- [ ] No bare `\RuntimeException` for HTTP outcomes

---

### T007 — Refine `AgentExecutor::callProviderWithRetry`

**File**: `packages/ai-agent/src/AgentExecutor.php`

**Purpose**: Replace the `@todo`-marked `catch (\Throwable $e)` with typed catch clauses that correctly implement FR-003:
- `TransportException` → retry (same as `RateLimitException`)
- `RateLimitException` → retry (existing behaviour, keep)
- `ClientErrorException` → re-throw immediately (no retry)
- Any other exception → re-throw immediately (no retry)

**Steps**:
1. Open `packages/ai-agent/src/AgentExecutor.php`, navigate to `callProviderWithRetry` (line ~582).
2. Add `use` imports:
   ```php
   use Waaseyaa\AI\Agent\Provider\TransportException;
   use Waaseyaa\AI\Agent\Provider\ClientErrorException;
   use Waaseyaa\AI\Agent\Provider\ProviderException;
   ```
   (`RateLimitException` is already imported on line 16.)
3. Rewrite the catch chain inside the retry loop:
   ```php
   } catch (RateLimitException $e) {
       // 429 — retryable with backoff (existing logic)
       $this->handleRateLimitRetry($e, $attempt, $maxAttempts);
   } catch (TransportException $e) {
       // 5xx / network — retryable, same budget
       if ($attempt >= $maxAttempts) {
           throw $e;
       }
       $this->waitBeforeRetry($attempt);
   } catch (ClientErrorException $e) {
       // 4xx non-429 — non-retryable, re-throw immediately
       throw $e;
   } catch (\Throwable $e) {
       // Unknown exception — non-retryable, re-throw immediately
       throw $e;
   }
   ```
4. Remove the `@todo` comment.
5. Add an ownership comment:
   ```php
   // Retry semantics: RateLimitException (429) and TransportException (5xx/network)
   // are retried. ClientErrorException (4xx non-429) and all other exceptions re-throw
   // immediately without consuming retry budget. See FR-003.
   ```
6. Preserve the existing retry budget logic (FR-025; do NOT change `$maxAttempts` or backoff values).

**Validation**:
- [ ] `@todo` comment removed
- [ ] Three explicit catch clauses in order: `RateLimitException`, `TransportException`, `ClientErrorException`, `\Throwable`
- [ ] `ClientErrorException` and `\Throwable` re-throw immediately
- [ ] Retry budget values unchanged

---

### T008 — Write `AgentExecutorRetryTest` (FR-010)

**File**: `packages/ai-agent/tests/Unit/Provider/AgentExecutorRetryTest.php`

**Purpose**: Regression coverage for the retry semantics. Four test cases covering each decision branch in `callProviderWithRetry`.

**Steps**:
1. Create `packages/ai-agent/tests/Unit/Provider/AgentExecutorRetryTest.php`.
2. Use `#[Test]`, `#[CoversClass(AgentExecutor::class)]` attributes.
3. Write four test methods:

   **Test 1 — `rateLimitExceptionIsRetried`**:
   - Create a mock `ProviderInterface` that throws `RateLimitException` on the first call, returns a valid response on the second.
   - Assert that `AgentExecutor` calls the provider twice and returns the successful response.

   **Test 2 — `transportExceptionIsRetried`**:
   - Mock provider throws `TransportException` on first call, succeeds on second.
   - Assert provider called twice and response returned.

   **Test 3 — `clientErrorExceptionRethrownImmediately`** (covers SC-003):
   - Mock provider always throws `ClientErrorException`.
   - Assert `ClientErrorException` propagates from `callProviderWithRetry` after exactly ONE provider call (no retry).

   **Test 4 — `genericExceptionRethrownImmediately`**:
   - Mock provider throws `\InvalidArgumentException`.
   - Assert it propagates after exactly one call.

4. For test isolation, instantiate `AgentExecutor` with a fake/null implementation for all non-provider dependencies (broadcaster, event dispatcher can be null/NullLogger stubs at this stage since WP02 adds dispatcher injection).

**Validation**:
- [ ] Four test methods present
- [ ] All four green (`./vendor/bin/phpunit packages/ai-agent/tests/Unit/Provider/AgentExecutorRetryTest.php`)
- [ ] No test makes network calls

---

## Branch Strategy

**Planning/base branch**: `main`
**Merge target**: `main`
**Execution**: Worktree allocated per `lanes.json` computed by `finalize-tasks`. This WP has no dependencies and will be in lane 1.

## Definition of Done

- [ ] Three new exception files created in `packages/ai-agent/src/Provider/`
- [ ] `RateLimitException` extends `ProviderException` (not `\RuntimeException` directly)
- [ ] `AnthropicProvider` throws typed exceptions for all HTTP outcomes
- [ ] `OpenAiCompatibleProvider` throws typed exceptions for all HTTP outcomes
- [ ] `AgentExecutor::callProviderWithRetry` has typed catch clauses, `@todo` removed
- [ ] `AgentExecutorRetryTest` passes (4 test methods green)
- [ ] PHPStan clean — `composer phpstan` passes
- [ ] `composer cs-check` passes (or `composer cs-fix` run and committed)
- [ ] No bare `\RuntimeException` throw for HTTP status outcomes in Provider/ classes

## Risks

| Risk | Mitigation |
|---|---|
| `RateLimitException` used in callers that catch it specifically | The change is backward-compatible; existing catch clauses still work |
| `callProviderWithRetry` retry budget accidentally changed | Only rewrite the catch clauses; preserve all existing retry-count and backoff variables |
| PHPStan dead-code gate fires on new classes | New `final class` leaves that are thrown/caught are not dead code; no annotation needed |

## Reviewer Guidance

1. Check that no HTTP-outcome throw site in `AnthropicProvider` or `OpenAiCompatibleProvider` still throws bare `\RuntimeException`.
2. Verify `callProviderWithRetry` retry budget variables (`$maxAttempts`, sleep values) are unchanged.
3. Confirm `AgentExecutorRetryTest` tests each of the four branches and uses `exactly(1)` / `exactly(2)` mock expectations to validate retry count.
4. Confirm `ProviderException` is abstract (not final, not interface).

## Activity Log

- 2026-05-21T00:25:35Z – claude:sonnet:implementer:implementer – shell_pid=705035 – Assigned agent via action command
- 2026-05-21T00:38:56Z – claude:sonnet:implementer:implementer – shell_pid=705035 – TransportException/ClientErrorException/RateLimitException hierarchy; AnthropicProvider and OpenAiCompatibleProvider categorize by HTTP status; AgentExecutor retry budget unchanged; 4/4 AgentExecutorRetryTest green; cs-check + phpstan clean
