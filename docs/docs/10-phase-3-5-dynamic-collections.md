# Phase 3.5 — Dynamic collections and administrator setup

## Domain and migration decision

`product_families` remains the collection identity table so existing dish eligibility, offerings, products, package drafts, audit locators and foreign keys retain their IDs. The internal column/table names remain for migration compatibility; the UI calls these records **collections**. A collection has an immutable code, primary/add-on role and supported `behavior_type`. Its name, description, display order and active state are presentation/availability data. A nullable `archived_at` distinguishes archival from temporary deactivation. At most one non-archived primary collection is enforced by a PostgreSQL partial unique index. Zero primary collections is valid before setup.

Supported behavior types are `main_package` (primary, per pax), `package` (add-on, per set), `packed_meal` (add-on, per pack with optional split), `fixed_bundle` (add-on, per set and fixed inclusions), and `direct_sale` (add-on, future direct listings). These are controlled mechanisms, not customer-facing names or executable business rules. The existing `main_catering`, `belly_lechon`, `food_pack`, `food_tray`, and `pica_pica` codes are preserved as historical collection IDs and mapped to those five behaviors without merging or deleting any row. New add-on codes are generated stable identifiers. `family_order_defaults` remains mandatory and is inserted in the same creation transaction.

The additive migration drops the old five-code CHECK and replaces it with a normalized-code CHECK, behavior/role CHECK, unique non-archived primary index and updated cross-table package trigger using `behavior_type`. It widens slot kinds with a generic `item` type while retaining legacy kinds and their serving-unit checks. Package behavior no longer demands lechon kilograms or tray choices; packed meals no longer demand rice, drink or dish slots. Required selections are defined by configured groups. Fixed bundles permit only fixed inclusions and no option premiums. `direct_sale` cannot own package variants. The existing master catalog and availability joins already scope eligibility and offerings by collection ID.

No applied migration is rewritten. Existing development rows remain untouched until the owner elects to run migrations; Phase 3.5 verification uses only the isolated `_test` database. The production seeder stops creating commercial collections. Existing seeded rows stay in upgraded installations; operators may archive unwanted legacy collections after reviewing their references. Initial Admin provisioning remains the existing interactive command. No demonstration commercial prices or products are seeded.

## First-run and later-phase boundary

Current collection names are unique after trimming and case folding. Archival releases the name for a replacement while preserving both row identities and their historical references. Rollback is intentionally refused if administrator-created collections, archived collections or generic `item` slots would no longer fit the older schema; an empty fresh installation was verified through up/down/up migration smoke tests on the isolated PostgreSQL test database. The Activity History response applies server-side recursive redaction to both new and legacy event payloads before sending data to the browser.

An empty collection/catalog state is normal. Admin creates the one primary Main Catering collection, then configures its package product and any number of variants. Add-on collections can be created independently with one supported behavior each. The package editor handles `main_package`, `package`, `packed_meal` and `fixed_bundle`; `direct_sale` is an identity/eligibility context only until its dedicated listing revision manager is implemented. No draft is customer-bookable. Phase 4 must implement one active draft and one current published version per variant, immutable snapshots, atomic publication and current availability checks. Customer inquiries, reservations and checkout remain later work.

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
| `products` / `product_variants` | Package products / variants | Independently named draft owners and choices |
| `selection_groups` | Inclusion groups | Fixed or selectable options in one draft |
| `audit_events` | Activity History | Append-only administrative/security record |

Historical migration identifiers and API field names remain stable. New UI uses business terms without renaming persistent identities solely for presentation.
