# Local Composer workflow

Use the committed `composer.json` and `composer.lock` for published installs. When you have a local Waaseyaa monorepo checkout and need symlinked `waaseyaa/*` packages for development, use `composer.local.json`.

1. Clone or place your app so `../waaseyaa/packages/*` exists relative to the app root (e.g. app in `~/dev/my-app` and monorepo in `~/dev/waaseyaa`).
2. Copy `composer.local.json.example` to `composer.local.json` (or add your own `repositories` and overrides).
3. Run `composer install` or `composer update` as usual.

The app loads `composer.local.json` through `wikimedia/composer-merge-plugin`, and `prepend-repositories: true` makes local path repositories win over Packagist during development.

Before you commit dependency changes, run `composer regen-lock`. That command disables plugins so Composer ignores `composer.local.json` and refreshes `composer.lock` against the published `waaseyaa/*` packages instead of leaking local path references.
