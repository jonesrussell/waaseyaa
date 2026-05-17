# Mission Spec: CSRF for Inertia File Uploads

**Mission ID**: `01KQZJQJV8XMG9C1PF7TVMKKHE` (mid8: `01KQZJQJ`)
**Mission Slug**: `inertia-file-upload-csrf-01KQZJQJ`
**Mission Type**: `software-dev`
**Target Branch**: `main`
**Created**: 2026-05-06
**Status**: Draft (specify phase)

---

## Overview

Inertia.js apps that submit `multipart/form-data` (the standard pattern for file uploads, triggered by `forceFormData: true`) cannot currently submit to CSRF-protected routes in any consumer of the Waaseyaa framework. The CSRF middleware in `waaseyaa/user` exempts JSON requests but rejects multipart submissions, and the framework provides no mechanism to expose the CSRF token to the client. This mission removes that gap by adding a JS-reachable token surface that follows the convention Inertia and Laravel consumers already expect, so file-upload UIs work in convention-following apps with **zero consumer-side wiring**.

The fix lives entirely in the framework. No consumer-side workaround is acceptable.

---

## User Scenarios & Testing

### Primary User Stories

**US-1: Application developer building a file upload page**

As an application developer using the Waaseyaa framework with Inertia and Vue, I want to write an Inertia form that uploads a file (`form.post(route, { forceFormData: true })`) without any CSRF-specific code, so that I can build file-upload features the same way Laravel/Inertia developers do in any other ecosystem.

**Acceptance**: A new Inertia form page wired only with standard Inertia conventions (no CSRF token reading, no header injection, no hidden field) can successfully POST a multipart payload to a CSRF-protected route and receive a 200/302 response. The framework supplies the token transparently.

**US-2: Application developer protecting an existing JSON or traditional form route**

As an application developer with existing routes accepting `application/json` or traditional `application/x-www-form-urlencoded` posts, I want my routes to keep working exactly as they did before, so that this fix does not become a forced upgrade requiring me to rework working code.

**Acceptance**: All currently-passing CSRF behaviors for JSON and form-urlencoded posts continue to pass without modification. No existing test in `waaseyaa/user` regresses.

**US-3: Giiken end user uploading a knowledge source**

As a Giiken community member uploading a document through the Ingestion page, I want the upload to succeed and produce a knowledge item, so that I can contribute content the way the UI advertises.

**Acceptance**: A real file (e.g., `.md`, `.csv`, `.docx`) uploaded through the Giiken Ingestion page on a deployment using the framework version produced by this mission lands in storage, creates a `knowledge_item`, and the page transitions to a success state.

### Acceptance Scenarios

| ID  | Scenario                                                                                                                                | Expected Outcome                                                                                                       |
| --- | --------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| AS-1 | A first-page-load GET request to any HTML response from a Waaseyaa-bootstrapped app                                                     | Response includes a JS-reachable CSRF token surface following the standard convention                                  |
| AS-2 | An Inertia POST with `Content-Type: multipart/form-data` carrying the conventionally-forwarded CSRF header to a protected route          | 200/302 response (request authorized)                                                                                 |
| AS-3 | An Inertia POST with `multipart/form-data` and **no** CSRF header                                                                       | 403 Invalid Security Token (existing protection preserved)                                                            |
| AS-4 | An Inertia POST with `multipart/form-data` and a **stale or wrong** CSRF token in the conventional header                                | 403 Invalid Security Token                                                                                             |
| AS-5 | An existing `application/json` POST with the existing token mechanism                                                                   | Behavior unchanged (still authorized when token correct, still 403 when wrong)                                        |
| AS-6 | An existing `application/x-www-form-urlencoded` POST with the existing `_token` field                                                   | Behavior unchanged                                                                                                     |
| AS-7 | A Giiken Ingestion upload (real multipart file submit) on a deployment using the framework build produced by this mission              | Upload succeeds, file lands in storage, `knowledge_item` row created, UI shows success                                |
| AS-8 | The token surface is consumed by a non-Inertia consumer (e.g., a vanilla `fetch` client reading the conventional value)                 | Convention is documented and the token is reachable through the documented mechanism                                  |

### Edge Cases

- **Token rotation**: The mission does not change rotation policy (out of scope), but the new surface must reflect whatever token the existing system considers current. After a rotation, the surface returns the new token on the next response.
- **Missing session**: A request with no session yet (first visit) must still receive a token surface on its first HTML response so subsequent submits can use it.
- **HTTPS / cookie security flags**: Where the new surface uses cookies, defaults must be safe (HttpOnly choice is design-direction-dependent and decided in plan; Secure flag must follow the request scheme; SameSite default must be Lax or stricter).
- **Reverse proxies / sub-paths**: Cookie path/domain handling must work when the app is mounted at a sub-path or behind a reverse proxy that rewrites host.
- **Multiple tabs**: Token must remain valid across concurrently open tabs of the same app sharing one session.

---

## Requirements

### Functional Requirements

| ID     | Requirement                                                                                                                                                                                                                                                  | Status   |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- |
| FR-001 | The framework MUST expose the active CSRF token to JavaScript on every HTML response from a Waaseyaa-bootstrapped app, through a documented convention reachable without app-specific code.                                                                  | Proposed |
| FR-002 | The framework MUST accept the CSRF token from a JS-reachable conventional source (e.g., a header populated automatically by Inertia's HTTP client) for any state-changing request, regardless of `Content-Type`.                                              | Proposed |
| FR-003 | The framework MUST continue to accept the CSRF token from the existing `_token` form field for `application/x-www-form-urlencoded` and `multipart/form-data` requests that include it.                                                                       | Proposed |
| FR-004 | The framework MUST continue to exempt `application/json` requests from CSRF as it does today, with no behavioral change for that content type.                                                                                                                | Proposed |
| FR-005 | The framework MUST reject state-changing `multipart/form-data` requests that lack a valid CSRF token in any accepted location, returning the existing 403 Invalid Security Token response.                                                                   | Proposed |
| FR-006 | The token surface MUST reflect token rotations: after the existing system rotates the session token, the next HTML response surface MUST carry the new token.                                                                                                | Proposed |
| FR-007 | The framework MUST provide one way for non-Inertia consumers (vanilla `fetch`, traditional templates) to read the same token, documented in the framework docs.                                                                                              | Proposed |
| FR-008 | A consumer application that follows standard Inertia conventions (uses `@inertiajs/vue3`, default axios client, `forceFormData: true` for file uploads) MUST submit successfully to a CSRF-protected route with **zero application-level CSRF-specific code**. | Proposed |

### Non-Functional Requirements

| ID      | Requirement                                                                                                                                                                                                            | Threshold                                                                                                       | Status   |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- | -------- |
| NFR-001 | The token surface MUST not measurably degrade response time on HTML responses.                                                                                                                                          | p95 added overhead < 1 ms per response, measured locally on the existing benchmark suite if present, else N/A   | Proposed |
| NFR-002 | The new behavior MUST be covered by unit tests in `waaseyaa/user` that exercise every accepted token location for every accepted Content-Type.                                                                          | 100% of token-acceptance pathways covered (Content-Type × token-source matrix); tests run under `composer test` | Proposed |
| NFR-003 | The new behavior MUST be covered by an integration-level test that simulates an Inertia multipart submit through the framework's HTTP pipeline.                                                                         | At least one integration test exercising the full middleware stack with multipart + conventional header         | Proposed |
| NFR-004 | The framework MUST emit no new deprecation warnings, PHP notices, or test-suite warnings on any platform currently supported by the framework matrix.                                                                  | `vendor/bin/phpunit` and any existing `phpstan` runs complete with zero new warnings                            | Proposed |
| NFR-005 | The mission MUST be verifiable through a real cross-repo smoke test: a Giiken deployment using the new framework version performs a successful Ingestion upload of a real file in a browser session.                    | One documented manual smoke run with screenshot or curl trace evidence stored in the mission's `artifacts/`     | Proposed |

### Constraints

| ID    | Constraint                                                                                                                                                                                                                                                                          | Status   |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| C-001 | The fix MUST live entirely inside the Waaseyaa framework packages. Consumer applications (Giiken and others) MUST receive zero CSRF-related code changes as part of this mission.                                                                                                  | Required |
| C-002 | The mission MUST NOT introduce per-route CSRF exemption configuration (separate concern, future mission).                                                                                                                                                                            | Required |
| C-003 | The mission MUST NOT change the CSRF token rotation policy or session driver.                                                                                                                                                                                                       | Required |
| C-004 | The mission MUST NOT introduce a hard dependency on a Laravel package or any other framework. The convention may be Laravel/Inertia-compatible but the implementation must remain Waaseyaa-native.                                                                                  | Required |
| C-005 | The acceptance gate for mission completion includes a real cross-repo smoke test against the Giiken Ingestion page using the framework version produced by this mission. Mission cannot be marked done until that smoke succeeds and evidence is captured in `artifacts/`.          | Required |
| C-006 | All work is on `main` (target_branch from `branch-context`). Standard spec-kitty worktree-per-lane will be created during `/spec-kitty.implement`.                                                                                                                                  | Required |
| C-007 | The implementation approach (XSRF cookie + header bridge vs. Inertia shared prop vs. hybrid) is intentionally **deferred to `/spec-kitty.plan`**. The spec does not pick a mechanism; it specifies the observable behavior.                                                          | Required |

---

## Success Criteria

| ID    | Outcome                                                                                                                                                                                                                                                              | How verified                                                                                                                       |
| ----- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| SC-1  | A convention-following Inertia consumer can submit a multipart file upload to a CSRF-protected route with zero CSRF-specific application code.                                                                                                                       | New integration test in `waaseyaa/user` (or appropriate package) exercising Inertia multipart through the full middleware stack. |
| SC-2  | All existing CSRF behaviors (JSON exempt, `_token` form field, header path if any, traditional posts) continue to pass.                                                                                                                                              | Existing `vendor/bin/phpunit` suites in affected packages pass without modification of their assertions.                          |
| SC-3  | The framework documentation explains the CSRF convention for both Inertia consumers ("you get this for free") and non-Inertia consumers (how to read the token), with at least one runnable example per audience.                                                  | Doc page added or updated in the framework's docs surface; reviewed during `/spec-kitty.review`.                                  |
| SC-4  | A real Giiken Ingestion file upload succeeds end-to-end against a Giiken instance running the framework build produced by this mission, against either a path-repo or a published alpha tag.                                                                          | Manual smoke run with terminal/browser evidence captured in `kitty-specs/inertia-file-upload-csrf-01KQZJQJ/artifacts/`.            |
| SC-5  | The mission introduces zero new warnings in PHPStan or PHPUnit on the framework's standard test/lint commands.                                                                                                                                                       | `composer analyse` and `composer test` baselines unchanged.                                                                       |

---

## Key Entities

This mission does not introduce new domain entities. It changes framework infrastructure: middleware, response behavior, and possibly a new HTTP-layer component (cookie writer or shared-prop emitter, decided in plan).

Touched code surfaces (illustrative, not prescriptive — final list is a plan-phase output):

- `packages/user/` — `CsrfMiddleware` and CSRF token issuance.
- `packages/foundation/` or `packages/http/` — response pipeline; if the design adds a cookie or header writer, it likely lives here.
- Framework docs surface — convention page.
- Framework tests — middleware unit tests, integration test for Inertia multipart.

---

## Assumptions

1. The existing `_token` form-field acceptance and JSON-exempt rule reflect intentional, correct behavior and should remain. (Confirmed by problem statement.)
2. Giiken's Ingestion page is the only file-upload UI in the consumer ecosystem today. Smoke-testing against it covers the real-world consumer surface. (Stated in problem brief.)
3. The Waaseyaa user package owns CSRF; no other package currently writes CSRF tokens. (Inferred from the brief; planning will validate by code reading.)
4. The chosen convention (cookie + header, shared prop, or hybrid) is decidable in `/spec-kitty.plan` based on a brief reading of `waaseyaa/user`, `waaseyaa/foundation`, and `waaseyaa/http`. The spec does not pre-commit.

---

## Dependencies

- **Cross-repo verification dependency**: The hard acceptance gate (C-005, SC-4) requires a working Giiken checkout with a path-repo composer link to the Waaseyaa monorepo. The smoke step does not modify Giiken; it only points Giiken's `composer.json` repositories at the local path during verification, runs the upload, then reverts.
- **No new external dependencies**: The implementation must not pull in new composer packages.

---

## Out of Scope

- Per-route CSRF exemption configuration.
- CSRF token rotation policy changes.
- Session driver changes.
- Refactoring the existing `application/json` exemption rule.
- Creating a generic file-upload UI component or upload-size configuration in the framework.
- Changes to Giiken consumer code (other than the verification-only path-repo configuration that is reverted after smoke).

---

## Verification Plan (high-level)

Three layers, all gated:

1. **Unit**: `waaseyaa/user` middleware and token issuance tests covering the full Content-Type × token-source acceptance matrix.
2. **Integration**: At least one test that exercises the framework's HTTP pipeline end-to-end with an Inertia multipart submit.
3. **Cross-repo smoke (hard gate)**: Real Giiken Ingestion upload against a path-repo build of the framework, evidence in `artifacts/`.

The cross-repo smoke is the mission's release gate. Unit and integration green is necessary but not sufficient.

---

## Open Questions

None at spec time. The design-direction decision (XSRF cookie vs. shared prop vs. hybrid) is intentionally deferred to `/spec-kitty.plan` and is not a `[NEEDS CLARIFICATION]` because the spec specifies behavior, not mechanism.

---

## Branch Strategy

- Current branch at workflow start: `main`
- Planning/base branch: `main`
- Final merge target: `main`
- `branch_matches_target`: true

(All values from `spec-kitty agent mission branch-context --json` at mission creation.)
