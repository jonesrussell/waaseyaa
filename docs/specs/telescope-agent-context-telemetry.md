# Telescope agent-context telemetry

<!-- Spec reviewed 2026-04-24 - Prometheus canonical waaseyaa_agent_context_* + legacy waaseyaa_cc_* mirrors; HTTP /api/telescope/agent-context/* + codified-context aliases; Telescope record.agent_context precedence -->

Operator-facing **names** for Telescope’s codified-context pipeline (session/event/validation recorders, drift scoring). Storage **types** (`cc_session`, `cc_event`, `cc_validation`), SQLite table `telescope_cc_entries`, and JSONL `telescope_cc.jsonl` are **not** renamed here — they remain the durable internal representation.

## Prometheus (canonical + deprecated)

`Waaseyaa\Telescope\CodifiedContext\Storage\PrometheusCodifiedContextStore` exposes text exposition with:

| Canonical metric | Type | Meaning |
|------------------|------|---------|
| `waaseyaa_agent_context_sessions_total` | counter | Distinct `session_id` values observed |
| `waaseyaa_agent_context_events_total` | counter | `store()` invocations |
| `waaseyaa_agent_context_drift_events_total` | counter | Drift-related / severity-flagged events (same heuristics as before) |
| `waaseyaa_agent_context_validations_total` | counter | Validation-ish types |
| `waaseyaa_agent_context_drift_score_avg` | gauge | Running mean of numeric `drift_score` samples |

**Deprecated duplicates:** `waaseyaa_cc_*` series are emitted in the **same** scrape with identical sample values and `Deprecated:` HELP lines so existing dashboards keep working. Remove the legacy block after downstream consumers migrate.

`getMetrics()` returns **both** canonical and `waaseyaa_cc_*` keys (same values) for in-process introspection.

## HTTP JSON (canonical + legacy)

Routes are registered in **`Waaseyaa\Foundation\Kernel\BuiltinRouteRegistrar`**. All routes require the **`admin`** role.

| Action | Canonical path | Legacy alias |
|--------|----------------|--------------|
| List sessions | `GET /api/telescope/agent-context/sessions` | `GET /api/telescope/codified-context/sessions` |
| Session row | `GET /api/telescope/agent-context/sessions/{sessionId}` | `…/codified-context/sessions/{sessionId}` |
| Events | `GET …/sessions/{sessionId}/events` | same under `codified-context` |
| Validation | `GET …/sessions/{sessionId}/validation` | same under `codified-context` |

**Dispatch:** `Waaseyaa\Foundation\Http\Router\CodifiedContextApiRouter` instantiates `Waaseyaa\Api\Controller\CodifiedContextController` with **`HttpKernel::getCodifiedContextSessionStore()`** (nullable). Hosts that persist codified-context data should call **`HttpKernel::setCodifiedContextSessionStore()`** during boot (e.g. from a `ServiceProvider::configureHttpKernel()` hook) with an adapter such as **`CodifiedContextSessionStoreAdapter`**.

## Telescope config toggle

Under `telescope.record`:

- **`agent_context`** — when this key **exists** (even `false`), it alone controls the codified-context observer (`CodifiedContextObserver`).
- **`codified_context`** — used only when `agent_context` is **absent** (legacy behaviour).

## Admin SPA

The Nuxt dev pages may keep the historical route path **`/telescope/codified-context`** for navigation; API calls target **`/api/telescope/agent-context/…`** (see `packages/admin/app/composables/useCodifiedContext.ts`).
