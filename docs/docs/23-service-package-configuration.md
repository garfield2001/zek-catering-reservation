# Phase 3: service-owned package configuration

Status: implementation and verification complete, 2026-10-20. Starting checkpoint: `7127d6f`. This phase belongs to the Application Reliability, Code Quality, Add-on UX & Development Seeders master request. It does not authorize the original customer-facing Phase 5 or this request's Phase 4 demo seeders.

## Approved policy and architecture

The owner-approved Phase 3 rule supersedes version-only format selection in `20-addon-package-formats.md` for **new packages**. Services sold by package configure Separate Food Servings or Combined Contents and a default container once. Package Versions still store resolved facts for immutable publication and historical cloning.

The existing modular monolith, menu validation, integer-centavo pricing, exact distinct-food rules, fixed inclusion intent, category rules, numeric/named sizes, publication coordination, CSRF transport and permission system are reused. No parallel package/pricing engine, generic repository or dependency is added. Main Catering remains per guest without add-on format controls. Individually sold items keep absolute option prices and independent versions.

## Database safety and migration

A read-only PostgreSQL transaction inspected actual `zek_reservation:5432` on 2026-10-19. All 22 existing migrations were applied; no Add-on Services existed, so there was no local mixed-format conflict to resolve. The inspection transaction was rolled back. No development migration, data write, credential or permission change has been executed.

Forward migration `2026_10_19_000022_add_service_package_configuration.php` adds:

| Table | Added fields | Integrity |
| --- | --- | --- |
| product_families | package_format, default_presentation_quantity, default_presentation_unit_id | Nullable legacy state; otherwise known format, positive whole count, indexed restrictive unit FK, package-selling add-on context only; Combined Contents requires one complete container |
| products | service_format_owned boolean, default false | Identifies new configured packages versus legacy products; cannot change after creation |

No records are backfilled with inferred formats. Existing products remain legacy. Established non-null service format cannot change once any products exist; create a separate service for a different format. Default container edits affect subsequently created packages only.

New configured products copy service facts into their first Package Version. Database guards enforce the service-owned format and prohibit rewriting its recorded default container. Per-food presentation exceptions remain independently configurable under existing rules. Historical cloning preserves the source Version's facts, not today's service defaults. Legacy packages retain their existing explicit format controls, clearly labeled as legacy; this is an intentional compatibility path, not an alternative for new packages. Mixed legacy services remain unconfigured until an Administrator explicitly chooses their future settings.

Rollback refuses to discard configured service facts. PostgreSQL trigger functions use CREATE OR REPLACE to support repeated Laravel fresh test installation, which can retain functions after dropping tables. No old migration was rewritten. The editable Draw.io ERD and database entity definition include the new ownership distinction.

## Backend and UI implementation

- `ServicePackageConfiguration` validates format, whole container counts, active units, context and established-format restrictions inside the existing authorized/audited CreateCollection and UpdateFamilyPresentation transactions.
- Public creation Form Requests require package service configuration. Both public package-creation endpoints reject unconfigured services, and the editor explains the setup prerequisite. Existing internal Actions retain omitted configuration for legacy reconstruction/import fixtures; ordinary HTTP creation cannot use that compatibility boundary.
- `SaveProduct` resolves and validates configured defaults under the service row lock. `PackagePresentation` rejects forged format/default overrides. Existing pricing, uniform selection, publication and child integrity checks remain authoritative.
- Service forms offer the two selling methods, business explanations, format/default container fields, inline unit creation, dirty-state protection and contextual errors. An established format has an accessible explanation and safe next step. Legacy services can be renamed or disabled without inventing a format; configuring future packages is an explicit choice.
- New package Details shows inherited format/default container read-only. Existing versions and clones retain their recorded defaults after service edits.
- Menu presents optional package serving amounts as a secondary disclosure. Every food has a summary of container, serving amount and surcharge, followed by Customize Serving or Price. Numeric/named sizes, fixed amounts, optional surcharge and separate-food container exceptions reuse existing controls.
- Switching to sizes with existing measurements/premiums requires confirmation. Removing size/custom-serving configuration also requires confirmation. General surcharge and size pricing cannot double-charge; weight upgrades do not imply additional containers. Invalid hidden fields are expanded before browser validation focuses them.
- Container-unit deactivation warnings include affected services; new packages cannot inherit an inactive unit. Existing snapshots are not rewritten.

## Acceptance coverage

### Maintainability review

The new service fields component is 100 lines; food customization is 184 lines. The existing package wizard remains a reviewed 635-line orchestration exception, retaining its tested state, modal, transport and publication boundaries. This phase adds inherited presentation to its Details step without introducing a second editor. The previously extracted payload mapper and package review remain in use. No unrelated modules were reorganized.

| Requirement | Current evidence |
| --- | --- |
| Separate servings, different food counts in one service | ServicePackageInheritanceTest: 4- and 6-food packages; shared menu browser scenarios |
| 3/4/5 kg upgrades, no extra trays or duplicate charge | ServicePackageInheritanceTest and PackagePresentationTest; mixed-serving browser scenario |
| Complete packs/boxes, ten complete units rather than containers per food | ServicePackageInheritanceTest; service-owned combined-presentation browser workflow |
| Fixed-only and mixed menus, optional portions | Existing fixed-bundle/catalog browser tests and complete package/menu integration suites |
| No new package-level format override | Forged-request and PostgreSQL tests; browser absence of editable format controls |
| Service edits apply to future packages, not history | ServicePackageInheritanceTest; expanded service edit/publication/revision/clone browser workflow |
| Legacy mixed formats, old IDs/snapshots/cloning | ServicePackageConfigurationTest forward upgrade; both isolated upgrade harnesses |
| Main Catering and individual listings preserved | Full PHP suite; main publication lifecycle and grouped listing browser tests |
| Admin/Staff and session safety | ServicePackageConfigurationTest; browser denial and fresh/expired-session write suites |
| Desktop/mobile and light-only behavior | Browser overflow assertions and screenshots at 390px/1440px with both OS color preferences; manual screenshot inspection |

## Executed verification

| Check | Result |
| --- | --- |
| Complete PHP/PostgreSQL suite | 205 passed, 1,539 assertions, zero failures (55.37 seconds) |
| Focused service foundation/upgrade tests | Included in complete suite; prior focused run 9 passed / 50 assertions |
| Pre-presentation upgrade harness | Passed through 23 migrations; IDs and old snapshot retained, legacy null format retained, revision published |
| Representative Milestone B upgrade harness | Passed through 23 migrations; IDs/package snapshot retained, listing tables present, obsolete Variant tables absent |
| Vitest | 40 passed across 15 files |
| TypeScript, ESLint, Prettier | Passed |
| Larastan / Pint | Passed |
| Production build | Passed, 3,488 modules |
| Final complete Chrome suite | 31 passed, zero failures (6.3 minutes), using installed Chrome and the guarded router |
| Focused format/customization Chrome rerun | 2 passed: mixed food customization and service-default/revision/clone history |
| Draw.io XML / final diff and route checks | Passed: 8 editable Draw.io pages parsed, 97 application route-list lines, clean `git diff --check` |

All database-changing tests use guarded `zek_catering_test` on `127.0.0.1:5432`, checking both configured and actual connection identity. Database suites run sequentially. The browser uses installed Chrome, the existing guarded PHP test router and production assets, not a development/Vite server.

The first complete browser run had 30 passes and one timeout at an outdated region locator for the newly collapsed optional-serving section. The test now opens and targets the disclosure; the final complete rerun is pending. Earlier frontend tests were updated to explicitly confirm returning to default servings. These were not silently counted as passes.

## MANUAL TESTING READINESS

READY FOR MILESTONE TESTING. Automated success is not owner acceptance or commercial-production readiness. The Admin workflows below are the intended milestone review scope, not a customer-facing release. Staff can exercise only their assigned catalog/service/package/pricing/publishing/history capabilities. No Customer UI is delivered.

### Owner walkthrough

Use a current-schema **isolated test installation** and an authorized Administrator account. A code checkout does not update the owner's development schema. No development migration is authorized by this report.

1. **Required:** prepare active Food Categories and Food Items in Food Catalog, or use inline food/category creation in the Package Builder. Photos and descriptions are optional.
2. **Required:** create an Add-on Service, choose Sold by Package, then Separate Food Servings. Configure one Tray per included/selected food. Use an existing active unit or create one inline. Save the service.
3. **Required:** create a package. Details must show inherited format/container without another format selector. Name it and continue to Menu.
4. **Required:** select allowed foods and an exact food count; mark fixed foods where appropriate and configure required categories only when intended. Different packages can have different counts. Fixed-only packages need no artificial customer-choice group.
5. **Optional:** expand Optional food portions for a common portion amount. Otherwise leave it unconfigured. Ordinary foods already inherit the service's container.
6. **Optional:** expand Customize Serving or Price for a roast. Offer 3 kg included, 4 kg + PHP 600 and 5 kg + PHP 1,200. It still uses one Tray. Confirm any replacement of an existing separate amount/food surcharge. Use Presentation exception only when a different container/count is genuinely needed.
7. **Required:** set the complete package-set price and minimum/increment/optional maximum purchase quantity. Main Catering retains guest-based quantities instead.
8. **Required for publication:** review the actual food combination, amounts, presentation, size extras and purchase constraints. Save Draft to continue later, or explicitly confirm Publish after validation.
9. **Optional history review:** revise the package price, publish, inspect actor/time and before/after comparisons, then clone an older publication into a new Draft. Older snapshots and their prices/presentation must remain unchanged.
10. **Service default check:** edit the service's container default, then create another package. Only the new package receives the changed default. An established service format is locked; create a separate service for Combined Contents.
11. **Combined Contents check:** create a service with one complete Meal Pack or Meal Box. Configure its foods and per-complete-container price. A quantity of ten means ten complete containers, not ten containers per constituent food.
12. **Staff check:** repeat with appropriately assigned permissions. Missing service management, pricing or publication permission must remain rejected server-side. Existing individual-sale and Main Catering workflows must not show inappropriate add-on format controls.

Deferred: public website, inquiries, reservations, quotations, payments, inventory, new split-ordering UI, demo seeders, Team & Access redesign and global Activity History redesign. Legacy split configurations remain supported but new format-aware packages use uniform combinations. There is no production-readiness claim.

## Owner transition and Git

After owner review, the owner may separately authorize backing up and migrating development through migration 000022. Do not reset the database. Existing records require no destructive conversion; legacy services can be configured explicitly for future packages without changing history. Rollback protection is intentional.

No staging, commits, pushes, branches or history changes have been performed. The milestone is ready for the owner to review and, after acceptance, commit through GitHub Desktop.
