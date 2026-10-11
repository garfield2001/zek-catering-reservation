# Zek Catering — Catalog and Package Administration

Laravel 12 + Inertia 2 + React 19/TypeScript + PostgreSQL + Tailwind 4. Phases 1–4 provide authentication, permission-based Admin/Staff administration, append-only audit history, master catalog administration, package drafts, price previews and package publication with immutable version snapshots. Direct-sale listings, customer reservations, quotations, payments and notifications are not implemented.

## Prerequisites

- PHP 8.2+ with pdo_pgsql, mbstring, XML/DOM, fileinfo, OpenSSL and standard Laravel extensions; Composer 2.
- Node 22.12+ (Node 24 recommended), npm and PostgreSQL (tested with 18).
- Separate development and test databases. Never point automated tests at production.

## First setup

1. Run `composer install` and `npm ci`.
2. Copy `.env.example` to `.env`. Set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD for your own PostgreSQL database. No credentials are committed.
3. Run `php artisan key:generate`.
4. Run `php artisan migrate --seed`. The seeder creates roles/permissions and five catalog families/defaults, not user credentials or commercial packages.
5. Run `php artisan app:create-admin` and supply the first Admin's name, email and password interactively.
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

Browser fixtures run `migrate:fresh` only after confirming that the configured database name ends in `_test`; they erase data in that dedicated test database. On this Windows installation, the Playwright-managed PHP server can hang during teardown. A tested workaround is to run a separate testing-only server on port 8011, set `PLAYWRIGHT_REUSE_SERVER=1`, and use `PLAYWRIGHT_CHANNEL=chrome` with locally installed Chrome. Stop that server after the tests.

For an installed Edge browser on Windows, use `$env:PLAYWRIGHT_CHANNEL='msedge'` before `npm.cmd run e2e`; no Chromium download is then needed. Browser tests use port 8011, seed random credentials into the test database and verify real CSRF enforcement. Run backend and browser database tests sequentially. If Playwright's managed Laravel server lingers during shutdown on Windows, start `php artisan serve --env=testing --host=127.0.0.1 --port=8011` in a separate terminal, set `$env:PLAYWRIGHT_REUSE_SERVER='1'`, run `npm.cmd run e2e`, then stop that server.

Formatting: `php vendor/bin/pint` and `npm run format`. CI runs PostgreSQL tests, migration rollback/reapply, static analysis, formatting, lint, type checks, component tests, production build and browser checks.

## Structure

```text
app/
  Actions/{Auth,Access,Catalog}/
  Console/Commands/CreateAdmin.php
  Http/{Controllers,Middleware,Requests}/
  Models/
  Policies/
  Queries/{Access,Catalog}/
  Services/{Access,Audit,Catalog}/
resources/js/
  pages/{auth,admin}/
  features/{auth,access,audit,catalog}/
  components/ui/
  layouts/
  types/
database/{migrations,seeders,factories}/
tests/{Feature,Browser}/
docs/
```

See [Phase 1 decisions and migrations](docs/docs/07-phase-1-foundation.md), [Phase 2 catalog decisions](docs/docs/08-phase-2-master-catalog.md) and the [V3 specification index](docs/README.md). Later phases remain unimplemented. Recovery/MFA policy, production hosting, retention and deployment hardening remain launch decisions.
