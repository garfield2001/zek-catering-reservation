# Catalog Acceptance Scenarios V3
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
