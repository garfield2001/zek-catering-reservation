# Phase 3.5 — Dynamic collections and administrator setup

Current approved refactor: [Business workspaces and package architecture](15-business-workspaces.md) supersedes conflicting historical Variant, manual primary setup, Main Catering surcharge/Serving Option, and empty-primary-installation descriptions below. The primary Catering service is automatically provisioned; add-on services remain dynamic. Catering and compatible add-ons use a shared category/food menu builder. Package quantities are independently owned, not inherited. Advanced Serving Option rules remain internal; their ordinary manual group editor is removed.

## Domain and migration decision

`product_families` remains the collection identity table so existing dish eligibility, offerings, products, package drafts, audit locators and foreign keys retain their IDs. The internal column/table names remain for migration compatibility; the UI calls these records **collections**. A collection has an immutable code, primary/add-on role and supported `behavior_type`. Its name, description, display order and active state are presentation/availability data. A nullable `archived_at` distinguishes archival from temporary deactivation. At most one non-archived primary collection is enforced by a PostgreSQL partial unique index. Zero primary collections is valid before setup.

Supported behavior types are `main_package` (primary, per pax), `package` (add-on, per set), `packed_meal` (add-on, per pack with optional split), `fixed_bundle` (add-on, per set and fixed inclusions), and `direct_sale` (add-on, future direct listings). These are controlled mechanisms, not customer-facing names or executable business rules. The existing `main_catering`, `belly_lechon`, `food_pack`, `food_tray`, and `pica_pica` codes are preserved as historical collection IDs and mapped to those five behaviors without merging or deleting any row. New add-on codes are generated stable identifiers. `family_order_defaults` remains mandatory and is inserted in the same creation transaction.

The clean baseline creates the normalized-code CHECK, behavior/role CHECK, unique non-archived primary index and current cross-table package trigger using `behavior_type` directly. It widens slot kinds with a generic `item` type while retaining legacy kinds and their serving-unit checks. Package behavior no longer demands lechon kilograms or tray choices; packed meals no longer demand rice, drink or dish slots. Required selections are defined by configured groups. Fixed bundles permit only fixed inclusions and no option premiums. `direct_sale` cannot own Package Products. The existing master catalog and availability joins already scope eligibility and offerings by collection ID.

The earlier Phase 3.5 record did not rewrite applied migrations. The later clean-baseline decision supersedes that deployment assumption for this unreleased repository: project-owned migration files now describe a fresh install directly, while existing development rows remain untouched until the owner chooses conversion/import or an explicitly authorized reset. Verification uses only the isolated `_test` database. The production seeder stops creating commercial collections. No demonstration commercial prices or products are seeded.

## First-run and later-phase boundary

Current collection names are unique after trimming and case folding. Archival releases the name for a replacement while preserving both row identities and their historical references. Rollback is intentionally refused if administrator-created collections, archived collections or generic `item` slots would no longer fit the older schema; an empty fresh installation was verified through up/down/up migration smoke tests on the isolated PostgreSQL test database. The Activity History response applies server-side recursive redaction to both new and legacy event payloads before sending data to the browser.


## Terminology map

The later approved [domain vocabulary](domain-terminology-audit.md) refines this historical map: use **Selection Group** internally (`selection_groups`), **Included Items** for fixed groups and **Customer Choices** for selectable groups in business UI, and **Group Allowed Option** for the `group_options` junction. The physical identifiers below remain unchanged.

| Internal name | Admin label | Meaning |
|---|---|---|
| `dishes` | Food items | Canonical food or beverage identity |
| `dish_categories` | Food categories | Culinary classification |
| `product_families` | Collections | Primary catering or optional add-on grouping |
| `behavior_type` | Configuration type | Validated selection/pricing mechanism |
| `dish_family_eligibilities` | Collection availability | Whether a food item may be offered in a collection |
| `dish_offerings` | Offerings | Collection-specific preparation |
| `offering_options` | Serving options | Serving unit, size and capacity information |
| `products` / `package_versions` | Package Products / Package Versions | Independently named draft owners and published revisions |
| `selection_groups` | Inclusion groups | Fixed or selectable options in one draft |
| `audit_events` | Activity History | Append-only administrative/security record |

Historical migration identifiers and API field names remain stable. New UI uses business terms without renaming persistent identities solely for presentation.
