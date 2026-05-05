# Minoo Resume Verification Plan

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Audience**: Minoo maintainers and operators resuming the frozen `upgrade-waaseyaa-alpha-171-01KQTDC2` upgrade mission against the next framework alpha after #1390 lands.
**Self-contained**: yes (NFR-004). You should be able to run this plan end-to-end without reading framework source.

---

## Prerequisites

Before starting, verify all of these:

| Item                                                                                                  | How to verify                                                                                              | Pass signal                                  |
|-------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|----------------------------------------------|
| Framework issue #1390 is **closed** as resolved (the dispatcher shim is merged on `main`)              | `gh issue view 1390 --repo waaseyaa/framework --json state,closedAt`                                       | `state: CLOSED`, non-null `closedAt`         |
| The next alpha tag has been cut from the merged `main` (e.g., `0.1.0-alpha.173` or whatever ships next) | `gh release list --repo waaseyaa/framework --limit 5`                                                       | A tag dated *after* #1390's `closedAt`       |
| Your Minoo checkout is at the head of the upgrade-mission branch                                       | `git status` from the Minoo repo                                                                            | Clean tree on `kitty/mission-upgrade-waaseyaa-alpha-171-01KQTDC2-lane-{a,planning}` (or the branch your team uses) |
| PHP 8.4+ available locally (Minoo and framework share the project minimum)                             | `php -v`                                                                                                    | Major.minor ≥ 8.4                             |
| Composer ≥ 2.x                                                                                          | `composer --version`                                                                                        | Major version 2                              |

If any prerequisite fails, **stop**. Do not proceed; escalate to the framework team.

## Step 1 — Pin to the new alpha

In the Minoo repo:

```bash
# Replace ALPHA_TAG with the actual version from the gh release list above, e.g. 0.1.0-alpha.173
ALPHA_TAG="0.1.0-alpha.<NNN>"
composer require "waaseyaa/framework:${ALPHA_TAG}" --no-update
composer update waaseyaa/* --with-all-dependencies
```

| Pass signal                                                                                            | Failure signal                                                                                          |
|--------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------|
| `composer update` exits 0; `composer.lock` shows `waaseyaa/framework` at `${ALPHA_TAG}` and the same version pinned across every `waaseyaa/*` sibling. | Composer reports unresolvable conflicts → re-check that `ALPHA_TAG` exists on Packagist and matches the GitHub release. |

If failure, escalate. Do not proceed to Step 2.

## Step 2 — Boot the kernel

```bash
# From the Minoo repo root:
php artisan key:generate --force  # if Minoo uses an artisan-style CLI
# OR, if Minoo uses bin/waaseyaa:
bin/waaseyaa optimize:manifest
```

| Pass signal                                                          | Failure signal                                                                                              |
|----------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|
| Command completes with exit code 0; no stack trace; no `Application failed to boot` line in the output. | Any stack trace, `RuntimeException`, or `Class … not found`. → Capture full output and file a framework issue. |

## Step 3 — Smoke a previously-failing route

The pre-#1390 failure mode was: every public route returned HTTP 500 with body containing `"Parameter $params: array parameters require #[MapRoute] or #[MapQuery]."`. Pick a route that previously failed (any public route in Minoo will do) and request it.

```bash
# Start whatever local server Minoo uses:
composer dev   # or: php artisan serve, or: bin/waaseyaa serve, etc.

# In another shell:
curl -sS -i http://127.0.0.1:8000/  # or any public route Minoo serves
```

| Pass signal                                                                                                                                          | Failure signal                                                                                                       |
|------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| Response status is 200 (or 3xx redirect, depending on the route), and the body is the expected page content. No `Parameter $...: array parameters require ...` text in the response. | HTTP 500 with the old rejection message → escalate; the shim did not actually land. HTTP 500 with a different stack trace → capture and proceed to Step 4 to triangulate. |

Repeat for at least three previously-failing routes (the `#1390` issue identified the top files: `StaticPageController`, `MessagingController`, `NewsletterAdminApiController`). One route per controller class is sufficient.

## Step 4 — Confirm deprecation log emission

The framework now emits one structured `notice` per `(controller, method, parameter)` registration that relies on the implicit-array shim. Minoo should see ~184 such log lines on cold boot (per #1390's blast radius count).

```bash
# Tail Minoo's configured log file. The path varies; the most common:
tail -F storage/logs/laravel.log  # or wherever Minoo's logger writes

# In another shell, hit a route that has not yet been touched in this process:
curl -sS http://127.0.0.1:8000/<some-route> > /dev/null
```

Look for log lines with `channel: dispatcher.deprecation`. The schema is documented in `post-1390-dispatcher-contract.md` §5; the JSON context fields you should see at minimum are:

```json
{
  "channel": "dispatcher.deprecation",
  "event": "implicit_array_shim",
  "controller_class": "App\\Controller\\StaticPageController",
  "method": "show",
  "parameter_name": "params",
  "recommended_attribute": "MapRoute"
}
```

| Pass signal                                                                                                                                                                                         | Failure signal                                                                                                          |
|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| At least one `dispatcher.deprecation` notice fires for the controllers Minoo just hit; the JSON context fields match the documented schema.                                                          | No deprecation lines at all → either Minoo's logger isn't capturing `notice` level (raise `LOG_LEVEL` to `debug` and retry), or the framework alpha did not include the WP02 emitter (verify the changelog for the alpha). |
| The line count grows monotonically as you exercise more routes, capping at ~184 distinct triples (the Minoo count documented in #1390).                                                              | Same notice fires repeatedly for the same triple → the dedup invariant is broken; escalate.                              |

To extract Minoo's migration backlog from the logs:

```bash
grep '"channel":"dispatcher.deprecation"' storage/logs/laravel.log \
  | jq -r '.context | "\(.controller_class)::\(.method) ($\(.parameter_name) → #[\(.recommended_attribute)])"' \
  | sort -u
```

This list IS Minoo's migration backlog. Each entry corresponds to one method that should add an explicit attribute to suppress the notice.

## Step 5 — Run Minoo's test suites

```bash
# Unit + feature tests (Pest or PHPUnit, whichever Minoo uses):
composer test

# E2E if available:
npm run test:e2e
```

| Pass signal                                                          | Failure signal                                                                                            |
|----------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------|
| All suites green. Note: Minoo's frozen mission expected 16 integration failures to disappear when #1390 landed — confirm those 16 are now green. | Any test fails. Compare failures to Minoo's frozen mission's expected-failure list; if the failures are not the 16 expected ones, capture and triage separately. |

## Step 6 — Resume the upgrade mission

If Steps 1–5 all passed, Minoo's `upgrade-waaseyaa-alpha-171-01KQTDC2` mission is unblocked. From the Minoo repo:

```bash
# Update the upgrade mission's status from blocked to in_progress:
spec-kitty agent tasks status --mission upgrade-waaseyaa-alpha-171-01KQTDC2 --json

# Continue per Minoo's own Spec-Kitty workflow. The dispatcher rejection is no longer
# a blocker; remaining items in the original mission backlog (JsonResponseTrait shim,
# EntityType _fieldDefinitions migration, ServiceProvider setKernelServices migration,
# phpstan baseline) can now proceed.
```

The exact Spec-Kitty commands to advance the upgrade mission are owned by Minoo's repo and are out of scope for this plan — refer to Minoo's `kitty-specs/upgrade-waaseyaa-alpha-171-01KQTDC2/` directory.

## Escalation

If any step fails in a way the failure-signal rows do not anticipate:

1. Capture the full output of the failing command, including stack traces.
2. Capture the framework version (`composer show waaseyaa/framework`).
3. File a `framework` issue:
   ```bash
   gh issue create --repo waaseyaa/framework \
     --title "Minoo resume failure on alpha.<NNN>: <one-line summary>" \
     --body "$(cat <<'EOF'
   ## Step that failed
   <Step number from minoo-resume-verification.md>

   ## Command run
   ...

   ## Output captured
   ...

   ## Framework version
   ...
   EOF
   )" \
     --milestone "Track 1 — Entity system & hydration"
   ```
4. **Do not proceed to subsequent steps** until the issue is triaged.

## Out of scope

This plan does not address:

- Minoo migrating from implicit-array signatures to attribute-annotated signatures. That is a separate, voluntary follow-up in Minoo's repo. The deprecation notices guide it, but they do not block resume.
- The non-dispatcher items on Minoo's frozen mission (`JsonResponseTrait`, EntityType `_fieldDefinitions`, ServiceProvider `setKernelServices`, phpstan baseline). Those are independent.
- Changes outside `waaseyaa/framework`. If the alpha bump cascades into other `waaseyaa/*` packages, follow each package's release notes; this plan only validates the dispatcher fix.

## Cross-references

- Mission spec: [`../spec.md`](../spec.md) §2 (Goals & Success Criteria — SC-005 anchors here)
- Contract: [`./post-1390-dispatcher-contract.md`](./post-1390-dispatcher-contract.md) §5 (log schema)
- Framework issue: [`waaseyaa/framework#1390`](https://github.com/waaseyaa/framework/issues/1390)
