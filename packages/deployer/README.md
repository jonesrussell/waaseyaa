# waaseyaa/deployer

**Layer 6 — Interfaces**

Shared Deployer recipe for Waaseyaa applications.

Ships canonical `deploy.php` recipes that consumer apps `require` to get standardized atomic deploys, asset compilation, migration runs, and health-check probing. The recipe expects a Caddy + PHP-FPM + systemd target and integrates with `bin/waaseyaa db:init` for first-deploy database initialization.

Key files: `recipe/`, `deploy.php`.
