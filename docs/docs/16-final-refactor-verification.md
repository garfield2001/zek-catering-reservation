# Final product configuration refactor — verification and handoff

Verified locally on 2026-10-13. This report covers the current uncommitted refactor, not Phase 5 or production acceptance. Read it with [current business rules](15-business-workspaces.md), [migration inventory](14-clean-schema-migration.md) and [owner walkthrough](11-admin-staff-guide.md).

## Implemented architecture and workflows

| Area | Implemented result and evidence |
|---|---|
| Ownership | Collection → Product → Package Version. `Product`, `PackageVersion`, `PackageWorkspace` and the clean 000005 baseline replace the Variant layer. No Variant model/table/route remains. |
| Primary Catering | `EnsurePrimaryService` provisions/resolves one current primary under an advisory lock without numeric IDs. It preserves deliberate inactivity. The ordinary user starts at Catering Packages. |
| Guided creation | A name/description modal opens a workspace with Details, Menu, Pricing & Quantity, Review & Publish. `PackageDraftEditor` guards unsaved step/navigation changes. |
| Shared food menu | `PackageMenuForm`, `MenuFoodDetails`, `SavePackageMenuConfiguration` and `PackageMenuConfiguration` share allowed foods, required categories, exact distinct selections and feasibility across compatible behaviors. Required sole foods become fixed automatically. Explicit add-on intent is stored separately as `always_include`, so adding another allowed food does not incorrectly retain a derived fixed inclusion. |
| Main Catering | Exact N includes fixed foods and required-category choices. Multiple choices from one category are permitted; each required category needs at least one. No per-food quantities or premiums. Backend tests configure independent A/B/C packages with totals 4/5/6. |
| Configurable add-ons | Independently named service-scoped packages support fixed and selectable foods, optional per-unit quantities/units, independent per-set price and purchase rules, and separate estimated people served. A 3 kg inclusion in two purchased sets remains 3 kg per set, not three selections. |
| Packed Meals | Per-pack prices, independent purchase minimum/step/maximum, optional paid foods/measurements and uniform/split complete menus. Split totals must equal purchased quantity. Identical menus merge before existing optional subgroup constraints apply. |
| Fixed bundles | All selected foods are fixed; selection count is derived. No customer-choice or premium controls. |
| Quantity rules | Values belong to each Version. Copying another package creates independent values. Retained catalog defaults are not enforced on packages. Laravel validates positive integers, bounds and increments. Prices are integer centavos with overflow checks. |
| Catalog | One Food Catalog workspace manages categories, foods, activity and eligibility, with search/filtering. Inline dialogs reuse Catalog requests/actions/policies and preserve unsaved menus. Contextual preparation/Serving Option pages remain available for add-ons. |
| Publication | Immutable snapshots, Product-owned current pointers, ownership checks, optimistic Draft revision checks, transactional publication/concurrency protection and historical cloning remain enforced. New availability checks live eligibility/activity while validation uses frozen commercial/category facts. |
| Access and audit | Existing Admin/Staff Policies and permissions remain authoritative for direct HTTP requests, inline catalog writes, pricing, publication and lifecycle actions. No development account permissions changed. Last-Administrator and append-only audit regressions remain covered. |

## Direct Sale and other boundaries

Direct Sale service creation, scoped navigation, Food Item eligibility, preparations and Serving Options are implemented. Its UI explicitly states that independently priced individual listings, size/listing revisions, per-product purchase policies, listing publication/current pointers and listing availability need their dedicated milestone. It cannot enter the package builder.

Advanced Serving Option/Selection Group rules remain internal and tested, but their old manual editor is removed. Existing advanced-rule drafts cannot silently overwrite their configuration through the shared builder; conversion of imported advanced records requires a deliberate mapping. This is not a claim that every internal advanced option has a new UI control.

Customer inquiries, quotations, reservations, checkout, payments, notifications and media workflows remain deferred. Optional subgroup settings are supported internally; the ordinary packed-meal UI exposes uniform/split mode, not an advanced subgroup-rule editor.

## Removed/replaced paths

- Removed Variant models, requests, controller, lifecycle Actions and React pages/forms; replaced lifecycle responsibilities with Package Version classes.
- Removed manual Selection Group page, form, option-picker and rule-editors. Authorized legacy GET routes redirect into the workspace; Main Catering rejects group construction.
- Removed separate package product create/edit page; old URLs redirect to the canonical section/workspace. Creation is a context-scoped modal.
- Removed separate Food Category/Food Item index/edit pages and their obsolete browse queries. Legacy edit/create URLs open the same authorized Food Catalog dialogs. Read-only detail pages remain contextual views.
- Removed the ordinary Collection ordering-default/floor form and old generic Collection cards/pages. Retained stable permission, catalog and audit identifiers are documented compatibility boundaries.

See [migration inventory](14-clean-schema-migration.md) for every retained, rewritten, removed and created migration. The old incremental upgrade helpers were removed because the owner explicitly chose a clean unreleased baseline.

## Executed verification

All destructive database checks used uncached PostgreSQL **zek_catering_test on port 5432**. PHP guards verify the target before database refresh; browser fixtures do the same, and Playwright global setup rejects any server without the testing-only database header. Installed Chrome ran against the guarded PHP server on local HTTP port 8014; that is an HTTP port, not a PostgreSQL port. Credentials stayed in ignored local configuration.

| Check | Latest result |
|---|---|
| Full PHP/PostgreSQL suite | 117 passed; 793 assertions; 0 failed/skipped |
| Playwright, installed Chrome, one worker | 11 passed; 0 failed/skipped |
| Vitest | 3 passed in 2 files; 0 failed/skipped |
| Larastan | Passed; no errors |
| Pint --test | Passed |
| TypeScript --noEmit | Passed |
| ESLint | Passed |
| Prettier check | Passed |
| Frontend production build | Passed |
| Migration round trip | 12 rolled back, 12 reapplied, then explicit guarded migrate:fresh applied all 12 successfully, including 000011 |
| Fresh schema integration | CleanPackageSchemaTest passes: no Variant tables, columns, indexes, named constraints, triggers or functions; no quantity-override columns; final menu fields, current-pointer FK and one-active-Draft index present |
| Route verification | 76 application routes; 0 Variant routes; replacement redirects tested |
| Draw.io | Six-page XML parses; current catalog/package/access pages updated; deferred designs labeled separately |
| git diff --check | Passed |

Initial closure runs exposed a formatting issue and browser timing/label/redirect problems; those were corrected and the complete affected checks rerun. Final passes above supersede those failed intermediate runs. Remote GitHub Actions, other browsers, screen-reader acceptance, load testing and commercial-production verification were not run.

### Browser evidence inspected

The browser suite exercised catalog CRUD and serving sizes; first-run primary setup; service-scoped navigation and Staff denial; package modals; inline food/category creation preserving selections; independent quantity copying; configurable add-on measurements/capacity and publication; packed-meal configuration; fixed-bundle publication; Main publication, revision, disable and archive. Main lifecycle tests additionally query persisted PostgreSQL state, not just page navigation.

Local screenshots under ignored `test-results/` were visually inspected: `unified-food-catalog.png`, `main-configured-menu.png`, `packed-service-package.png`, `configurable-addon-review.png`, `main-publication-review.png`, and `main-revision-history.png`. These are regenerated evidence, not source assets to commit. Split price calculations and two-complete-set fulfillment semantics were verified through backend test interfaces; no deferred customer ordering UI was added.

### Coverage reconciliation

The owner-reported prior baseline was 98 PHP tests/655 assertions and eight browser tests. The intermediate refactor had fallen to 53 PHP tests/357 assertions and seven browser tests. The final suite has 117 PHP tests/793 assertions and 11 browser tests. Counts are not a substitute for coverage: restored Draft, publication, dynamic-service and simultaneous-publication suites retain permissions, malformed inputs, money overflow, availability, split meals, snapshot freezing, PostgreSQL constraints and real competing publisher processes. Variant/inherited-floor expectations were replaced with independent Product/Version/menu expectations, not used to preserve obsolete architecture. [Suite-by-suite comparison](15-business-workspaces.md#coverage-regression-audit).

## Development database boundary

Read-only transactions against `zek_reservation` found 12 migrations recorded in batch 1, one user, one Collection, 18 foods, one Product and two Versions (one Published, one Draft), with 36 menu-food rows. There are no Variant tables or Selection Groups. The database lacks `package_version_food_items.always_include`, despite the baseline migration already being recorded. No other active transaction was observed during inspection.

No development migration, reset, truncation, account edit or permission change was executed. Do not run the revised application against that incomplete schema and mistake the mismatch for a workflow defect. Ordinary migrate will not replay rewritten baselines.

Owner decision remains separate: **A**, preserve records through a verified export, controlled mapping into a fresh-baseline database and validation before switching; or **B**, explicitly authorize discarding/resetting development contents. Neither transition was performed. Historical snapshots and IDs must be preserved for A.

## MANUAL TESTING READINESS

**READY FOR MILESTONE TESTING — isolated fresh-baseline environment only.**

Technical implementation and integrated Admin package workflows passed local automated verification. This is not owner acceptance, full customer acceptance or commercial readiness. The existing development database is not yet ready for the revised code. Browser fixtures are disposable; the final migration round trip removed their ephemeral accounts and data. For an owner walkthrough, use the guarded isolated environment, seed supported roles/primary service and provision a first Administrator interactively; do not run destructive suites concurrently with the walkthrough.

Necessary owner path: Food Catalog → categories/eligible foods → Catering Packages → Create Package → Menu (exact count, allowed foods, required categories) → save → Pricing & Quantity → save → review/confirm publication. Expected result: a current immutable publication with matching history. Optional: inline catalog creation, maximum purchase, independent rule copying, later draft revision, historical restoration and availability controls. [Detailed walkthrough](11-admin-staff-guide.md).

Admin can test the full implemented setup/lifecycle. Staff can test assigned view/manage/price/publish/history permissions and server-side denial outside them. Customer selection semantics are currently backend-tested only; there is no customer reservation workflow to accept. Broader customer/commercial testing belongs after the separately approved transaction/listing milestones and deployment/security preparation, not an invented date.

## Commit recommendation

The repository milestone is technically ready for owner review and a GitHub Desktop commit after UI acceptance. Development-data transition is an explicit local deployment boundary, not permission to discard records. No remaining failing automated check is known. Modified, deleted and new source/test/specification files form the refactor; ignored environment credentials, browser output, caches and build artifacts must remain uncommitted. No Git staging/history mutations were performed.

Suggested subject:

`refactor(catalog): unify package configuration and admin workflows`

Suggested body:

- Replace the Variant layer with Collection → Product → Package Version ownership and a consolidated PostgreSQL fresh-install baseline.
- Preserve publication immutability, same-owner pointers, concurrency guards, audit history and existing Admin/Staff access protections.
- Add idempotent Catering setup, service-scoped package modals and a guided Details/Menu/Pricing/Review workspace.
- Share category-based food configuration across Catering, configurable add-ons, packed meals and fixed bundles with behavior-appropriate rules.
- Make package quantity policies independent, support one-time copying, and retain integer-centavo pricing and split-menu validation.
- Add optional add-on measurements/capacity and distinguish explicit fixed inclusions from automatically derived fixed foods.
- Consolidate Food Catalog dialogs and inline creation; remove competing legacy editors and technical group/Variant forms.
- Preserve Direct Sale preparation/navigation contracts while explicitly deferring listing publication and customer transactions.
- Synchronize architecture, entity definitions, owner guide, migration transition documentation and editable Draw.io ERD.
- Restore/extend regression coverage: 117 PHP tests/793 assertions, 11 Chrome workflows and 3 Vitest tests; pass static analysis, formatting, type checks, fresh migration round trip and production build.
- Document the untouched development database's schema mismatch and required owner-approved transition.
