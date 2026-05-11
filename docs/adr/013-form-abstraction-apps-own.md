# 013 — Form abstraction: apps own form rendering

**Status:** Accepted (2026-05-11)
**Mission:** Stability charter ratification
**Spec context:** `docs/specs/drupal-comparison-matrix.md` §6.4, §1.2, §1.7

## Context

The Field API has three sides: **storage** (have), **widget** (form input), **formatter** (rendered output). Waaseyaa has storage and has SSR templates for the other two. There is no framework contract for "render this field as a form input" or "render this field as display output." Drupal Form API and its widget/formatter plugin system are large — bigger than its Entity API.

The audit's Drupal-comparison matrix flagged this as the **largest quiet gap**. The question is whether to build it or commit to not building it.

## Options considered

### A. Build full Drupal-style Form API + widget plugins

Forms as render arrays, validation pipeline, AJAX, multi-step, file upload, CSRF, formatter plugins, widget plugins, view modes. Comprehensive; expensive; encourages an admin UI culture that locks apps to one rendering technology. Rejected.

### B. Build a minimal widget plugin contract

Each field type declares a default widget and formatter; apps may override. Smaller than A. Still introduces a stable surface ("widget plugin contract") that future framework decisions must respect. Rejected for v0.x; the cost-benefit is wrong while consumer count is one.

### C. Apps own all form rendering (CHOSEN)

The framework provides field-level validation primitives and CSRF on the routing layer. Form input rendering, AJAX, multi-step flows, file upload, and view-mode-style display logic all live in app code. Twig partials in `templates/components/` are the reuse mechanism — across apps via shared composer packages, not via framework plugins.

This is committed publicly. Once apps build authoring UIs around this absence, adding a widget plugin layer later would either bypass app code (breaking) or require app migration (taxing). The commitment removes the temptation to half-build later.

## Decision

Form rendering is an **app concern**. The framework ships:

### Validation primitives

`FieldDefinition::validators()` accepts a list of validators (callables, validator objects, or attribute-marked rules). The framework runs these on `EntityStorage::save()` and raises a typed `EntityValidationException` carrying per-field errors. Apps consume that exception in their form-handling code and re-render with errors.

This is **server-side** validation. Client-side validation is an app concern.

### CSRF

Routing layer issues and validates CSRF tokens on state-changing routes. Stable surface: token issuance API, validation middleware, and the `csrf_token()` Twig function. Apps that bypass CSRF for specific routes (e.g. webhook endpoints) opt out explicitly per route.

### File upload

Media package (`media`, Layer 2) handles file storage. The HTTP-layer upload handling (multipart parsing, size limits, content-type validation) is on the routing layer's stable surface. Form rendering of file inputs is app concern.

### What the framework does NOT ship

- A widget plugin contract.
- A formatter plugin contract.
- View modes as a framework abstraction.
- AJAX form submission helpers (apps use fetch/htmx/whatever fits their frontend stance).
- Multi-step form coordination.
- A form-state object.
- An "admin form" abstraction.

### Cross-app reuse

When two apps want to share an authoring UI for an entity type (e.g. two Indigenous-community apps both editing `Teaching` entities), the reuse mechanism is:

1. A shared composer package containing Twig partials, CSS, and any JS.
2. Both apps depend on the package and `include` the partials.
3. Validation rules live in the entity's `FieldDefinition`, so they ride with the entity, not the form.

This is the same shape as the `jonesrussell/indigenous-taxonomy` package — domain artifacts shipped via composer, not via framework plugins.

## Consequences

- **The form gap is closed by commitment, not by feature.** "Apps own forms" is a position, not a deferral.
- **Cross-app authoring UI reuse requires composer packages, not framework plugins.** A small ecosystem of shared `waaseyaa-ui-*` partial packages becomes the expected pattern.
- **Drupal contrib like Webform or Paragraphs have no direct equivalent.** Apps that want webform-like dynamic forms build them or use frontend solutions.
- **Once consumer apps build around this absence, reversing the decision is itself a breaking change.** This is intentional — the commitment removes the temptation to half-build a widget layer later.
- **Server-side validation as a framework concern is preserved.** The framework still owns "is this entity valid"; it only declines to own "what does the form look like."

## References

- Matrix: `docs/specs/drupal-comparison-matrix.md` §1.2 (field API three sides), §1.7 (forms), §6.4.
- Minoo's `templates/components/` — current per-app pattern.
- `jonesrussell/indigenous-taxonomy` — composer-shared domain artifact pattern.
- Related ADRs: 014 (theme packages — same pattern for templates/CSS), 010 (storage; validation runs at coordinator save).
