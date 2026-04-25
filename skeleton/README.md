# Waaseyaa Application

A Waaseyaa CMS application.

[**Discord**](https://discord.gg/ZzQNhrBb7U) | [GitHub](https://github.com/waaseyaa/framework) | [Website](https://waaseyaa.org)

## New project

```bash
composer create-project waaseyaa/waaseyaa my-app --stability=dev
cd my-app
```

Use `./vendor/bin/waaseyaa` for the CLI. Optional path-linked `waaseyaa/*` checkouts: copy `composer.local.json.example` to `composer.local.json` (see [docs/local-dev.md](docs/local-dev.md)).

## Directory Structure

```
bin/
├── dev.sh               Local development runner (`composer run dev`)
├── post-create-setup.php  One-time setup after `create-project`
└── maintenance/         Audit/release helpers (optional for beginners)

src/
├── Access/        Authorization policies
├── Controller/    HTTP controllers (thin orchestration)
├── Domain/        Domain logic grouped by bounded context
├── Entity/        ORM entities (pure data models)
├── Ingestion/     Inbound data pipelines (files, email, APIs)
├── Provider/      Service providers (bootstrapping, DI, routing)
├── Search/        Search providers, autocomplete, indexing
├── Seed/          Seeders for dev/local bootstrap
└── Support/       Cross-cutting utilities (ValueObjects, helpers)
```

### Domain Rules

Bounded contexts go under `Domain/<ContextName>/` with optional subdirectories:
`Service/`, `ValueObject/`, `Workflow/`, `Assembler/`, `Ranker/`, `Mapper/`.

### Support Rules

Cross-cutting utilities (validators, slug generators, normalizers, distance
calculators) belong in `Support/`.

### Namespace Rules

Namespaces must match PSR-4 directory structure. Update namespaces in files
and all references when moving code.

## Commands

```bash
composer install                    # Install dependencies
composer run dev                    # Start backend (+ admin HMR when configured)
./vendor/bin/phpunit                # Run tests
./vendor/bin/waaseyaa optimize:manifest  # Rebuild provider manifest
./vendor/bin/waaseyaa serve              # Dev server
./vendor/bin/waaseyaa                    # CLI
./bin/maintenance/waaseyaa-audit-site    # Optional convergence preflight
```

## First 60 Seconds

```bash
composer install
composer run dev
```

`composer run dev` always starts the PHP app. If an admin Nuxt package is configured,
it also starts the admin dev server with hot reloading.

Open your app at `http://127.0.0.1:8080` (or your configured `APP_HOST` / `APP_PORT`).

## Optional: Admin HMR Setup

If your project has a Nuxt admin app outside this skeleton, point Waaseyaa to it:

```bash
export WAASEYAA_ADMIN_PATH=../waaseyaa/packages/admin
composer run dev
```

When `WAASEYAA_ADMIN_PATH` resolves to a directory containing `package.json`,
the dev command launches both backend and admin HMR together.

## Configuration

- `config/waaseyaa.php` — Framework configuration
- `config/entity-types.php` — Custom entity types
- `config/services.php` — Service overrides

## License

GPL-2.0-or-later
