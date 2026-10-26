# Catalog Acceptance Scenarios V3

Current approved refactor: [Business workspaces and package architecture](15-business-workspaces.md) supersedes conflicting historical Variant, manual primary setup, Main Catering surcharge/Serving Option, and empty-primary-installation descriptions below. The primary Catering service is automatically provisioned; add-on services remain dynamic. Catering and compatible add-ons use a shared category/food menu builder. Package quantities are independently owned, not inherited. Advanced Serving Option rules remain internal; their ordinary manual group editor is removed.
Phase 4 package publication implementation and its PostgreSQL verification status are tracked in [the Phase 4 architecture](13-phase-4-package-publication.md). The direct-sale, media and customer scenarios below remain future acceptance targets. Historical CAT-39/CAT-40 pointer and multiple-draft wording is superseded: one active Draft per Package Product, one current published Version per Package Product, and restoration creates a higher-numbered Draft rather than moving a pointer backward. Any remaining Variant wording in this historical matrix refers to the former schema only.

Additional Phase 4 acceptance cases: creating one Package Product generates one default Variant and Version 1 Draft atomically; optional variants publish independently; generated technical codes never require operator input; included items and category-constrained customer choices can be configured without mandatory rice/drink assumptions; incompatible overlapping groups block publication; peso strings convert exactly to integer centavos; stale group edits and stale publication fail without overwriting; an abandoned Draft is read-only and does not replace the current publication; archived Products/Variants retain immutable publication history; live catalog deactivation can make a published Variant unavailable and reactivation can recover it; Staff requires distinct draft, price, publish, history and lifecycle permissions. These cases require PostgreSQL integration and browser verification before milestone acceptance.
These scenarios are the approved acceptance target; some Phase 1-3 cases have automated coverage, while publication, direct listings, media and reservation cases remain future work. Each scenario needs appropriate backend coverage (unit for calculations, feature for authorization/routes, PostgreSQL integration for database/publication) and an equivalent admin workflow or actionable validation message where user-facing. Price previews are transient and never create quotations, orders or reservations.

| ID | Scenario | Expected |
|---|---|---|
| CAT-01 | Main Catering A, 150 guests, ₱280 per guest, no surcharge | ₱42,000; 149 rejected |
| CAT-02 | Main Catering A, rice fixed, drink select-one, 3 distinct freely chosen pork/chicken/beef dishes | allowed including 3 different pork dishes |
| CAT-03 | Main Catering selects same Chicken Adobo twice in one group, including through different serving options | rejected by canonical dish identity under default uniqueness |
| CAT-04 | Category-controlled mode requires 1 pork, 1 chicken, 1 beef | fail until all three satisfied |
| CAT-05 | Main catering +₱20 per guest premium | 150 pax -> +₱3,000 |
| CAT-06 | Belly Lechon A 3kg fixed, 2 trays, 1 set | default set price; kg cannot change |
| CAT-07 | Belly Lechon A 3kg selectable 5kg with +₱1,000 per set, 2 sets | adds ₱2,000; still A, still 2 dish slots per set |
| CAT-08 | Belly Lechon tray offerings each approximate 15 guests, lechon kg independent | no inaccurate 15 pax promise for lechon item |
| CAT-09 | Food Pack Meal A 10 packs, base ₱150, Coke +₱20/pack | ₱1,700 total |
| CAT-10 | Food Pack Meal A 11 packs with minimum 10 and increment 5 | rejected |
| CAT-11 | Food Pack split enabled: 20 plain, 10 Coke, base ₱150 | ₱4,700; sum group quantities 30 |
| CAT-12 | Food Tray: 2 Chicken Adobo trays at ₱1,200 + 1 Beef Caldereta at ₱1,800 | ₱4,200; no variant needed |
| CAT-13 | Food Tray navigation filters the Beef node with explicitly assigned listings | shows its assigned current published listings; culinary classification is unchanged |
| CAT-14 | Pica-Pica set ₱4,500, 2 sets, no selection changes | ₱9,000; fixed inclusion quantities double |
| CAT-15 | Dish exclusive to Food Pack | never eligible in Belly Lechon or catering groups |
| CAT-16 | Dish eligible for all contexts but explicitly denied in one group | not selectable in that group |
| CAT-17 | Published package edited | new draft created; old published immutable and still served until new publish |
| CAT-18 | Invalid group requires 3 dishes with only 2 available | publication rejected with actionable error |
| CAT-19 | Staff without pricing permission changes price by forged request | 403; no persisted change |
| CAT-20 | Staff without publish permission attempts publish | 403; audit on permitted admin action |
| CAT-21 | Dish tray has overridden image | use offering-specific photo; fallback dish then system placeholder |
| CAT-22 | Pricing sent from browser altered | server ignores client total and recomputes |
| CAT-23 | Package family default minimum modified | already published version stays at frozen effective rule |
| CAT-24 | Offering inactive after being published | new publication/selection rejects it; historical version retained; a package with infeasible remaining required choices is unavailable |
| CAT-25 | Admin creates category Food Tray > Chicken, orders nav items | labels display correctly; purchasing rules unaffected |
| CAT-26 | Fixed group, select-one, select-many each validated independently | no illegal option selection or bypass |
| CAT-27 | Browse add-ons | Belly Lechon, Food Pack, Food Tray and Pica-Pica are four independent families; Main Catering is separate; no reservation required |
| CAT-28 | Submit split allocations against a uniform Food Pack version | rejected; uniform is the default |
| CAT-29 | Split allocations reference different variants or different versions of the same variant | rejected; all allocations use the one current meal variant version |
| CAT-30 | One split allocation omits a required meal selection | rejected even when other allocations are valid |
| CAT-31 | Split quantities do not sum to the total, or include zero/fractions/negative values | rejected with allocation-specific errors |
| CAT-32 | Food Pack total 10, minimum 10, step 5, split 3 plain + 7 Coke at base ₱150 | allowed with default total-only rules; total ₱1,640 |
| CAT-33 | Food Pack total 11 split into individually plausible groups | rejected by total minimum/step rules |
| CAT-34 | All subgroup restriction fields null; later fixture enables subgroup minimum 5 and step 5 | null preserves total-only behavior; enabled rules reject 3+7 and accept 5+5 for total 10 without schema change |
| CAT-35 | Identical split allocations are submitted twice | normalized/merged before subgroup restrictions; total and premiums preserved |
| CAT-36 | Edit dish name/category or option size after publication | historical package labels, classification, fulfillment and prices remain frozen; next publication uses validated current facts |
| CAT-37 | Add newly eligible dishes/options after publication | old publication does not gain choices; clone and publish to include them |
| CAT-38 | Direct SQL updates/deletes published package/revision or child; inserts/reparents child into/from published group | database rejects each mutation; application gives an actionable error |
| CAT-39 | Set owner current pointer to another owner's publication or to a draft | database rejects; valid same-owner published pointer allowed |
| CAT-40 | Two editors save the same draft revision or publish against an outdated current pointer | stale request conflicts without overwriting; successful publication atomically commits snapshots, pointer and audit |
| CAT-41 | Deactivation races with publication | shared locking protocol yields a consistent outcome; later new selections always honor inactive state |
| CAT-42 | Food Tray draft changes price from ₱1,200 to ₱1,300 | public preview stays ₱1,200 until publish; then ₱1,300; old revision immutable |
| CAT-43 | Browser submits a tray price or attempts to use a noncurrent revision publicly | price recomputed from current published revision; stale revision rejected with refresh guidance |
| CAT-44 | Try a listing from a non-Food-Tray family or cross-family group option | reject family mismatch before publication; wrong eligibility pair cannot be persisted |
| CAT-45 | Main Catering variant tries minimum 100 while enforced family floor is 150 | publication rejected; Admin floor changes audited; old published effective values retained |
| CAT-46 | Negative premium, fractional centavos or overflowing calculated total | rejected; no float rounding; decimal-string response retains exact large integer values |
| CAT-47 | Select a 5kg lechon upgrade with ₱1,000 premium for 2 sets | premium is ₱2,000, not multiplied by kg; fulfillment is 10kg plus separately scaled trays |
| CAT-48 | Restricted group quotas, allow whitelist and deny rule conflict | denial wins; allow cannot bypass family/category restrictions; free mode permits distinct same-category dishes |
| CAT-49 | Three option rows represent only two canonical dishes for a unique group requiring three | publication rejected as infeasible |
| CAT-50 | unique_dishes=false and selected occurrence count is 2 | count contributes twice to quotas/premiums and scales fulfillment twice; nonpositive counts rejected |
| CAT-51 | Fixed group has multiple options, required select-one lacks a default, or defaults exceed quota | publication rejected; incomplete select-many defaults allowed but final selection must satisfy minimum |
| CAT-52 | Pica-Pica draft adds selectable inclusion or nonzero option premium | publication rejected; Admin changes fixed contents/base price through a new draft |
| CAT-53 | Staff without price permission changes nested premiums/listing prices or publishes a draft with price changes | 403 with no mutation; publication cannot bypass price permission |
| CAT-54 | Active Admin, inactive Admin, Staff override and role permissions evaluated | active Admin allowed within business invariants; inactive Admin denied; Staff deny beats grants; no matching permission denies |
| CAT-55 | Default Staff publishes or administers permissions | denied; Admin can explicitly delegate; Staff cannot escalate beyond own authority or assign Admin |
| CAT-56 | Disable last active Admin or use a revoked account session | last-Admin mutation rejected transactionally; revoked session denied |
| CAT-57 | Successful change rolls back or audit update/delete is attempted | rolled-back mutation leaves no successful audit; persisted audit is append-only and excludes secrets |
| CAT-58 | Category reparenting forms a cycle or hidden ancestor contains visible child | cycle rejected including concurrent edits; hidden subtree excluded from browsing without changing commercial rules |
| CAT-59 | Fake image extension, SVG, excessive dimensions/size or failed processing | upload rejected or marked failed; only ready safe raster derivatives appear publicly |
| CAT-60 | Attach two primary images, reorder images, or remove asset referenced by another owner | duplicate primary rejected; reorder transaction preserves uniqueness; referenced asset not deleted |
| CAT-61 | Asset attachment races with deletion, or deletion job retries | shared asset lock/deleting state prevents broken references; cleanup is idempotent |
| CAT-62 | Offering has no ready image; variant lacks image | offering falls back to dish then placeholder; variant to product then placeholder; publishing without photos allowed |
| CAT-63 | Delete referenced master data or reparent a published serving option | rejected; archival preserves history and current availability rules |
| CAT-64 | No selected public publication, withdrawn owner or inactive eligibility/family/dish/option/category | new selection unavailable; authorized historical inspection still possible |
| CAT-65 | Serving claims from multiple distinct tray components are displayed | no summed guest-capacity claim; kg has no implied pax conversion |
| CAT-66 | Rename Food Pack Lunch to Packed Meals and Main Catering to Catering Packages | codes, primary/add-on identity, eligibility, quantity units, prices and validations remain unchanged; messages use current display names and configured limits |
| CAT-67 | Create a fifth Main Catering variant and rename an existing variant | no A-D cap or identity change; each variant retains its own groups, quantity rules and pricing; display names are unique per product without case/space ambiguity |
| CAT-68 | Staff attempts to edit family presentation without `family.manage`; Admin changes name, order or active state | unauthorized direct requests fail; permitted changes are audited; inactive family cannot enter a new package preview |
| CAT-66 | Configure inherited maximum versus explicit unbounded override | inherited maximum follows defaults for drafts; explicit null override removes maximum; published effective rule remains frozen |
| CAT-67 | Query addon/catalog listing pages with many rows | pagination and eager loading prevent per-row query growth; unpublished values and internal audit data absent from public props |
| CAT-68 | Disable a permission or account during active session | next authorized request uses fresh access version; stale cached grants cannot authorize writes |
| CAT-69 | Clean PostgreSQL migration, rollback of unpublished scaffold, backup restore and media recovery | verified in disposable/staging environments before launch; never use destructive reset on production data |

## Test data policy
ALL prices and serving claims in these scenarios, including 280/300/320/340 main catering, hypothetical 5000 Lechon, +20 Coke and 4500 Pica-Pica, are deterministic test examples until explicitly approved in the production configuration matrix. The Main Catering minimum of 150 is the approved default, not a test-only assumption. Use factories and clearly labeled demo fixtures; do not silently seed example prices as production publications. Mock storage and prohibit external network calls in automated tests. Run DB constraints/triggers/concurrency tests against PostgreSQL, not SQLite. Concurrency tests use independent connections and synchronized transactions.

## Phase 3 acceptance scope and clarifications

Phase 3 implements **draft-only administrator configuration and price previews**. Tests exercise fixed rice, required drinks, distinct dish choices, category quotas, static/dynamic lechon, tray choices, Food Pack uniform/split allocations, Pica-Pica fixed inclusions, current availability, permission denial, centavo overflow and PostgreSQL integrity. The Food Pack family default is 10 packs under the later explicit Phase 3 instruction; examples may use variant overrides. Tests requiring publication, immutable historical snapshots, public availability, direct tray listings, customer selections or reservations remain Phase 4 or later and must not be counted as Phase 3 passes. The actual implementation coverage is recorded in 09-phase-3-package-drafts.md and `tests/Feature/PackageDraftTest.php`.
# Phase 3.5 acceptance amendment (2026-10-10)

- A clean migration and production seed create roles/permissions but no collection, food item, offering, package or commercial price. The empty dashboard and Collections screen guide the initial Admin setup.
- Admin may create one current primary collection, rename it without changing pricing behavior, archive it without losing history, and create a replacement. Concurrent duplicate primary creation is rejected by PostgreSQL and the workflow action. A primary may contain more than four independently configured variants.
- Admin may create any number of add-on collections, including two with the same behavior. Food eligibility and serving offerings remain scoped to collection IDs; a food item may belong to exactly one or several collections.
- Approved behaviors are package-based per set, packed-meal per pack, fixed bundle per set, direct-sale identity, and primary catering per pax. Package-based add-ons have no universal kilogram requirement. Fixed bundles have only fixed selections and no option premiums. Direct-sale collections reject package configuration; Milestone B implements independently priced listing versions.
- Inactive or archived collections, food items, categories, eligibilities, offerings and serving options are unavailable for new package selections. Package validation and preview use configured groups, counts, quantity units and integer centavos.
- Staff can manage collections only with the existing catalog-management permission. Significant creation, editing, archive and package changes are audited; Admin retains full access.

## Milestone B acceptance amendment (2026-10-14)

### Later service-owned configuration acceptance (2026-10-20)

- New package-service creation requires an explicit format and default container; direct-sale and Main Catering contexts reject package configuration.
- New HTTP package creation fails closed for an unconfigured legacy service. Once configured, package format/default container are inherited and read-only in the editor; forged overrides fail server-side.
- Existing packages and historical clones retain recorded defaults. Changing the service container affects newly created packages only. Existing mixed-format services require an explicit future setting, never an inferred migration choice.
- Optional portion, food-price, size and container-exception controls use progressive disclosure. Switching away from configured sizes/amounts requires confirmation; size pricing does not duplicate a general food surcharge.
- Verify different food counts in one service, fixed-only menus, complete packs/boxes, quantity repetition, numeric/named sizes, immutable revisions/clones, Staff authorization, light-only desktop/mobile layouts, and existing Main Catering/individual-listing regressions. See `23-service-package-configuration.md` for executed results and remaining scope.

- One package editor owns Details, Menu, Pricing & Quantity and Review & Save. Navigation does not create records. Invalid input and stale revision failures roll back the complete save. Publication remains explicit and confirmed.
- Automatic sole-required-category foods and explicit Always Include form a distinct fixed union counted toward exactly N foods. Main Catering rejects measurements/premiums. Category Select All changes allowance only.
- Contextual Add Food preselects its category, permits transactional inline category creation and preserves unsaved package state.
- Optional images validate decoded content, size/dimensions, use generated private storage references, render thumbnails/full views and clean up rollback/replacement files. Unauthorized image changes fail server-side.
- Published Package Details uses only the immutable snapshot; separately authorized Draft previews use saved draft data. Group by saved category; show prices, purchase rules, fixed intent, measurements and current availability.
- Ordinary service creation offers two selling methods. Generic package services may opt into packed-meal semantics in Pricing. Legacy packed_meal/fixed_bundle contracts remain supported.
- Individually sold services create independent food/optional-size listings, save Draft terms in centavos, publish immutable snapshots, revise/clone history and disable/archive without losing history.
- Listing SQL constraints enforce source ownership, one Draft, valid price/quantity facts, immutable publication and forward-only same-owner pointers. Separate Staff editing, pricing, publishing, history and lifecycle permissions are enforced.
- Fresh-install and 12-to-15 migration verification use ONLY zek_catering_test on 127.0.0.1:5432, preserving representative IDs and package snapshots across the upgrade. Development migration requires owner authorization.
- Owner testing follows the current guide and verification report; passing tests is not owner acceptance or production certification.

### Phase 3.1 package-builder and Measurement Unit acceptance (2026-10-20)

- An Administrator configures an add-on food through one collapsed **Food Options** section: standard serving, a fixed portion using an active configured Measurement Unit, or size choices where permitted. Portion and packaging remain distinct facts.
- Newly entered free-text units, inactive units, and malformed portion pairs are rejected by Laravel. An active selected unit is resolved server-side and its abbreviation is frozen into the draft and later snapshot.
- Moving between a general selection charge and size choices requests confirmation and cannot leave contradictory price data. Main Catering still rejects food portions and food-specific charges.
- An unused Measurement Unit can be edited, deactivated/reactivated, or deleted by an authorized Administrator. A referenced unit cannot be renamed or deleted through application workflows; deactivation retains history and prevents new selection. Unauthorized Staff HTTP writes fail.

### Phase 5 Add-on experience and local-bootstrap acceptance (2026-10-21)

- An Administrator creates an Add-on Service by entering service details, choosing Sold by Package or Sold Individually, and sees package format and presentation only for package selling. Separate Food Servings and Combined Contents describe actual container behavior without fixed food examples or altered pricing rules.
- A normal Add-on Package inherits the service presentation and saves without a package-wide food portion. **Advanced Package Settings** is collapsed until a Default Food Portion is intentionally configured; a saved configuration remains discoverable and survives reopening.
- Food Options remains one collapsed configuration surface per Food Item. Fixed portions, size choices, surcharges and presentation exceptions retain their established mutually compatible validation and never convert packaging into food weight.
- A Measurement Unit created or changed through either quick creation or Manage Measurement Units persists, updates relevant active selectors without a full reload, and preserves unsaved Service/Package input. Referenced-unit and inactive-unit safeguards remain server-enforced.
- On an isolated local/test schema with the explicit test flag, the normal `DatabaseSeeder` creates roles, permissions, the primary service, `Admin Tester`, 11 categories, 42 Food Items and four incomplete Draft packages in order. Reruns do not duplicate fixtures or overwrite the known account password or ordinary fixture edits. Normal non-local/test seeding does not create known credentials or commercial fixtures.

### Phase 5.1 catalog experience acceptance (2026-10-21)

- Food Catalog uses a compact List view by default and an optional remembered Grid view. Both use the same server-side search, category/availability filters, ordering and 12/24/48 pagination; category counts are explicitly total catalog counts.
- The catalog preserves controls during a filter visit, delays result loading feedback to avoid fast-request flashing, and cancels/sequences filter visits so stale responses cannot replace current results.
- Package Menu food discovery is searchable and category-filtered in a bounded result region. Selected Food Items remain separately visible when filters change. Each visible Customer Choice Group persists a category-specific minimum and maximum count; a category with one eligible choice is promoted to an Included Item on the server. Configured totals, fixed foods, choice bounds and feasibility are validated before a Draft can be saved or published.
- Direct-sale **Add Foods to Sell** selects active Food Items across one or more categories without expanding existing editors. Shared selling settings are explicit; each selected Food Item keeps an independent price and Draft listing. The transaction rejects unavailable or duplicate sources and retrying an identical payload is idempotent.
- A presentation exception equal to the inherited default is not saved as a new override; a changed quantity or container remains valid. Existing Draft and Published presentation facts are not rewritten.
- New Food photos have validated, readable slug-plus-opaque-ID paths; existing paths remain compatible. Internal account photos use opaque paths, ownership/Team-access reads, audit events, safe replacement/removal and initials fallbacks.
