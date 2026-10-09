# Phase 1 — Application and Access Foundation

Authorized by the client after approval of V3. Phase 1 supersedes the older documentation-only gate for this phase only. Phases 2–7 remain unimplemented. No catalog/package/add-on/customer/reservation/quotation/payment/notification code is included.

## Implementation choices

- Laravel 12 is compatible with the available PHP 8.2 runtime. Use a current patched PHP release for commercial deployment; the inspected local PHP 8.2.12 installation is old. Laravel 12 support and upgrade planning must be reviewed before launch.
- Inertia 2, React 19, strict TypeScript, Tailwind 4, Vite 8 and a feature-first UI. Dependency lockfiles define the reproducible versions. Node 24 is used locally/CI.
- Native Laravel session authentication with database sessions; no public registration, remember-me UI, password-reset email, or notification flows. Passwords use Laravel's hashed cast; account creation/replacement requires 12 characters, mixed case and a number.
- Login uses generic failure messages, account/IP throttling plus route throttling, session ID rotation and active-account checks. Standard web middleware enforces CSRF. Logout invalidates the session and rotates its token.
- Database-backed roles/permissions with per-user allow/deny overrides. A request-scoped resolver centralizes precedence. Policies govern target-specific operations. No global Gate Admin shortcut bypasses invariants.
- Admin has full permission access. Staff starts with its assigned dashboard.view role grant only. Other Phase 1 capabilities are staff.view, staff.manage, access.manage and audit.view. Publishing capabilities are deliberately absent until that feature exists.
- Overrides apply only to Staff. Deny beats explicit allow/role grants. Resetting an override to inherit removes it. Access administration is absent from default Staff grants. Admin can delegate it; delegated Staff cannot modify Admin, themselves, more privileged accounts (including inactive ones), or capabilities they lack. Staff cannot create an account whose baseline role grants exceed their own.
- No role-editor abstraction is added: roles are fixed Admin/Staff; user overrides configure Staff access. The role_permissions junction supports the documented role baseline. Future baseline-edit workflows must revoke affected sessions and audit changes.
- Staff records use optimistic access_version checks. All access mutations serialize on ordered role row locks, reauthorize fresh actors/targets, preserve the last active Admin, increment the target's version and remove database sessions. The next request rejects any surviving old-version session.
- Account mutation and audit insertion commit together. PostgreSQL prevents UPDATE, DELETE and TRUNCATE of audit_events. JSONB stores explicitly selected, recursively redacted before/after data; audit subjects are historical locators. Login/logout are audited; failures/denials/revoked sessions are security log events with request correlation.
- Bootstrap the first Admin through an interactive command; never seed production passwords. Subsequent accounts are managed through authenticated administration.
- Query classes are used for the staff directory; simple audit pagination stays a small controller query. Actions coordinate multi-step authentication/access workflows; no generic repository or one-service-per-CRUD layer.

## Migrations

1. 0001_01_01_000000_create_users_table.php — users, case-insensitive email uniqueness, positive access version, database sessions.
2. 0001_01_01_000001_create_cache_table.php — Laravel cache and cache locks (shared login throttling).
3. 0001_01_01_000002_create_jobs_table.php — standard Laravel job infrastructure only; no notification jobs.
4. 2026_10_09_000001_create_access_tables.php — roles, permissions, user_roles, role_permissions, user_permission_overrides, FKs, composite keys, effect CHECK.
5. 2026_10_09_000002_create_audit_events_table.php — indexed JSONB audit records and append-only trigger/function.

The audit function uses CREATE OR REPLACE so PostgreSQL test refreshes, which drop tables but retain standalone functions, remain repeatable. Rollback drops the audit table before its trigger function.

## Local environment and verification

See the repository README for commands. Local .env and .env.testing are ignored, use generated application keys, and never contain committed credentials. Tests require PostgreSQL and a database ending in _test; the guard executes before refresh traits can migrate. PHPUnit pins the test database name to zek_catering_test. Browser fixtures also refuse a non-test database and generate ephemeral random passwords in memory.

The implementation session used an isolated PostgreSQL 18 cluster at .local/postgres on loopback port 55432, with databases zek_catering and zek_catering_test. It does not modify the pre-existing server on 5432. This disposable local cluster uses trust authentication and must never be exposed or reused as production configuration. .local also retains the initial generated frontend dependencies archived during a major tool upgrade; it is ignored.

Browser tests use a dedicated test server on 8011 and either installed Edge (PLAYWRIGHT_CHANNEL=msedge) or Playwright Chromium. Do not run browser fixtures simultaneously with PHPUnit's database refreshes. Fixtures remain in the disposable test database until the next refresh.

## Launch limitations and deferred decisions

Phase 1 is a tested foundation, not the complete commercial product. Production still needs HTTPS, APP_DEBUG=false, secure cookies, a patched supported PHP runtime, separate migration/runtime database privileges, restricted log access, backup/restore checks, and the already documented recovery/MFA/retention/hosting decisions. Runtime credentials must not own the schema or have permission to disable audit triggers. Account recovery is intentionally not implemented without the agreed procedure.

No first Admin password is chosen automatically. The owner must run app:create-admin interactively. Production customer data and commercial catalog configurations are outside this phase.
