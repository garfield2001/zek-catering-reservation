# Phase 2 maintainability review

Date: 2026-10-19. Baseline: `1342bec` (`fix(auth): prevent stale CSRF tokens in admin submissions`). Scope: Phase 2 of Application Reliability, Code Quality, Add-on UX & Development Seeders. Phase 3 service-level formats and Phase 4 demo seeders are not implemented here.

## Method and boundaries

Repository-wide structural inventory and searches covered hand-maintained PHP, React/TypeScript/JavaScript, CSS, tests, routes, configuration, seeders, factories and migrations. Focused source inspection prioritized mixed responsibilities, request handling, large files, authorization/transaction boundaries and failure-path coverage. This is not a claim of exhaustive line-by-line correctness proof.

The initial inventory contained 158 application files, 113 JS/CSS files, 47 test files, two route files, ten configuration files, one seeder, one factory and 22 migrations. Vendor code, generated assets and dependency caches are excluded from size targets. No dependencies, routes, database schema, migration history or commercial policies changed. No development database operations were performed. PostgreSQL tests require both configured and actual connection identity to be `zek_catering_test` on `127.0.0.1:5432`; cached configuration is rejected.

## Prioritized findings and changes

| Priority / risk | Evidence and problem | Resolution and coverage |
| --- | --- | --- |
| High / medium interaction risk | `features/catalog/components/food-item-form.tsx` mixed rendering, multipart/Inertia transport, image reset, contextual callbacks and Save & Add Another state (345 lines). | Rendering is now 166 lines; `use-food-item-form.ts` (189 lines) owns the cohesive lifecycle. Existing CSRF transport, dirty-dialog protection, upload method spoofing, category retention and contextual callbacks remain. Browser regression covers these interactions. |
| High / medium commercial-wire risk | `features/packages/components/complete-package-form.tsx` combined a 759-line wizard, payload conversion and review rendering. | Extracted `package-form-data.ts` (77 lines) and `components/package-review.tsx` (142 lines). Parent retains state, inline creation, submission and explicit publication confirmation. Three new payload tests cover filtering, IDs/revision, optional values, immutable inputs, bundle semantics and Main Catering restrictions. |
| Medium / low risk | Three write handlers duplicated unchecked Laravel validation-error conversion. | Shared `lib/validation-errors.ts` accepts unknown envelopes, preserves dotted keys and safely ignores malformed values. Used by Food Item, JSON save and package submission. Three new unit tests cover malformed envelopes/entries and valid string/array errors. |
| Medium / low risk, confirmed failure | `LoginRequest` and `SaveUserRequest` coerced arbitrary email inputs into strings before validation. An array produced HTTP 500 instead of 422. | Normalize only strings, and require the string validation rule. `RequestNormalizationTest` adds three PostgreSQL feature tests covering malformed login/account inputs, no unintended creation/authentication, and valid case/whitespace normalization. The defect was reproduced before correction. |
| Medium / low risk | Permanent maintainability rules needed to apply beyond one component. | Added standards to `AGENTS.md`: responsibility boundaries, type safety, advisory size reviews, preservation requirements, reuse of existing transport and explicit exceptions. No rigid file-length gate or new tooling. |

Frontend paths in this table are relative to `resources/js`; backend requests are under `app/Http/Requests`.

## Architectural observations and deliberate exceptions

- Controllers remain thin: reviewed larger controllers include PublicationController and DishController (98 lines each), and ProductController (90). Meaningful workflows remain Actions; trivial HTTP validation does not require a new abstraction solely for uniformity.
- SaveCompletePackage retains transactional boundaries and publication coordination. Laravel remains authoritative for pricing, quantity and permission validation. MinorMoney retains integer-centavo/overflow rules; frontend money utilities format rather than replace backend calculation.
- PackageMenuConfiguration (223), SaveSelectionGroup (216), PackageSelectionValidator (166), PackageFoodSizes (164), ConfigurationFeasibility (151), PackagePresentation (109), and SaveIndividualListing (106) are cohesive domain units. Draft configuration and frozen publication validation are intentionally distinct lifecycle concerns, not mechanically deduplicated.
- PackageDirectory eager-loads relevant versions and limits frozen presentation projections. No obvious directory N+1 was identified during this review; this is not a load-test result.
- `useLiveFilters` preserves context and cancels stale requests; editor loaders use abort handling; image uploads release object URLs. No replacement global state or generic repository layer is warranted.
- CSS (89 lines) remains a cohesive light-only base. Admin layout (289) and catalog workspace (299) coordinate navigation/focus and filters/modals respectively; splitting these solely by line count would obscure ownership.
- CompletePackageForm remains **575 lines**. Payload conversion and review rendering now have separate tested boundaries, but wizard state, inline creation and submission still form a substantial orchestration component. Retain this reviewed exception rather than scatter coupled state. Reassess alongside the separately authorized Phase 3 builder UX changes.
- MenuFields (325), PreviewBuilder (319) and FoodSizeFields (317) are next medium-priority complexity candidates. Preserve legacy split-selection behavior and numeric/named-size semantics. Phase 2 does not redesign these business interfaces.
- Small repeated read-only modal loaders have different response/lifecycle contracts. A generic hook is a low-priority candidate only if a genuinely shared contract emerges.
- Scenario-rich tests intentionally exceed advisory limits: PackageDraftTest (648), commercial-refinement browser suite (587), MasterCatalogTest (438), PackagePublicationTest (385), IndividualListingTest (382), AccessFoundationTest (374), session-writes browser suite (324), catalog browser suite (319). Preserve readable end-to-end scenarios rather than split arbitrarily.
- PostgreSQL constraint migrations remain cohesive integrity units. Seeders retain bootstrap responsibilities; demo data remains Phase 4. No schema changes means no ERD change is required.
- Existing strict TypeScript, React Hooks lint rules, Larastan level 5 and formatter tooling are reused. Browser test TypeScript is exercised by Playwright but is not included in the application's TypeScript project; broader test type-check configuration is a deferred tooling improvement.

## Verification

Fresh executions for this working tree:

| Check | Result |
| --- | --- |
| `php artisan test --compact` | 191 passed, 1,430 assertions, zero failures; PostgreSQL isolated test database |
| `npm.cmd test` | 37 passed in 14 files, zero failures |
| `npm.cmd run typecheck` | Passed |
| `npm.cmd run lint` | Passed |
| `npm.cmd run format:check` | Passed |
| `php vendor/bin/phpstan analyse --no-progress` | Passed, no errors |
| `php vendor/bin/pint --test` | Passed |
| `npm.cmd run build` | Passed, 3,487 modules |
| Complete Chrome/Playwright suite | 31 passed, zero failures or skips, 4.4 minutes; installed Chrome and guarded testing server |
| `php artisan route:list --except-vendor --json` | Passed; 93 application routes enumerated, no route changes |
| `git diff --check` | Passed, including final documentation |
| Isolated migration upgrade / Draw.io validation | Not rerun: no migrations, schema or ERD changes |

The browser server uses the existing testing router, refuses unsafe configuration, verifies actual PostgreSQL identity before handling application requests, and serves built assets independently of any running Vite server. Database-mutating suites run sequentially. No credentials are printed or changed. No mutations are automatically retried.

## MANUAL TESTING READINESS

**READY FOR MILESTONE TESTING.** Technical implementation and the affected individual/integrated Admin workflows passed regression verification. Staff authorization rejection is covered. No customer workflow is supplied or testable in this phase. Technical checks alone do not establish owner acceptance or commercial-production readiness.

Owner review should exercise the existing Admin Food Catalog and Package Builder in an appropriate test environment:

1. Log in and immediately create a Food Item with an image; expect one saved record without refresh.
2. Use Save & Add Another repeatedly; expect cleared food/image fields and retained category. Edit an existing item and verify its image handling.
3. Start a package Draft and create a food/category/unit inline; expect parent inputs and modal context to remain intact.
4. Configure existing selectable/fixed foods, sizes and presentation; review and save. Reopen to verify persisted values. Publication still requires explicit confirmation and backend validation.
5. Review Published history and clone into a Draft; expect the existing publication unchanged.
6. Repeat relevant permitted operations as Staff; denied operations must remain rejected. Invalid/expired sessions must not produce writes or misleading success, and unsaved input should remain available.

No owner migration or configuration action is required. Customer workflows, service-owned format redesign and demo seeders are not delivered by this phase. Stop for owner review before Phase 3.

## Checkpoint recommendation

**READY for the Phase 2 maintainability milestone commit**, subject to owner review. No blocking failures remain. Large-file exceptions and deferred tooling improvements above are deliberate, nonblocking follow-up candidates rather than claims that every file now meets a size target.

Suggested subject: `refactor(admin): separate form workflows and package review concerns`

Suggested body:

- Extract the Food Item form lifecycle while preserving uploads, contextual saves, category retention and session handling.
- Separate typed package payload conversion and review rendering from wizard orchestration without changing commercial rules or publication contracts.
- Share defensive Laravel validation-error mapping across custom writes.
- Reject malformed login/account email types without server errors while retaining valid normalization.
- Add six frontend unit tests and three PostgreSQL feature tests; document repository-wide maintainability standards and intentional exceptions.
- Verify 191 PHP tests (1,430 assertions), 37 Vitest tests and 31 Chrome browser tests, plus static analysis, formatting, type checks and production build.

No Git staging, commits or history changes were performed. The checkpoint contains no database migration or development-credential change.
