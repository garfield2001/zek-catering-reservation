# Final product configuration and business workspaces

Owner-approved amendment, 2026-10-13. This document supersedes conflicting earlier phase vocabulary, Variant architecture, manually created primary services, package quantity inheritance/floors and ordinary technical Selection Group editors. Milestone B now supersedes its image, Direct Sale and separate-save UI limitations; customer transactions remain unauthorized. See [current commercial catalog specification](18-milestone-b-commercial-catalog.md).

## Decisions and domain contract

- **Collection → Product → Package Version.** Packages are independent products. An add-on service owns its products; identical product names in different services are valid. Stable behavior identifiers, not editable names, select business rules.
- The ordinary primary section is **Catering Packages**. `EnsurePrimaryService` provisions/resolves a primary Collection idempotently with serialization and no numeric ID assumption, preserves a disabled existing primary and replaces only an archived primary. Users never need to create it.
- Each Version owns positive integer minimum/increment and optional maximum. Maximum must be at least minimum; valid purchases are minimum plus a nonnegative multiple of increment, within maximum. Initial draft values are 1 / 1 / no maximum, editable independently. Illustrative 100/150-guest requirements are not system floors.
- Copying quantity values from another package is a one-time copy submitted through the same validated pricing endpoint. No link or inherited rule survives. `family_order_defaults` remains retained catalog metadata for existing catalog contracts, but package creation, pricing, publication and selection do not inherit or enforce it. The ordinary defaults/floor editor is removed.
- Main Catering price is per pax in integer centavos, entered as pesos. Exactly N distinct foods includes fixed foods and required-category choices. One selected food in a required category is fixed; multiple selected foods require **at least one**, not exactly one. Optional-category foods can fill remaining selections. Zero allowed foods makes a category unavailable. Impossible requirements are rejected transactionally.
- Main Catering has no per-food measurements, premiums or servings. PostgreSQL and domain validation reject these fields; they are absent from its ordinary UI.
- Configurable add-ons use the same exact-food/category builder, allowing explicit fixed foods, optional included measurements and optional food price adjustments. A quantity of two is two complete sets, not two food selections. Kilograms do not imply guest capacity. Estimated people served is a separate optional Version field.
- Packed Meals reuse the compatible category/food composition rules, with price per pack, per-pack measurements/adjustments and uniform/split allocations. Each split allocation must satisfy the complete menu, and allocation quantities must sum to the purchase quantity. Product minimum/increment/maximum apply to the total. Identical split menus merge before any configured optional subgroup restrictions are checked.
- Fixed bundles derive selection count from all selected foods, mark all foods fixed and forbid substitutions/premiums. Their ordinary UI has no customer-choice controls.
- Advanced add-on Serving Option/Selection Group rules remain internal and regression-tested (including kg/tray choices, duplicate prevention and category quotas). They are deliberately distinct from canonical foods and package purchase units. A Version cannot mix these advanced rules with the shared menu; obsolete manual group pages are removed. An advanced-rule draft is not silently converted/lost by saving the new builder.

## Code and route organization

Thin controllers call authorized Actions; Form Requests validate HTTP shapes. `PackageWorkspace` resolves page data and permission-gates history and copy sources. `PackageMenuConfiguration` implements feasibility, fixed inclusions, selection and availability rules once for compatible behaviors. `MenuPricePreview` handles shared-menu prices and split totals with checked integer arithmetic; the established advanced serving-option calculator remains separately tested.

Canonical destinations:

| Business destination | Route |
|---|---|
| Food Catalog | `/admin/food-catalog` |
| Catering Packages | `/admin/catering-packages` |
| Catering workspace | `/admin/catering-packages/{product}` |
| Add-on Services | `/admin/add-on-services` |
| Service workspace | `/admin/add-on-services/{family}` |
| Add-on package workspace | `/admin/packages/products/{product}` |
| Shared saved menu | `PUT /admin/packages/products/{product}/versions/{version}/package-menu` |

Legacy identifiers such as `family_id`, `products`, `dish_id`, supported permission codes and historical audit events are intentional physical/wire boundaries, not instructions to display technical vocabulary. There is no Variant model/table/route. Generic package indexes redirect to canonical business destinations. Legacy package create/edit GETs redirect to the canonical modal/workspace destinations. Manual group GETs cannot render the removed editor.

React's package feature composes the setup navigator, shared menu form, food detail controls, independent pricing form, inline catalog dialogs and publication history. Pages compose these focused components. Draft setup has Details, Menu, Pricing & Quantity and Review & Publish stages. Save-before-switch warnings protect unsaved work; forms retain state during inline catalog redirects. Reusable forms use unique IDs so nested dialog labels and keyboard interaction remain correct.

Legacy catalog create/edit URLs redirect into those same authorized workspace dialogs; separate category/food edit and list pages were removed. Read-only catalog detail pages and contextual add-on Serving Option pages remain distinct, useful views.

Food/category creation uses the same Catalog Form Requests, Actions and Policies whether opened from Food Catalog or the package. The selected service is preselected for inline food eligibility. No inline capability grants additional Staff permissions.

## Publication, availability and security

Published snapshots contain product/service presentation, all commercial terms, independent quantity rules, capacity, category rules and per-food facts. Historical JSON is immutable. Publication locks Product then Version, uses serializable transactions/retries, and advances only the owning Product pointer. A repeated publication is idempotent; historical restore clones to a higher-numbered Draft. Child mutations lock their Version; reparenting and post-publication edits are rejected.

Master edits do not rewrite snapshots. New availability separately checks active/nonarchived service/product, canonical food/category activity and retained active eligibility. Frozen category facts are used for published selection rules. Batch catalog availability avoids one eligibility query per food.

Administrator retains supported permissions subject to last-Administrator and immutable-history rules. Staff is least-privileged. Menu editing, price changes, publishing, history, lifecycle and inline catalog operations are server-authorized separately. Hidden controls are not an authorization boundary. Development account ID 2 is untouched.

Future quotation/reservation modules can capture immutable Version ID/snapshot terms. Whether a pending inquiry refreshes or requires requotation remains a future explicit policy; this refactor does not implement it.

## Database and fresh-installation contract

See [exact migration strategy](14-clean-schema-migration.md). Baseline 000005 directly creates Products, Package Versions and generic menu/rule tables. There are no quantity-override columns. Menu food rows preserve explicit `always_include` intent separately from derived `is_fixed`, and add nullable positive decimal `included_quantity`, nullable controlled `included_unit`, and nonnegative `price_adjustment_minor`; measurements and premiums are optional for add-ons, prohibited for Catering and premium-free for legacy bundles. Milestone B permits explicit always_include for Main Catering as well. Capacity is a nullable positive integer.

000009 protects publication ownership, state, current pointer, immutable children and audit history. 000011 validates behavior, clone ownership, same-service options/foods, mutually exclusive configuration models and Main/bundle restrictions. No migration creates obsolete Variant objects and later drops/renames them.

The editable `catalog_v3.drawio` now includes Milestone B image metadata and individual listing tables. Historical lifecycle diagrams do not override current Product-owned Package Versions.

The owner reset development after the earlier migration report. Milestone B read-only inspection confirms the 12 baseline migrations and always_include column are present. Its forward migrations still require explicit owner authorization; no development data was modified.

## Direct Sale boundary

Milestone B completes individual listings under stable direct_sale services: independently priced versioned listings, immutable publication, optional sizes, availability and audit. A Direct Sale service still cannot create a package or access package menu semantics.

Individual listing administration is now implemented by Milestone B. Customer checkout, quotation and reservation integrations remain deferred.

## Coverage regression audit

The owner-reported earlier milestone had 98 PHP tests/655 assertions and eight browser tests. The intermediate refactor fell to 53 PHP tests/seven browser tests after removing package-specific coverage. Read-only comparison against HEAD identified these four affected suites:

| Suite | HEAD methods | Restored/current methods | Meaningful changes |
|---|---:|---:|---|
| PackageDraftTest | 20 | 19 | Restored money/overflow, eligibility, split meals, premiums, fixed inclusions, direct HTTP permission failures and SQL constraints. Variant-only rename replaced by independent Product/menu tests. Main surcharge/group cases now test advanced add-ons; Main equal-price rules have dedicated tests. |
| PackagePublicationTest | 17 | 17 | Restored snapshot freezing, permissions, archive, same-owner pointer/provenance, raw SQL immutability and independent Package publications. |
| DynamicCollectionTest | 11 | 8 | Obsolete empty-primary bootstrap and independent Variant expectations replaced by automatic-primary and independent-package coverage; canonical scoped navigation has dedicated tests. Rename behavior, fixed bundles, direct-sale rejection, role/behavior SQL checks and audit redaction retained. |
| SimultaneousPublicationTest | 1 | 1 | Restored competing real PostgreSQL publisher processes and idempotency. |

Additional suites cover primary bootstrap/workspace/security (8), Main Catering package menus (2), clean schema absence of Variant objects (1), scoped navigation (1), and final shared-menu/quantity/inline contracts (11). Existing access, permissions, audit, catalog constraints and presentation tests are retained. Assertions tied to old inherited floors were replaced with independent-rule assertions, not removed to bypass failures.

Browser coverage retains authentication/access, CSRF/no registration, first-run provisioning, Main/add-on isolation and persisted publication/history/archive. It now exercises inline category/food creation without losing unsaved selections, modal setup, configurable Bellychon-style measurements/capacity, packed menus, fixed bundles and independently copied quantity settings. Test credentials are ephemeral, never committed.

## Verification and owner acceptance

Consult the final execution report for exact results; historical counts above are a coverage comparison, not a claim about the latest run. PHP and browser test guards require uncached PostgreSQL `zek_catering_test:5432`. Browser global setup verifies the testing-only server header. Run destructive PHP/browser checks sequentially.

See [owner walkthrough](11-admin-staff-guide.md) for necessary versus optional setup and [README](../../README.md) for local commands. Passing technical checks is not owner acceptance, full customer testing or commercial production readiness. The owner must review the implemented UI on an isolated fresh baseline before their GitHub Desktop commit; development-data transition remains a separate authorization.
