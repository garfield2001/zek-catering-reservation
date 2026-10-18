# Phase 1 — HTTP 419 reliability

Scope: Phase 1 of the owner's Application Reliability, Code Quality, Add-on UX & Development Seeders request. Baseline: clean committed `1afb8eb`. Phases 2–4 require separate owner review checkpoints and are not implemented here. Verification below is independent of the previous milestone.

## Confirmed diagnosis

The initial Chrome reproduction logged in, followed the Food Catalog Inertia link, and submitted a new Food Item without a document reload. The actual response was **419**, not a validation or authorization error. A separate control confirmed the session cookie remained authenticated: `/admin/home` returned 200, and a request using the current XSRF cookie reached normal validation (422). Sending the old HTML `X-CSRF-TOKEN` header alongside the current cookie header still produced 419. No token or cookie values were printed; diagnostic browser tracing is disabled.

`App\Actions\Auth\Login` correctly regenerates the session after authentication. The installed Laravel `Session\Store::regenerate()` also regenerates its CSRF token. Inertia navigation does not replace the initial Blade document. Five custom fetch submission paths continued reading the login document's meta token. Laravel's `VerifyCsrfToken::getTokenFromRequest()` prioritizes `_token`, then `X-CSRF-TOKEN`, over `X-XSRF-TOKEN`. Consequently even adding a fresh cookie header without removing a stale raw header would not solve this defect. Reloading the authenticated page rendered a new meta token and masked it.

Existing browser workflows generally called `page.goto()` immediately after login. Their successful results did not exercise this lifecycle. New tests use sidebar links and modal operations without that reload.

## Inspected lifecycle and configuration

- Login Action, SessionController, LoginRequest, login form, logout and session regeneration.
- `bootstrap/app.php`, web/auth routes, EnsureActiveSession, HandleInertiaRequests, Blade root, app entry point and Vite configuration.
- Session configuration and only nonsecret local/testing environment fields: database sessions, 120-minute lifetime, lax same-site, HTTP cookies allowed locally, no cached Laravel configuration. No credential or environment changes were necessary.
- Inertia forms/router submissions retain their existing request mechanism. Installed Axios uses the Laravel `XSRF-TOKEN` / `X-XSRF-TOKEN` convention. No dependency was added.
- All custom fetch sites: five mutation implementations shared the stale-meta pattern; remaining fetch sites load editor/details/impact data and do not mutate records.

The failure reproduced on a consistent origin with built production assets, so neither Vite nor a hostname mismatch was required to cause it. `localhost` and `127.0.0.1` still have separate cookie scopes: use one Laravel origin consistently, not the Vite asset URL as the application. This is an operational precaution, not a claimed additional diagnosed defect. Actual owner browser extensions, proxy behavior and wall-clock session expiry are not reproduced by this test environment.

## Correction and boundaries

`resources/js/lib/http.ts` supplies one small same-origin request helper for existing custom JSON/multipart calls. It reads and decodes Laravel's current XSRF cookie at submission time, removes conflicting raw/cached headers, retains same-origin credentials and requests JSON. It does not retry writes, reload pages, weaken middleware, add CSRF exemptions, set multipart boundaries, or introduce a second authentication system. The obsolete Blade meta token is removed.

Consumers: FoodItemForm, useJsonSave (service and listing modals), inline Measurement Unit creation, CompletePackageForm and PreviewBuilder. Existing payloads, field-error mapping, image uploads, saved callbacks, dirty guards and backend authorization remain. Expired/invalid sessions (401/419 or a login redirect) receive a clear retained-input message. Sign in on another tab of the same origin and explicitly resubmit; there is no automatic mutation retry. Network errors still do not claim success and require checking whether a save completed before retrying.

Authentication/session storage, commercial Actions, pricing, publication snapshots and schema are unchanged. There are **no new migrations** or ERD relationship changes. No development-database writes or Git mutations were performed.

## Evidence and test coverage

`session-writes.spec.ts` covers fresh-login Food Item and service creation, repeated Save & Add Another with image upload and category retention, logout/Inertia login and hard-reload saves, inline Food Item/category/unit creation and package save, invalidated cookie recovery in another tab, JSON session failure, and invalid-CSRF rejection. A read-only isolated-database assertion verifies exact record counts, not just navigation or success notifications. The expired-session test removes authentication cookies rather than mocking a successful response; the server rejects the real write and retains the form.

`http.test.ts` verifies request-time token rotation, stale-header removal, same-origin restriction, multipart preservation, no retry and session-error classification. The existing complete browser and PHP suites cover authorization, image validation, Main Catering, individual listings, package publication and immutable history.

The browser fixture setup and PHP base TestCase now verify `current_database()` and `inet_server_port()` before destructive test setup, in addition to configuration guards. The browser router verifies the actual connection too. Only `zek_catering_test` on PostgreSQL port 5432 is accepted. Upgrade harnesses already have actual-connection guards.

Verification on 2026-10-19: PHP/PostgreSQL **188 passed, 1,391 assertions** (62.58 seconds); Vitest **31 passed across 12 files**; TypeScript, ESLint, Prettier, Pint, Larastan, production build (3,483 modules), login/catalog route inspection and `git diff --check` passed. Seven focused Chrome scenarios passed. Complete Chrome regression: **31 passed, 0 failed** (4.9 minutes), including all 24 prior workflows and seven new lifecycle scenarios. Migration-upgrade and Draw.io checks were not rerun: not applicable to this phase because no schema, migration or ERD changes were made.

The isolated server had stopped before one run (connection refused). Playwright's Windows-owned PHP teardown also stalled after all seven focused tests passed; only the process verified as the isolated port-8011 test server was terminated, allowing the run to exit successfully. Full regression uses a separately managed, guarded test server to avoid that teardown problem. These environment incidents were not treated as successful application tests or redirected to development.

### Changed-file inventory

- `resources/js/lib/http.ts`: shared request-time CSRF transport and session failure classification.
- `resources/js/features/catalog/use-json-save.ts`: service/listing JSON saves use current session credentials.
- `resources/js/features/catalog/components/food-item-form.tsx`: multipart catalog/inline saves use current credentials; preserve input and field errors.
- `resources/js/features/catalog/components/inline-measurement-unit.tsx`: same transport and session feedback for nested creation.
- `resources/js/features/packages/components/complete-package-form.tsx`: atomic modal package saves use the transport, without payload/business-rule changes.
- `resources/js/features/packages/components/preview-builder.tsx`: preview requests use the transport and distinguish session failure.
- `resources/views/app.blade.php`: remove the stale meta-token source.
- `tests/TestCase.php`, `tests/Browser/seed.php`, `tests/Browser/router.php`: actual-connection safety verification.
- `resources/js/lib/http.test.ts`, `tests/Browser/session-writes.spec.ts`, `tests/Browser/catalog-record-count.php`: regression coverage and isolated read-only persistence assertions.
- This document: confirmed diagnosis, scope boundaries, verification and owner walkthrough.

## MANUAL TESTING READINESS

**READY FOR MILESTONE TESTING. Phase 1 commit recommendation: READY.** Technical implementation, individual save workflows and integrated package/history regressions are verified; owner acceptance and commercial-production readiness are separate. Admin can test the workflows below. Staff can test only assigned capabilities and server-side denials. No customer UI is included. No migrations, credentials changes, or session-store resets are required by Phase 1; start the usual development servers with the updated assets and use one consistent application hostname.

From a fresh browser login, navigate using the sidebar (do not reload), create a Food Item with an inline category and optional image, repeat Save & Add Another, and create an Add-on Service. Expect one persisted record per submission. Then test inline package setup, logout/login and another immediate save. Invalid sessions must show an error, not success; inputs remain while the original tab stays open. Signing in in another tab and explicitly resubmitting is supported. Input is not persisted across closing/reloading a tab, and genuine permission denial cannot be repaired by refreshing. Existing Inertia-managed forms retain their framework error behavior; the new retained-input recovery message applies to custom JSON/multipart submissions.

Only reliability is in scope. Service-level format ownership, broader maintainability refactoring and development demo seeders remain deferred to the later approved phases. No customer workflows or commercial-production certification are included.

Suggested owner-controlled commit: `fix(auth): prevent stale CSRF tokens in admin submissions`. Include request-time cookie transport across custom writes, clear expired-session feedback, removal of the stale Blade token, database safety guards, lifecycle/persistence regressions and this diagnosis record. Stop for owner review before Phase 2.
