# 014 — Theme as a distributable composer package

**Status:** Accepted (2026-05-11)
**Mission:** Stability charter ratification
**Spec context:** `docs/specs/drupal-comparison-matrix.md` §6.5, §1.8

## Context

Apps currently own their templates and CSS. Minoo's `public/css/minoo.css` and `templates/*.html.twig` are app-internal — there is no contract for distributing a Waaseyaa design system as a reusable artifact.

When a sister community wants to launch their own Waaseyaa app with Minoo's visual language, today's path is: fork Minoo's templates and CSS into their own repo, then drift from there. This is fine for one or two consumers; it does not scale.

Drupal's theme system is heavy (base themes, sub-themes, theme hooks, theme suggestions, libraries.yml, library overrides). The decision is whether to add a theme-package contract now, what shape it should take, and how it interacts with the apps-own-forms position from ADR 013.

## Options considered

### A. No theme packages

Apps fork to reskin. Simplest. Rejected: predictably needed once the second app launches.

### B. Drupal-style base/sub-theme inheritance

Themes declare parent themes; override files cascade. Powerful, but encourages a "theme everything" culture and a deep precedence stack that is hard to reason about. Rejected.

### C. Composer-installable theme packages with declarative precedence (CHOSEN)

A `waaseyaa-theme-*` composer package declares a templates dir, an assets dir, and a manifest. Precedence is fixed and shallow: theme override → app templates → framework defaults. No multi-level inheritance; a theme either ships a file or it doesn't.

## Decision

Themes ship as composer packages with type `waaseyaa-theme`. Stable surface:

### Package shape

```
my-theme/
├── composer.json          # type: waaseyaa-theme
├── theme.json             # manifest (name, description, target framework version)
├── templates/             # *.html.twig, mirror the app's templates/ layout
└── public/
    ├── css/               # CSS files exposed to consuming apps
    ├── js/                # JS files exposed to consuming apps
    └── assets/            # images, fonts, etc.
```

`composer.json` `extra.waaseyaa.theme` declares the theme:

```json
{
  "extra": {
    "waaseyaa": {
      "theme": {
        "id": "my-theme",
        "templates": "templates",
        "public": "public"
      }
    }
  }
}
```

### Precedence (shallow, fixed)

When the SSR layer resolves `events.html.twig`:

1. **Active theme templates** — `vendor/<theme>/templates/events.html.twig` if present.
2. **App templates** — `templates/events.html.twig` if present.
3. **Framework defaults** — `vendor/waaseyaa/<package>/templates/events.html.twig` if any package ships one.

First match wins. No further cascade. No template-extends-template-extends-template chains forced on the system (Twig's own `{% extends %}` still works inside a template — that's user-space).

### Asset publishing

Theme `public/` directories are published into the app's `public/themes/<theme-id>/` via a CLI command:

```
bin/waaseyaa theme:install <theme-id>
bin/waaseyaa theme:publish-assets
```

The app's `<link>` and `<script>` tags reference `/themes/<theme-id>/css/...`. Apps remain in control of what links they emit in their `base.html.twig`. The framework does not inject `<link>` tags.

### Active theme selection

Apps name an active theme via config (`config/waaseyaa.php`, key `theme.active`). One active theme per app. Switching themes is a config change plus a `theme:publish-assets` run.

### Multi-theme support (deferred)

Per-route or per-section theming (e.g. admin uses one theme, public uses another) is **not in this ADR**. If demand emerges, a follow-up ADR adds `theme.routes` mapping. v0.x ships single-active-theme only.

### What themes do NOT do

- Override controller logic. (Themes are presentation; controllers are app.)
- Override entity types, policies, or storage. (Themes do not touch the data layer.)
- Inject middleware or providers. (A theme is not a plugin; consumers wanting logic-bearing packages register service providers directly.)
- Inherit from other themes. (No parent-theme chain.)

### Theme as a stability surface

The package shape, manifest format, precedence rules, and the `theme:*` commands are stable surface under charter §2.1. Breaking changes follow §4.

## Consequences

- **Cross-community visual reuse becomes possible without forking.** Minoo could publish `waaseyaa-theme-minoo`; a sister community installs it and inherits the design system.
- **The current Minoo `templates/` + `public/css/minoo.css` could be extracted to a theme package now.** Low-cost; gives the second consumer a tested template.
- **No additional render-layer complexity.** Twig precedence is the only mechanism; no theme hooks, no `*_preprocess_*` functions, no libraries.yml parser.
- **Theme switching is a deploy-time operation, not a runtime one.** Apps that need per-user or per-section theming will need follow-up work.
- **Composer ecosystem gains a clear extension point.** Type `waaseyaa-theme` is searchable on Packagist.

## References

- Matrix: `docs/specs/drupal-comparison-matrix.md` §1.8, §6.5.
- Minoo `public/css/minoo.css` and `templates/` — candidates for extraction.
- Related ADRs: 013 (apps own forms; theme packages share that "templates over plugins" stance), 010 (storage is unaffected by theming).
