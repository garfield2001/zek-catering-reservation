# Add-on selling configuration and Package Format

Historical ownership note: the later [Phase 3 service configuration specification](23-service-package-configuration.md) supersedes package-level format selection for new packages. This document remains the reference for retained version facts, legacy mixed-format compatibility and snapshot schema 5, not an alternative new-package workflow.

Status: implemented and verified on 2026-10-18, starting from committed checkpoint `18891b6`; ready for owner milestone review. The owner-approved refinement supersedes service-wide presentation assumptions. No development migration is authorized. Automated verification is not owner acceptance or commercial-production certification.

## Approved business rules and reused capabilities

- Two ordinary service selling methods remain: Sold by Package and Sold Individually. Stable `package`, `packed_meal`, `fixed_bundle`, `direct_sale` and `main_package` codes are not customer-facing names.
- Package Format belongs to a Package Version, not a Collection. One service may contain Separate Food Servings and Combined Contents packages. A format change never changes food counts, serving amounts, existing prices or fixed/category rules.
- Separate servings may have a common container count/presentation and optional per-food exceptions. Combined contents describe one complete purchased container; selected foods are components, not independently purchased containers. Serving measurements remain optional and independent. A kilogram upgrade never implies more trays.
- Reuse the menu engine, fixed inclusion intent, exact distinct-food counts, integer-centavo calculations, quantity restrictions, serving defaults, size choices and permission-checked atomic publication. New format configurations require uniform selections. Legacy split records retain their existing behavior; this milestone does not introduce split ordering.
- Main Catering stays per guest without presentation or food surcharges. Individual listings retain absolute option prices and their own selling units; they never use Package Format.
- Legacy fixed-bundle behavior still forbids substitutions and general food premiums. Format does not relax those rules. Newly created generic package services can use explicit fixed foods without adding another composition module.

## Routine technical decisions and compatibility

Migration `2026_10_18_000020_add_package_presentation.php` adds nullable `package_format`, `presentation_quantity`, `presentation_unit_id` to `package_versions`, plus nullable presentation quantity/unit overrides to `package_version_food_items`. Unit references reuse `measurement_units` with restrictive foreign keys and indexes. Presentation quantity is a positive integer container count, not a fractional food measurement. Combined Contents requires exactly one configured container; separate defaults may remain unspecified.

Database checks enforce known formats, paired quantity/unit fields and uniform selection for newly formatted configurations. Cross-table guards reject Main Catering/individual formats and food-container overrides outside Separate Food Servings. Existing immutable version/child guards continue to apply. Deferred override checks allow an atomic format/menu transition. Rollback refuses to discard configured version facts.

Migration `2026_10_18_000021_allow_configurable_listing_units.php` replaces only the old six-value selling-unit constraint. Individual listings accept a trimmed, nonempty descriptive label up to 30 characters, without control characters (for example box or serving basket). This is version-owned presentation text, never a behavior identifier or a new unit registry. Existing absolute option prices, quantity/lifecycle constraints, authorization, snapshots and clone behavior remain authoritative. The UI offers common suggestions without restricting valid custom labels. No existing rows or snapshots are rewritten. Rollback refuses while custom units exist rather than discarding them.

No existing rows are backfilled and no old snapshots are rewritten. A null format means not recorded, not an inferred format based on name or `per_pack`. Schema-5 snapshots freeze the format, default and exceptional presentations, including unit labels. Cloning preserves version ownership and configuration. Master-unit renames cannot alter a published snapshot. Changing a presentation does not change the legacy internal `price_basis`; it still denotes a price for one complete purchased unit, while the presentation contract supplies its accurate display label. This avoids rewriting old behavior, prices or selection policy merely to rename a box.

`PackagePresentation` validates active unit references in the existing save transaction, supplies editable inputs and frozen/effective read contracts. The menu price preview includes a top-level presentation contract and separate-serving presentation per food. For Combined Contents, food-level presentation is null: purchase quantity counts complete containers only. Commercial history compares format, default presentation and explicit food exceptions using recorded snapshots. Existing actor attribution is unchanged.

The preview also supplies `purchase_unit_label`, and quantity-validation errors use the configured presentation label and resolved minimum/increment/maximum. Published validation reads the frozen label. Retained endpoints with no presentation configuration keep their prior behavior, including the existing split-policy capability; the new format-aware editor supplies its format and permits only uniform selections. This milestone does not remove that compatibility path or implement new split ordering.

## Workflow and scope

The shared large modal retains Details, Menu, Pricing & Quantity, Review & Save. Add-on Details includes Package Format and presentation; Main Catering does not. Menu labels use Default Serving per Food and Serving & Additional Pricing. Existing safeguards prevent duplicate size/general premiums or silently replacing measurements. Format changes preserve conflicting presentation exceptions and explain how to resolve them explicitly. Dirty-state handling, inline food/unit creation, context retention, permissions and light-only styling remain.

Deferred: public pages, customer inquiries/accounts, split ordering UI for new formats, inventory/material consumption, equipment, Team & Access redesign, global Activity History redesign. No additional business policy is invented.

## Verification checkpoint

Acceptance evidence:

| Scenario | Backend / browser evidence |
| --- | --- |
| A–B: separate foods and kilogram upgrades without extra trays | `PackagePresentationTest`; `commercial-refinement.spec.ts` mixed serving workflow |
| C–D: complete meal packs and boxes with independent quantities | `PackagePresentationTest` combined-container pricing and renamed-unit tests; `package-formats.spec.ts`; `catalog.spec.ts` packed-service workflow |
| E: individual items, absolute sizes and custom units | `IndividualListingTest`; `commercial-catalog.spec.ts` grouped publication/revision/archive workflow |
| F: fixed inclusions | Combined-container backend/browser scenarios and existing fixed-bundle browser regression |
| G: immutable history, comparison and historical cloning | `PackagePresentationTest`; `package-formats.spec.ts`; both isolated upgrade harnesses |
| H: Main Catering and security regression | Main-format rejection test; full package/access/publication suites and browser lifecycle workflows |

PostgreSQL tests exercise paired/positive presentation quantities, combined-container count, restrictive references, deferred food-override constraints, immutable published parent/child facts and invalid individual-unit labels. Staff cannot change selling units without the pricing capability. Existing publication-concurrency and inactive/archive regressions remain part of the full suite.

Verification used guarded `zek_catering_test:5432` only. The new upgrade harness reconstructs the schema before migrations 000020/000021 and verifies old publication IDs/snapshots, unknown legacy format, cloning and a newly formatted publication. The broader representative upgrade harness separately verifies preserved package/listing history and custom-unit Draft conversion. Both passed through 22 migrations. No development schema or records were changed.

Executed results: PHP/PostgreSQL **188 passed, 1,391 assertions** (49.77 seconds); full installed-Chrome Playwright suite **24 passed, 0 failed** (4.4 minutes); Vitest **27 passed across 11 files**. Pint, Larastan, TypeScript, ESLint, Prettier, production build (3,482 modules), package route verification (32 routes), Draw.io XML validation (7 pages), and `git diff --check` passed. Earlier test-selector failures and formatting warnings were corrected and rerun. Windows blocked the PowerShell npm wrapper; the supported `npm.cmd` executable ran successfully without changing execution policy.

Desktop/mobile screenshots were inspected for combined presentation, individual listings and package details. Both OS color preferences retain the light-only interface. A small package-details link-spacing correction was made after the full browser run and checked with a focused browser rerun against the rebuilt assets.

## MANUAL TESTING READINESS

**READY FOR MILESTONE TESTING** on an isolated/current-schema installation. Admin can test all three selling configurations and integrated publication/revision/history workflows below. Staff can test permitted actions and denied pricing/publication/access paths; permissions are unchanged. No Customer UI is implemented, so customer rules are verified through backend tests, not a public reservation flow. Broader customer acceptance belongs to the separately authorized public-site/inquiry milestone. Two owner-controlled development migrations remain a setup dependency, not permission to run them. Legacy records without format facts remain explicitly unspecified; existing legacy split behavior is retained, not expanded. Owner acceptance and production deployment readiness remain separate decisions.

## Owner walkthrough

Use an isolated/current-schema installation and an authorized Admin account. Before using this code against the development installation, the owner must back up and explicitly apply the pending forward migration. No development migration or reset was performed here.

There are two pending forward migrations: 000020 (package presentation) and 000021 (configurable individual-listing units). Apply them together only after owner authorization; a code checkout alone does not update the development schema.

1. **Required preparation:** create active Food Categories and Food Items. Reuse existing foods; no new copy is needed for another service. Create descriptive Measurement Units only if needed, for example Tray, Kilogram, Meal Pack or Meal Box. Photos and descriptions are optional.
2. **Separate Food Servings:** create an Add-on Service using Sold by Package. Create a package; choose Separate Food Servings in Details. Optionally configure one Tray as the default presentation per included/selected food. In Menu, choose the actual food total, allowed foods, required categories and fixed inclusions. A serving default is optional. For a roast, optionally offer 3 kg included and 4/5 kg upgrades, with exactly one zero-extra included default. Do not repeat a separate included measurement or general premium for the same size-priced food. Presentation stays one Tray unless explicitly overridden. Enter the package-set price and purchase limits, review, then save a Draft or explicitly confirm publication.
3. **Combined Contents:** in the same service, create a separate package and choose Combined Contents. Configure one Meal Pack (or Meal Box). Choose the desired foods and count; use Always include where substitutions are not allowed. Individual portion measurements are optional under the secondary food-portion controls. Enter a price for the complete purchased container and a minimum such as 10. Review must describe 10 complete containers, each with the same configured combination, not one purchased container per constituent food. Pack/Box are configured labels, not new behaviors.
4. **Sold Individually:** create a service using Sold Individually. Create one Food Item listing with its selling unit, purchase limits and optional named sizes using absolute prices. Package Format must not appear. Review and publish through the existing listing lifecycle.
5. **History:** publish an add-on package, edit its format/presentation into a new Draft, and publish after review. Compare commercial changes and actor/time attribution. The older publication must retain its original facts. Create a Draft from older history; its format and presentation should be restored into the new Draft, not applied retroactively.
6. **Staff/security:** repeat only with explicitly assigned package management, pricing, publication and history capabilities. Restricted actions must remain unavailable server-side. Use disposable test records to check unit deactivation warnings; published snapshots stay frozen, but a Draft using an inactive unit cannot publish until corrected.

Main Catering remains per guest and must show no Add-on Package Format, packaging requirements or food upgrades. The public website, inquiry/reservation/quotation/payment flows, inventory and new split ordering remain deferred. Passing automation does not establish owner acceptance or commercial-production readiness.
