# Specification: Greenfield Removal Directive (Charter Amendment)

**Mission ID**: 01KQ7MN5TYE737EJN8EKFN9AEX
**Mission slug**: charter-greenfield-directive-01KQ7MN5
**Mission type**: software-dev
**Target branch**: main
**Created**: 2026-04-27

## Overview

Amend the project charter so that the existing "greenfield removal" rule — currently buried as a sub-bullet inside `DIR-001 (Respect risk boundaries)` — is hoisted into its own top-level directive (`DIR-003`) with `severity: error`, a stable ID, and visibility in the compact charter context loaded by every `/spec-kitty.specify` and `/spec-kitty.plan` invocation.

The substantive policy already exists in `charter.md` § Project Directives. This amendment changes its structural placement and visibility, not its content. The author has explicitly stated: "remove bad architecture, no matter the cost." That instruction is already charter law; it is currently invisible to compact-context consumers.

## Context

### Why this amendment

When agents (including human reviewers consulting the rendered charter) load charter context via `spec-kitty charter context --action <action> --json`, the compact mode returns:

```
Governance:
  - Template set: software-dev-default
  - Paradigms: domain-driven-design
  - Directives: DIR-001, DIR-002
  - Tools: git, spec-kitty
```

DIR-001's title surfaces only its first line — *"Respect risk boundaries: Absolute non-negotiables:"* — which gives no hint that the greenfield removal policy lives inside it. Agents must read the full `charter.md` to discover it. In practice, this means the policy is selectively applied: agents who skim compact context default to the industry-standard "preserve API surface" assumption and only correct course when explicitly reminded.

Hoisting the policy into its own directive (a) makes it a stable referent (`DIR-003`), (b) ensures it appears in compact loads, and (c) allows independent severity tuning.

### Source of policy text

The directive text is taken from `charter.md` as it stands today, lightly edited for self-containment as a top-level directive:

> **DIR-003: Greenfield Removal Policy.** During alpha (current state), the greenfield principle applies. When a better pattern lands, the old one is removed outright. No deprecation window is required. Backwards-compat shims that retain known-bad patterns are forbidden. `@deprecated` wrappers, `Legacy*` namespaces, parallel `v2` interfaces, and "for backward compatibility" comments are not acceptable substitutes for deletion. Architecture quality is preferred over API stability for the duration of alpha. Breaking changes are still announced explicitly per DIR-001 (CHANGELOG.md entry, UPGRADING.md migration recipe) — communication discipline is preserved; compatibility debt is not.

### What is NOT changing

- The procedural rule from DIR-001 — *"No silent breaking changes to public PHP API. Breaking changes are explicit, called out in CHANGELOG.md, and accompanied by an UPGRADING.md migration recipe"* — stays in place. Communication discipline is not "compatibility nonsense"; it is required and unaffected by this amendment.
- The phase-dependent removal policy at `beta entry and beyond` (formal deprecation windows, two-minor-release survival, etc.) stays in place. DIR-003 explicitly scopes itself to "during alpha".
- DIR-001 retains its overall scope (risk boundaries, no Drupal globals, no service locators, no secrets, etc.). Only the greenfield sub-clause is lifted out into its own directive.

## User Scenarios & Testing

### Primary user: any agent (LLM or human) operating under spec-kitty workflows

#### Scenario 1 — Agent loads compact charter context

1. Agent runs `spec-kitty charter context --action specify --json` (or `plan`, or any action).
2. JSON `text` field includes `Directives: DIR-001, DIR-002, DIR-003`.
3. Agent expanding DIR-003 sees a self-contained statement of the greenfield removal policy without having to read the full charter.
4. **Acceptance**: DIR-003 appears in compact context output, with description text that clearly conveys "remove bad code outright, no shims" without requiring further reading.

#### Scenario 2 — Spec author proposes a constraint preserving API surface

1. Author drafts a constraint like *"no breaking changes to FooInterface"* in a mission spec.
2. Reviewer (or agent) flags it against `DIR-003` and either revises the constraint or opens a `charter-exception` issue per the Exception Policy.
3. **Acceptance**: DIR-003 is concrete enough to ground the rejection. The reviewer doesn't have to re-derive the rule from author preference.

#### Scenario 3 — Agent considers writing a `@deprecated` wrapper

1. During implementation, the agent considers adding `@deprecated since 0.5 — use Bar instead` on a wrapper method that forwards to a new API.
2. The agent's loaded charter context surfaces DIR-003.
3. **Acceptance**: The agent recognizes the wrapper is forbidden under DIR-003 and instead deletes the old method, updating all callers in the same change.

### Edge cases

- **Beta entry**: DIR-003 is alpha-scoped. Once the framework declares beta, DIR-003 either retires or is reworded to reflect formal-deprecation rules. The amendment process handles that transition; this mission does not pre-emptively encode it.
- **Security-critical code**: Existing security-advisory removal cadence (DIR-001 sub-clause) takes precedence. DIR-003 does not override.
- **Existing `@deprecated` annotations in the codebase**: Out of scope for this mission. A separate cleanup mission may sweep them. This mission only changes the rule, not its enforcement against existing code.
- **Charter exception**: An author may still file a `charter-exception` issue per the Exception Policy if an unavoidable shim must ship. The exception is time-boxed and visible. DIR-003 does not eliminate the escape hatch; it raises the cost of using it.

## Functional Requirements

| ID | Requirement | Status |
|---|---|---|
| FR-001 | `charter.md` § Project Directives shall include a top-level directive titled "DIR-003: Greenfield Removal Policy" containing the policy text in the Context section above. | Draft |
| FR-002 | The greenfield removal sub-bullet currently inside DIR-001 shall be removed and replaced with a one-line cross-reference: *"See DIR-003 for the alpha-phase greenfield removal policy."* No content from DIR-001 other than that sub-bullet is altered. | Draft |
| FR-003 | After running `spec-kitty charter sync`, `.kittify/charter/directives.yaml` shall contain a `DIR-003` entry whose `description` text conveys "no shims, no deprecation wrappers, remove bad architecture outright, no matter the cost". (Severity is hardcoded `warn` by Spec Kitty 3.1.6's extractor; see research.md Q1. The directive's *text* is the binding policy, not its severity.) | Draft |
| FR-004 | The compact charter context emitted by `spec-kitty charter context --action <any> --json` shall list `DIR-003` alongside `DIR-001` and `DIR-002` in the `Directives:` line of the `text` and `context` fields. | Draft |
| FR-005 | DIR-003 ships at the same `severity: warn` as existing directives (Spec Kitty 3.1.6 limitation, see research.md Q1). DIR-001 and DIR-002 are not modified. | Draft |
| FR-006 | The `Public-API removal policy is phase-dependent` clause's `beta entry and beyond` paragraph shall be relocated alongside DIR-003 so the alpha vs. post-alpha policy is co-located. The post-alpha rules are not changed. | Draft |
| FR-007 | The amendment shall update `CHANGELOG.md` with an entry describing the directive hoist (per DIR-001's CHANGELOG requirement for governance changes). | Draft |
| FR-008 | The amendment shall not modify `governance.yaml`, `metadata.yaml`, `references.yaml`, or `interview/` content. Only `charter.md` and the regenerated `directives.yaml` are affected. | Draft |

## Non-Functional Requirements

| ID | Requirement | Threshold | Status |
|---|---|---|---|
| NFR-001 | The amendment is reviewable as a single small diff | Combined diff ≤ 100 changed lines across `charter.md`, `directives.yaml`, and `CHANGELOG.md` | Draft |
| NFR-002 | The amendment introduces no churn elsewhere | No other repository files modified except those in NFR-001 plus this mission's spec/plan/tasks artifacts | Draft |
| NFR-003 | Charter sync round-trip is reproducible | Running `spec-kitty charter sync` twice in a row produces no diff after the first run | Draft |

## Constraints

| ID | Constraint | Status |
|---|---|---|
| C-001 | Amendment follows the documented Amendment Process: this mission's spec describes the change and rationale; the change ships through the standard implement / review / accept loop. | Active |
| C-002 | The amendment does not add new policy. It restructures existing policy text and assigns it a stable directive ID. The substantive rule already exists in DIR-001's sub-clauses. | Active |
| C-003 | `directives.yaml` is auto-generated; do not edit it directly. The mission edits `charter.md` and runs `spec-kitty charter sync` to regenerate. | Active |
| C-004 | The amendment does not relax any existing constraint. It only tightens visibility/severity. Per the Amendment Process, tightening amendments take effect on the next mission. | Active |
| C-005 | The amendment text in DIR-003 must be self-contained — readable in compact context without forcing the reader to also read DIR-001. Cross-references are allowed; load-bearing implication chains are not. | Active |

## Success Criteria

1. After merge, running `spec-kitty charter context --action specify --json` returns `text` containing `DIR-003` in the `Directives:` line.
2. A new agent starting a session can derive the greenfield removal policy from `directives.yaml` alone, without reading `charter.md` in full.
3. Subsequent missions in this repository (including the in-flight Single-Entity Work Surface mission) can reference `DIR-003` by ID in their constraints sections instead of restating the rule inline.
4. `spec-kitty charter sync` produces a clean, idempotent regeneration of `directives.yaml`.
5. No code in `packages/` is modified by this mission.

## Key Entities

- **`charter.md`** — primary edit target. Adds DIR-003, removes the greenfield sub-bullet from DIR-001 (replaced by cross-reference), relocates the phase-dependent removal-policy explanation alongside DIR-003.
- **`.kittify/charter/directives.yaml`** — regenerated artifact. Adds DIR-003 entry at `severity: error`.
- **`CHANGELOG.md`** — amendment entry under the next-release header.

## Assumptions

- `spec-kitty charter sync --force` is the canonical regeneration command for `directives.yaml` (verified in research.md).
- Severity is hardcoded `warn` for all directives in Spec Kitty 3.1.6 (research.md Q1). The directive's binding force comes from its description text, not its severity field.
- The current `severity: warn` on DIR-001 and DIR-002 is unchanged by this amendment.
- The author wants this rule prominent for *all* future automated agents, not just Claude. Memory/per-instance preferences are insufficient — charter is the right home.

## Dependencies

- **Spec Kitty CLI** with `charter sync` and `charter context` subcommands (already installed).
- **Existing charter** (`.kittify/charter/charter.md` and sibling files) as the substrate for the amendment.
- **In-flight mission**: `single-entity-work-surface-01KQ7M1P` is in flight on `main`. Per the Amendment Process, tightening amendments take effect on the next mission. The Single-Entity Work Surface mission's constraint C-010 was already corrected manually in its spec to align with the not-yet-hoisted policy; once DIR-003 lands, that constraint can be simplified to a one-line `See DIR-003` reference. That cleanup is in-scope for the Single-Entity Work Surface mission, not this one.

## Out of Scope

- Sweeping the existing codebase for `@deprecated` annotations or `Legacy*` symbols. (Future mission.)
- Changing `severity` on existing DIR-001 or DIR-002.
- Adding any new directives beyond DIR-003.
- Modifying the post-alpha (beta entry and beyond) deprecation policy. The amendment relocates that text but does not change its meaning.
- Updating the in-flight Single-Entity Work Surface spec to reference DIR-003 by ID. That cleanup is in-scope for that mission, not this one.
- Modifying `governance.yaml`, `metadata.yaml`, `references.yaml`, or interview artifacts.
