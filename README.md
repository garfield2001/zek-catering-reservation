# Zek Catering — Catalog and Package Administration

Laravel 12 + Inertia 2 + React 19/TypeScript + PostgreSQL + Tailwind 4. The application provides internal access control, append-only audit history, catalog/package/listing publication with immutable snapshots, and a public published-offering storefront with server-authoritative planning estimates. Customer accounts, inquiries, reservations, quotations, payments and notifications remain deferred.

## Prerequisites

- PHP 8.2+ with pdo_pgsql, mbstring, XML/DOM, fileinfo, OpenSSL and standard Laravel extensions; Composer 2.
- Node 22.12+ (Node 24 recommended), npm and PostgreSQL (tested with 18).
- Separate development and test databases. Never point automated tests at production.

## Current architecture and development safety

The current architecture is Collection → Product → Package Version. There is no Variant layer. Catering and add-ons use a shared category menu builder, independent package quantity rules, inline catalog dialogs and a guided setup workspace. `/` is the public customer storefront and `/login` remains a manually visited internal entry point; internal pages remain protected. Read [the public storefront boundary](docs/docs/28-phase-6-public-storefront.md), [the current specification](docs/docs/15-business-workspaces.md) and [owner walkthrough](docs/docs/11-admin-staff-guide.md).

The owner's development database is `zek_reservation`; never refresh or migrate it without separate approval. The consolidated baseline requires either a controlled export/conversion/import or an explicitly authorized reset. [Migration inventory and transition options](docs/docs/14-clean-schema-migration.md).

## First setup

1. Run `composer install` and `npm ci`.
2. Copy `.env.example` to `.env`. Set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD for your own PostgreSQL database. No credentials are committed.
3. Run `php artisan key:generate`.
4. For a fresh **local** database, run `php artisan migrate:fresh` and then `php artisan db:seed`. The local-only bootstrap creates roles, permissions, the primary Catering service, the `Admin Tester` development Administrator, 11 demo Food Categories, 42 demo Food Items, and four incomplete Main Catering Draft packages. Do not run either command against the owner's existing development database without separate authorization; see the transition decision below.
5. Sign in locally with `admin@gmail.com` / `adminpass123`, then change or deactivate this development account if it will remain in use. Outside the local environment, use `php artisan app:create-admin` to create the first Administrator interactively.
6. Start two terminals:

```sh
php artisan serve
```

```sh
npm run dev
```

Open http://localhost:8000. On Windows PowerShell with script execution restricted, use `npm.cmd` and `npx.cmd`. Alternatively run `npm run build` and serve Laravel without a Vite development server.

The owner's installed PostgreSQL uses port 5432. The development database and the dedicated `zek_catering_test` database must remain separate. Configure credentials locally in ignored environment files; no credentials are committed. Run the interactive bootstrap command if the first Administrator has not yet been provisioned.

## Access rules

Active Admins have full permission access, while immutable audit and last-active-Admin rules remain enforced. Staff inherits only dashboard.view initially. Assign individual Allow/Deny/Inherit overrides through **Team & access → Permissions**.

Permissions include dashboard, staff, access, audit, catalog and package capabilities. Package publishing, history and lifecycle permissions are separate from draft management and pricing, and are disabled for Staff by default. Explicit Staff deny wins over role grants. Delegated Staff cannot grant powers they lack or manage more privileged users. Account or permission changes revoke existing sessions. Existing users are deactivated instead of deleted.

The catalog manages Food Categories, one canonical Food Item per dish, Collection eligibility, Offerings and Serving Options. Inactive eligibility is retained for history; Offerings and Serving Options have no direct-sale price. See [Phase 4 publication](docs/docs/13-phase-4-package-publication.md) for the draft/version lifecycle, published snapshots and effective availability rules.

Measurement Units are managed contextually from the Food Catalog, Add-on Service form and Package Builder rather than through a standalone page. An active unit created through either **Add Measurement Unit** or **Manage Measurement Units** synchronizes with the current form without a reload. The local-only bootstrap and its production boundary are documented in [Measurement Units and Development Data](docs/docs/25-measurement-units-and-development-data.md).

## Checks

Copy `.env.testing.example` to `.env.testing`, configure PostgreSQL credentials and create the dedicated `zek_catering_test` database, then run:

```sh
php artisan key:generate --env=testing
composer test
composer lint
composer analyse
npm run format:check
npm run lint
npm run typecheck
npm test
npm run build
npx playwright install chromium
npm run e2e
```

Browser fixtures and PHP tests fail closed unless configuration is uncached and the connection is PostgreSQL `zek_catering_test:5432`. Fixtures erase ONLY that test database. Run PHP and browser tests sequentially.

Installed Chrome is supported: set `PLAYWRIGHT_CHANNEL=chrome`. On Windows where managed-server teardown lingers, use a separate terminal with the guarded router:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='pgsql'
$env:DB_DATABASE='zek_catering_test'
$env:DB_PORT='5432'
php -S 127.0.0.1:8013 -t public tests/Browser/router.php
```

Then in the test terminal:

```powershell
$env:PLAYWRIGHT_CHANNEL='chrome'
$env:PLAYWRIGHT_PORT='8013'
$env:PLAYWRIGHT_EXTERNAL_SERVER='1'
npm.cmd run e2e
```

Global setup verifies the router's test-database response header before executing tests. An ordinary development server is not accepted. Stop the testing server afterward. Use ignored local environment files for credentials; never put passwords in commands.

Formatting: `php vendor/bin/pint` and `npm run format`. CI runs PostgreSQL tests, migration rollback/reapply, static analysis, formatting, lint, type checks, component tests, production build and browser checks.

## Structure

```text
app/
  Actions/{Auth,Access,Catalog,Package}/
  Console/Commands/CreateAdmin.php
  Http/{Controllers,Middleware,Requests}/
  Models/
  Policies/
  Queries/{Access,Catalog,Package}/
  Services/{Access,Audit,Catalog,Package}/
resources/js/
  pages/{auth,admin,storefront}/
  features/{auth,access,audit,catalog,packages,storefront}/
  components/ui/
  layouts/
  types/
database/{migrations,seeders,factories}/
tests/{Feature,Browser}/
docs/
```

See [Phase 1 decisions and migrations](docs/docs/07-phase-1-foundation.md), [Phase 2 catalog decisions](docs/docs/08-phase-2-master-catalog.md) and the [V3 specification index](docs/README.md). Later phases remain unimplemented. Recovery/MFA policy, production hosting, retention and deployment hardening remain launch decisions.
