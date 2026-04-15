# Local Composer workflow

Use the committed `composer.json` for published dependencies and keep local package overrides in `composer.local.json`.

1. Clone your app beside the Waaseyaa monorepo so `../waaseyaa/packages/*` resolves from the app root.
2. Copy `composer.local.json.example` to `composer.local.json`.
3. Run `composer install` or `composer update` as usual.

The app loads `composer.local.json` through `wikimedia/composer-merge-plugin`, and `prepend-repositories: true` makes the local path repository win over Packagist during development.

Before you commit dependency changes, run `composer regen-lock`. That command disables plugins so Composer ignores `composer.local.json` and refreshes `composer.lock` against the published `waaseyaa/*` packages instead of leaking local path references.
