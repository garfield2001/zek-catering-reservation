# V3 Database Entity Definitions

## Commercial refinement amendment (2026-10-16)

This amendment and [the current implementation record](19-commercial-refinement.md) supersede historical assignment requirements below. Active Food Items in active Food Categories are globally available; the target Collection must also be active and unarchived. The `dish_family_eligibilities` junction is retired. Offerings and individual listings retain direct Food Item and Collection foreign keys. Previously published commercial snapshots are not rewritten.

New implemented entities and fields:

| Entity | Fields and integrity |
|---|---|
| measurement_units | id PK, name, abbreviation, active, timestamps; normalized name and abbreviation unique; referenced units restricted from deletion |
| package_versions | Optional default_serving_quantity NUMERIC(12,3), default_serving_unit_id FK measurement_units, default_serving_size_label; all absent or positive finite amount with a unit; Main Catering prohibited; existing publication immutability applies |
| package_food_size_groups | id PK; package_version_id, dish_id, measurement_unit_id FKs; unique version/food; composite FK to package_version_food_items; immutable ownership; Draft-only mutation |
| package_food_size_choices | id PK; size_group_id FK; positive quantity NUMERIC(12,3); optional size_label; nonnegative integer extra_price_minor; is_default; exactly one zero-extra default per group via partial unique index and deferred guard; distinct numeric amounts when unnamed or normalized labels when named; Draft-only mutation |

Serving resolution is explicit size choice, otherwise explicit legacy food measurement, otherwise optional package-version default. This inheritance does not affect purchase quantities or overwrite overrides. Named sizes can have identical included numeric amounts. Extras multiply purchased package quantity and never count as additional foods. Legacy general food premiums cannot be combined with size pricing for the same food. Snapshot schema 4 freezes defaults, size names, measurements and prices; cloning creates independently owned Draft configuration.

Individual listings now support version-owned grouped selling options with absolute centavo prices. Published schema-1 single-price records remain compatible and immutable; new schema-2 publications freeze their selling options. Public-disk photos, contextual food configuration, scoped commercial history and deactivation warnings are implemented. History/impact features read existing entities and do not introduce another publication or audit schema.

## Current implementation authority

[Final product configuration](15-business-workspaces.md) supersedes conflicting historical/provisional fields below. The implemented hierarchy is Collection → Product → Package Version. Shared menu pivots support all package behaviors; quantity rules are independently stored on each Version, with no override or inheritance columns. The editable Draw.io ERD represents this baseline.

## Phase 4 package publication amendment

The original logical ERD and the rows below retain physical legacy names and some provisional publication assumptions. The [Phase 4 package architecture](13-phase-4-package-publication.md) is authoritative for implemented package lifecycle rules. Milestone B supersedes the former direct-listing and media designs; storefront navigation remains deferred.

`products.archived_at?` permanently archives a Package Product and requires `active=false`. `products.current_version_id?` has a composite same-product FK to `package_versions`; its trigger requires published status, forbids clearing, and forbids moving backward. `package_versions.status` permits `draft`, `published`, `abandoned`; a partial unique index on `package_product_id WHERE status='draft'` enforces one active Draft per Package Product. Publication metadata and `published_snapshot` are constrained by state. Published and abandoned Versions and their child rows are database-immutable.

Current implementation: Package Versions freeze the complete commercial JSON snapshot and populate `group_options.snapshot_*` at publication for add-on feasibility checks. Master category/unit edits do not rewrite published facts; current active status and eligibility remain live checks. Clone provenance is checked by the Action and PostgreSQL trigger. Child reparenting is forbidden; child mutations lock their Version. The clean baseline directly creates these objects. It is a fresh-install baseline, not an in-place development upgrade. The shared category menu uses the two menu tables below for Catering and add-ons. Advanced serving-option groups remain an internal separately validated configuration, with no ordinary manual editor and no mixing within a Version.

Current-state/ERD legend: this document and `docs/diagrams/catalog_v3.drawio` retain physical table and FK names, including `product_families` (Collection), `dishes` (Food Item), `dish_categories` (Food Category), `selection_groups` (Selection Group), and `group_options` (Group Allowed Option). Older fixed-five-family and mandatory-slot descriptions are historical; [Phase 3.5](10-phase-3-5-dynamic-collections.md) defines the active dynamic collection constraints, and the [approved vocabulary](domain-terminology-audit.md) defines presentation terms. Milestone B implements individual listing versions and Food Item image metadata. Storefront taxonomy remains deferred.

This document describes implemented access, catalog, package configuration and publication entities. Individual listings and Food Item images are implemented by Milestone B. Storefront navigation below remains a deferred design.

## Conventions and constraints

- Identity tables use BIGINT identity primary keys. Junctions use composite primary keys unless stated otherwise. Application models may use a surrogate key where Eloquent requires one, but the documented composite uniqueness remains mandatory.
- Identity/business rows have created_at/updated_at TIMESTAMPTZ unless append-only; audit has created_at only. Store UTC, display Asia/Manila. Required columns are NOT NULL unless marked ?. Booleans have explicit defaults.
- Monetary columns are BIGINT CHECK >= 0; currency is CHAR(3) CHECK = 'PHP'. Prices are never floating point. API money is a decimal string. Calculation checks overflow before arithmetic/multiplication.
- Serving quantities/inclusion counts are NUMERIC(12,3) CHECK > 0. Purchased quantities/counts/minima/steps are integers; selection min may be zero, max >= min. Order minimum/step >= 1; nullable maximum >= minimum. Define a controlled unit vocabulary (kg, tray, portion, piece, bottle, pack); adding units requires explicit conversion/display semantics, not guessed conversions.
- Codes/slugs are normalized lowercase, nonempty, unique in their documented scope. Users' emails are normalized and have a unique lower(email) index. Use text columns plus CHECK constraints for finite statuses/modes.
- Every FK has an intentional deletion policy. Default RESTRICT for identities referenced by commercial/history rows; archive using active=false. Do not delete users with audit/media references. Draft-owned child deletion is permitted through explicit workflows; published triggers prevent deletes/cascades. Do not use blanket soft deletion or cascading removal of commercial history.
- Index referencing FK columns unless a primary/unique/index prefix already covers them. Add reverse junction indexes where needed, owner/status publication lookups, navigation parent/order indexes and audit actor/time and subject/time indexes. Validate browse query plans before additional indexes.
- Ordinary CHECK constraints cover row-local rules. Cross-table family consistency, publication status and immutable children require composite FKs/triggers where specified, plus transactional domain validation. Category feasibility and complete business validation remain domain-service responsibilities.
- Only migration credentials may alter schema/triggers. The runtime DB role cannot disable protections. Database constraints do not replace Policies.

## Access and audit

| Entity | Fields beyond common conventions | Keys and behavior |
|---|---|---|
| users | email, password, name, active=true, access_version=1 | Case-insensitive unique email; disable/revoke sessions instead of deleting referenced users |
| roles | code, name | Unique code; seeded admin/staff |
| permissions | code, description | Unique code; explicit capabilities for catalog editing, prices, publishing, media, audit and access |
| user_roles | user_id FK users, role_id FK roles | Composite PK |
| role_permissions | role_id FK roles, permission_id FK permissions | Composite PK |
| user_permission_overrides | user_id FK users, permission_id FK permissions, effect allow/deny | Composite PK; one effective override per permission |
| audit_events | actor_id? FK users, actor_label, action, subject_type, subject_id?, before_data? JSONB, after_data? JSONB, request_id?, context JSONB, created_at | Append-only; actor nullable for system operations; immutable actor label preserves attribution; redacted context |

Commercial Version History reads these existing events and immutable publication snapshots; it introduces no parallel audit tables. Package/listing history permissions govern the payload independently of global Activity History access. Publisher labels come from their publication events, never from a guessed current account name. Missing historical labels are explicitly identified. Snapshot comparisons show business facts rather than regenerated child identifiers. The scoped edit timeline shows at most 200 latest events, with an explicit truncation notice. New advanced-group audit facts include `package_version_id` so deleted group changes remain attributable; older orphaned group events without ownership facts cannot safely be assigned and are not rewritten.

Deactivation impact previews are read-only, catalog-management authorized and non-cacheable. They use actual service counts and permission-filtered Food Item/Draft usage. Warnings distinguish reversible deactivation from permanent archive and Draft abandonment. They do not simulate customer transactions, change child enabled flags or mutate published snapshots. Frozen category dependencies can survive moving a master Food Item, so the current usage links are guidance, not an exhaustive feasibility guarantee.

Audit subject_type/subject_id is intentionally a historical locator, not an unenforced business FK. An explicit subject-type vocabulary and stored descriptive context allow history to survive archival. No secrets in JSONB. Access mutations and the last-active-Admin check lock affected accounts/roles in a transaction; increment access_version and invalidate sessions/cached permissions.

Laravel infrastructure tables (sessions, jobs, failed jobs, cache as selected) are implementation infrastructure, outside the catalog ERD. Public registration and customer accounts do not exist.

## Master catalog

| Entity | Fields | Keys and behavior |
|---|---|---|
| dish_categories | id, code, name, active=true | Unique code; one primary category per dish |
| dishes | id, code, category_id FK dish_categories, name, description?, active=true | Unique code; canonical identity; names need not be globally unique |
| product_families | id, code, name, description?, sort_order=0, is_addon, behavior_type, active=true, archived_at? | Immutable code/role/behavior; normalized name unique among nonarchived rows; one current primary provisioned idempotently; dynamically named add-ons created by Admin. No mandatory five-family seed. |
| dish_family_eligibilities | dish_id FK dishes, family_id FK product_families, active=true | Composite PK; inactivate retained eligibility instead of deleting referenced pairs |
| dish_offerings | id, dish_id, family_id, code, name, description?, active=true | Composite FK (dish_id,family_id) to eligibility; UNIQUE(dish_id,family_id,code); multiple preparations allowed |
| offering_options | id, offering_id FK dish_offerings, option_code, label, unit, quantity, serving_pax?, serving_claim?, active=true | UNIQUE(offering_id,option_code); serving_pax positive integer when present; no price column |
| family_order_defaults | family_id PK/FK product_families, minimum_quantity, quantity_step, maximum_quantity?, enforce_minimum_floor=false | Retained catalog metadata only; no package inheritance or enforced package floor |

No generic business_settings key/value table is required. Typed family_order_defaults remains retained catalog metadata; it does not constrain package purchases or drafts. Package quantity settings live exclusively on their Versions.

The availability predicate includes active family and retained eligibility in addition to all applicable active owner/master rows. Editing current culinary classification does not change frozen classification; active checks for a published selection also include its frozen category.

## Package identities and versions

| Entity | Fields | Keys and behavior |
|---|---|---|
| products | id, family_id FK product_families, code, name, description?, sort_order=0, active=true | UNIQUE(family_id,code); family immutable; current_version_id and archived_at preserve publication/history; optional creation_token is unique; no redundant product type |
| package_versions | id, package_product_id FK products, version_number, status draft/published/abandoned, draft_revision=1, cloned_from_id? FK package_versions, family_id FK product_families, base_price_minor, currency, price_basis, selection_policy, total_selection_count?, estimated_capacity?, independent minimum_quantity/quantity_step/maximum_quantity, publication metadata | UNIQUE(package_product_id,version_number), UNIQUE(package_product_id,id); one active Draft partial index; effective fields freeze on publication |

Each Version stores its independent minimum, increment and optional maximum. Draft changes are validated directly; Collection defaults never constrain package values. Copying another package's rules copies values once and creates no inheritance relationship. These commercial facts freeze on publication.

Stable Collection behavior dictates price basis: main_package/per_pax, package/per_set, packed_meal/per_pack, fixed_bundle/per_set. Split and optional subgroup restrictions are legal only for packed_meal. Subgroup fields are null by default; if configured, minimum and step are required and maximum is optional. Fixed bundles are uniform.

Current pointer uses composite FK (id,current_version_id) from products to package_versions(package_product_id,id). A trigger additionally requires published status. Clone provenance must reference the same Package Product and a published source. Initial draft has no source. Version numbers allocate under the owner lock. One active Draft is permitted per Package Product; stale publication must explicitly acknowledge a newer current ID, otherwise return conflict.

Published metadata must be non-null exactly when status=published. Published version columns and owned rules/snapshots cannot be changed. A newer current publication does not mark the former one draft or mutate its status. Current pointers cannot be cleared; disable/archive controls withdraw packages while retaining history.

## Package groups and frozen option facts

| Entity | Fields | Keys and behavior |
|---|---|---|
| package_version_food_items | package_version_id FK package_versions, dish_id FK dishes, is_fixed, always_include, included_quantity?, included_unit?, price_adjustment_minor, timestamps | Composite PK; shared food menus; always_include preserves explicit add-on intent separately from derived fixed status; Published children immutable; Main forbids measurements/premiums, bundles require all fixed and no premiums |
| package_version_required_categories | package_version_id FK package_versions, category_id FK dish_categories, timestamps | Composite PK; shared package menus; each required category needs a selectable food; Published children immutable |
| selection_groups | id, package_version_id FK package_versions, group_code, name, mode fixed/select_one/select_many, slot_kind item/rice/drink/dish/lechon/tray/bundle_item, minimum_count, maximum_count, unique_dishes=true, category_mode free/restricted, inclusion_count, sort_order | UNIQUE(package_version_id,group_code); add-on package groups; Main Catering uses package_version_food_items and package_version_required_categories |
| group_options | id, group_id FK selection_groups, offering_option_id FK offering_options, is_default=false, price_adjustment_minor=0, snapshot_dish_id? FK dishes, snapshot_category_id? FK dish_categories, snapshot_offering_name?, snapshot_dish_name?, snapshot_category_name?, snapshot_option_code?, snapshot_option_label?, snapshot_unit?, snapshot_quantity?, snapshot_serving_pax?, snapshot_serving_claim? | UNIQUE(group_id,offering_option_id); all mandatory snapshot fields populated at publication; nullable serving estimates/claims remain optional |
| group_category_rules | group_id FK selection_groups, category_id FK dish_categories, minimum_count, maximum_count | Composite PK; restricted-mode whitelist/quotas; no rows for free mode |
| group_dish_rules | group_id FK selection_groups, dish_id FK dishes, effect allow/deny | Composite PK; conflicting allow+deny rows impossible |

Snapshot dish/category identities remain referenced with RESTRICT. The option's parent offering and dish identity cannot be reparented after use in a publication; create a new option/offering instead. Editing names, serving facts or a dish's current category is allowed, but affects only future snapshots. Group quotas use snapshot_category_id and duplicates use snapshot_dish_id in published selection validation.

The group_option snapshot is the historical commercial description, not a second editable master catalog. On draft save/preview, compare current master values and flag stale snapshots; publication recomputes snapshots under locks. Neither new eligibility nor new options expand an existing publication.

Rules are version-owned through groups and remain immutable, including inserts into a published group. The same trigger protection applies to changing a child's parent from or into a published version. Publication validates references, family consistency, default selections, exact fixed counts, category quotas and satisfiability considering current availability and canonical uniqueness.

## Individual listings — Milestone B

| Entity | Fields | Integrity |
|---|---|---|
| individual_listings | family_id, dish_id, offering_option_id?, code, creation_token, creation_hash, active, archived_at?, current_version_id? | Service must be an add-on with stable direct_sale behavior; source identity immutable; restricted food/service FKs; optional legacy serving option must match food/service; actor-bound idempotent creation |
| listing_versions | listing_id, version_number, draft_revision, status, name, uses_food_name, description?, selling_unit, has_selling_options, price_minor, currency PHP, minimum_quantity, quantity_step, maximum_quantity?, published_snapshot?, published_at?, published_by?, cloned_from_id? | Unique listing/version number; one Draft; selling_unit is trimmed descriptive text (1–30 characters, no control characters), not a behavior code; positive purchase quantities; price_minor is the lowest option price for grouped versions, legacy absolute price otherwise; immutable publication and same-owner older Published clone source |
| listing_selling_options | id, listing_version_id FK RESTRICT, label, price_minor BIGINT, sort_order | Nonblank normalized-unique label and unique order per version; nonnegative absolute price; Draft-only mutations with parent locking; immutable ownership; deferred constraints require grouped versions to have options and matching lowest price, all positive on publication |

A composite current pointer references (id, listing_id) and must identify that listing's Published Version. The pointer cannot be cleared or moved backward. Parent locking serializes publication and cloning. Published/Abandoned rows and all version identities are immutable; history cannot be deleted. One Food Item listing owns multiple labeled selling options through its versions. Legacy separate listings and optional Serving Option references remain supported, but are not required by the ordinary editor. Blank display-name override resolves the current Food Item name at save, then freezes at publication.

The authoritative published option prices, names, category, optional legacy source facts, selling unit and purchase rules live in JSONB published_snapshot (schema 2 for new publications). Backend preview requires an owned selling option when multiple options exist and multiplies its absolute price by purchased quantity; no package surcharge logic is reused. Minimum/increment/maximum apply to listing purchase quantity. Current availability combines listing, service, food/category and any legacy offering/option active state, without manual eligibility assignment. Live master edits never rewrite snapshots. Individual listings are not Package Products. Existing listing.view/manage/pricing.manage/publish/history.view/lifecycle.manage permissions remain; changing any option price requires pricing permission.

## Storefront navigation — deferred design, not implemented

| Entity | Fields | Keys and behavior |
|---|---|---|
| catalog_categories | id, parent_id? FK catalog_categories, slug, name, visible=true, sort_order | Globally unique slug; reject self-parenting and descendant cycles; lock taxonomy edits to prevent concurrent cycles |
| category_direct_listings | category_id FK catalog_categories, direct_listing_id FK direct_listings, sort_order | Composite PK; browse current published revision |
| category_package_products | category_id FK catalog_categories, package_product_id FK products, sort_order | Composite PK; browse current published version (future navigation scope) |

Navigation visibility is inherited: a hidden ancestor hides descendants from storefront browsing. Assignment is explicit, not inferred from a culinary category. Stable ordering is sort_order then id; tied sort values are allowed. Reparenting/renaming does not affect purchasing rules. Delete only unreferenced nodes or explicitly reassign children/attachments in a transaction.

## Food image metadata — Milestone B

The dishes table has nullable image_disk, image_path, image_thumbnail_path, image_width and image_height. A CHECK enforces either all NULL or all present with positive dimensions. Binary data is not stored in PostgreSQL. Paths are server-generated ULID JPEG names; filesystem metadata is hidden from serialized models, which expose controlled image URLs.

New photographs use Laravel's public disk, with a full re-encoded image bounded to 1600 pixels and a thumbnail bounded to 360 pixels. Testing uses a separate testing/public directory, never the development public directory. Existing private catalog references remain readable and are replaced only when that Food Item's image is explicitly changed. No bulk image migration occurs. The configured public/storage link targets app/public for normal installations (`php artisan storage:link`, owner-managed setup); application thumbnails still use the controlled media endpoint, which also supports isolated test storage and legacy files. Public photographs are not confidential: deactivation prevents catalog selection, not access through a known public storage URL. Decode/type/size/dimension validation precedes storage. False storage-write results are rejected, not committed as image metadata. New files are removed on transaction rollback (including ancestor workflows); replaced/removed files are deleted after commit. A generic cleanup warning records storage-deletion failures for operator follow-up without exposing credentials. Crashes/storage outages can still require operational orphan cleanup; no asynchronous media pipeline or multi-image attachment tables are introduced.

## Migration/entity dependency order

### Version-owned Package Format and physical presentation (2026-10-18)

`package_versions` adds nullable `package_format` (`separate_servings` or `combined_contents`), `presentation_quantity` (positive integer container count) and `presentation_unit_id` (restricted FK to Measurement Units). `package_version_food_items` adds nullable presentation quantity/unit overrides with the same FK restriction. Unit columns are indexed. Combined Contents requires one complete container and prohibits food-container overrides; Separate Food Servings may use an optional default and exceptions. Main Catering and individual listings cannot acquire Package Format. Newly formatted configurations require uniform selections. Old null formats and immutable snapshots are not inferred or rewritten. Existing publication/ownership guards remain authoritative. See [Package Format implementation and compatibility](20-addon-package-formats.md).

1. Users, roles, permissions, access junctions and append-only audit.
2. Culinary categories, product families, family defaults, dishes, eligibility.
3. Offerings, options, products and Package Versions without current pointer FK.
4. Selection Groups, allowed options/snapshots and category/dish rules.
5. Milestone B forward extensions: explicit Main fixed intent and package per-pack behavior, then dish image metadata, individual listings and listing_versions.
6. Add composite current-pointer FKs, provenance checks, immutable-publication triggers and supporting indexes.
7. Storefront navigation and multi-image attachment entities remain deferred and are not created.

The implemented schema includes Milestone B listing and image extensions but excludes storefront navigation and multi-image attachment designs. PostgreSQL integration tests verify the implemented constraints, publication guards and fresh schema. Publication feasibility remains authoritative domain validation, not an ordinary row CHECK.

Database design reference: [PostgreSQL constraints](https://www.postgresql.org/docs/current/ddl-constraints.html) explains composite foreign keys, row-local CHECK limitations, partial unique indexes and why referencing foreign-key columns need deliberate indexing.
# Phase 3.5 entity amendment (2026-10-10)

`product_families` now represents administrator-created collections. New columns: non-null immutable `behavior_type` and nullable irreversible `archived_at`. `code` remains an immutable, unique internal identifier; new values are generated, while five historical codes map unambiguously to behaviors. PostgreSQL CHECK constraints enforce behavior/role compatibility and archive/inactive consistency. A partial unique index permits zero or one non-archived primary; another permits a normalized name only once among non-archived collections. Historical archived rows retain IDs and may share a name with a new collection. New collection and its `family_order_defaults` row are inserted in one transaction. Existing child foreign keys and eligibility pairs remain unchanged. `selection_groups.slot_kind` gains `item`; cross-table triggers validate behavior, price basis, slot modes, collection ownership and relevant serving units. See [migration detail](10-phase-3-5-dynamic-collections.md) and the updated Draw.io ERD.
