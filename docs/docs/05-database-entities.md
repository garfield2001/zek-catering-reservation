# V3 Database Entity Definitions

This document defines physical-design decisions missing from the original logical ERD. Phases 1–3 are implemented in migrations; the publication, direct-listing, navigation and media sections remain future specifications. See 09-phase-3-package-drafts.md for Phase 3's draft-only differences. The draw.io diagram visualizes entities and FK dependencies.

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

Audit subject_type/subject_id is intentionally a historical locator, not an unenforced business FK. An explicit subject-type vocabulary and stored descriptive context allow history to survive archival. No secrets in JSONB. Access mutations and the last-active-Admin check lock affected accounts/roles in a transaction; increment access_version and invalidate sessions/cached permissions.

Laravel infrastructure tables (sessions, jobs, failed jobs, cache as selected) are implementation infrastructure, outside the catalog ERD. Public registration and customer accounts do not exist.

## Master catalog

| Entity | Fields | Keys and behavior |
|---|---|---|
| dish_categories | id, code, name, active=true | Unique code; one primary category per dish |
| dishes | id, code, category_id FK dish_categories, name, description?, active=true | Unique code; canonical identity; names need not be globally unique |
| product_families | id, code, name, description?, sort_order=0, is_addon, active=true | Unique code and normalized case-insensitive display name; seed exactly five stable codes. DB trigger makes code and primary/add-on flag immutable. Display fields and active state are editable with `family.manage` and audit. Four are add-ons; `main_catering` is the required primary for future reservations. |
| dish_family_eligibilities | dish_id FK dishes, family_id FK product_families, active=true | Composite PK; inactivate retained eligibility instead of deleting referenced pairs |
| dish_offerings | id, dish_id, family_id, code, name, description?, active=true | Composite FK (dish_id,family_id) to eligibility; UNIQUE(dish_id,family_id,code); multiple preparations allowed |
| offering_options | id, offering_id FK dish_offerings, option_code, label, unit, quantity, serving_pax?, serving_claim?, active=true | UNIQUE(offering_id,option_code); serving_pax positive integer when present; no price column |
| family_order_defaults | family_id PK/FK product_families, minimum_quantity, quantity_step, maximum_quantity?, enforce_minimum_floor=false | Exactly one per family; Main Catering starts at minimum 150 and enforce=true; Food Pack starts at 10 packs from Phase 3 |

No generic business_settings key/value table is required for this scope: the one documented business floor and its enforcement flag live in typed family_order_defaults. Changing them is Admin-only and audited. This removes an untyped competing source of truth.

The availability predicate includes active family and retained eligibility in addition to all applicable active owner/master rows. Editing current culinary classification does not change frozen classification; active checks for a published selection also include its frozen category.

## Package identities and versions

| Entity | Fields | Keys and behavior |
|---|---|---|
| products | id, family_id FK product_families, code, name, description?, sort_order=0, active=true | UNIQUE(family_id,code); family immutable once versions/listings reference the product; no redundant product type |
| product_variants | id, product_id FK products, code, name, description?, sort_order=0, active=true, current_version_id? | UNIQUE(product_id,code) and normalized case-insensitive display name per product; no fixed variant count. Current pointer constrained below; Food Tray products cannot own package variants |
| variant_versions | id, variant_id FK product_variants, version_number, status draft/published, draft_revision=1, cloned_from_id? FK variant_versions, family_id FK product_families, base_price_minor, currency, price_basis, selection_policy uniform/split, minimum_override?, step_override?, maximum_override?, maximum_override_enabled=false, minimum_quantity, quantity_step, maximum_quantity?, subgroup_minimum?, subgroup_step?, subgroup_maximum?, created_by FK users, published_by? FK users, published_at? | UNIQUE(variant_id,version_number), UNIQUE(variant_id,id); effective fields freeze on publication |

Draft effective values are resolved live by services and for display when defaults change; draft materialized effective columns update on draft save and are not authoritative if family defaults change afterward. Nullable overrides inherit defaults; maximum_override_enabled=true with a null maximum_override explicitly means unbounded, while false means inherit and requires maximum_override to be null. Publication must store both configured override provenance and current effective values in one transaction. The family floor check still applies.

Family dictates price_basis: main_catering/per_pax, belly_lechon/per_set, food_pack/per_pack, pica_pica/per_set. Split and non-null subgroup fields are legal only for Food Pack; subgroup fields are all null by default. If restrictions exist, minimum and step are required, maximum optional. Pica-Pica is uniform.

Current pointer uses composite FK (id,current_version_id) from product_variants to variant_versions(variant_id,id). A trigger additionally requires published status. Clone provenance must reference the same variant and a published source. Initial draft has no source. Version numbers allocate under the owner lock. Multiple drafts are permitted; stale publication must explicitly acknowledge a newer current version using an expected current ID, otherwise return conflict.

Published metadata must be non-null exactly when status=published. Published version columns and owned rules/snapshots cannot be changed. A newer current publication does not mark the former one draft or mutate its status. Clearing a current pointer withdraws an offering and is audited.

## Package groups and frozen option facts

| Entity | Fields | Keys and behavior |
|---|---|---|
| selection_groups | id, version_id FK variant_versions, group_code, name, mode fixed/select_one/select_many, slot_kind rice/drink/dish/lechon/tray/bundle_item, minimum_count, maximum_count, unique_dishes=true, category_mode free/restricted, inclusion_count, sort_order | UNIQUE(version_id,group_code); inclusion_count is number of selected options per purchased unit, not kilograms/serving size; slot_kind makes family policy explicit |
| group_options | id, group_id FK selection_groups, offering_option_id FK offering_options, is_default=false, price_adjustment_minor=0, snapshot_dish_id? FK dishes, snapshot_category_id? FK dish_categories, snapshot_offering_name?, snapshot_dish_name?, snapshot_category_name?, snapshot_option_code?, snapshot_option_label?, snapshot_unit?, snapshot_quantity?, snapshot_serving_pax?, snapshot_serving_claim? | UNIQUE(group_id,offering_option_id); all mandatory snapshot fields populated at publication; nullable serving estimates/claims remain optional |
| group_category_rules | group_id FK selection_groups, category_id FK dish_categories, minimum_count, maximum_count | Composite PK; restricted-mode whitelist/quotas; no rows for free mode |
| group_dish_rules | group_id FK selection_groups, dish_id FK dishes, effect allow/deny | Composite PK; conflicting allow+deny rows impossible |

Snapshot dish/category identities remain referenced with RESTRICT. The option's parent offering and dish identity cannot be reparented after use in a publication; create a new option/offering instead. Editing names, serving facts or a dish's current category is allowed, but affects only future snapshots. Group quotas use snapshot_category_id and duplicates use snapshot_dish_id in published selection validation.

The group_option snapshot is the historical commercial description, not a second editable master catalog. On draft save/preview, compare current master values and flag stale snapshots; publication recomputes snapshots under locks. Neither new eligibility nor new options expand an existing publication.

Rules are version-owned through groups and remain immutable, including inserts into a published group. The same trigger protection applies to changing a child's parent from or into a published version. Publication validates references, family consistency, default selections, exact fixed counts, category quotas and satisfiability considering current availability and canonical uniqueness.

## Food Tray direct listings

| Entity | Fields | Keys and behavior |
|---|---|---|
| direct_listings | id, product_id FK products, offering_option_id FK offering_options, code, active=true, current_revision_id? | UNIQUE(product_id,code), UNIQUE(product_id,offering_option_id); only food_tray product/option pairs allowed |
| direct_listing_revisions | id, listing_id FK direct_listings, revision_number, status draft/published, draft_revision=1, cloned_from_id? FK direct_listing_revisions, price_minor, currency, price_basis per_tray, minimum_override?, step_override?, maximum_override?, maximum_override_enabled=false, minimum_quantity, quantity_step, maximum_quantity?, snapshot_dish_id? FK dishes, snapshot_category_id? FK dish_categories, snapshot_dish_name?, snapshot_category_name?, snapshot_offering_name?, snapshot_option_code?, snapshot_option_label?, snapshot_unit?, snapshot_quantity?, snapshot_serving_pax?, snapshot_serving_claim?, created_by FK users, published_by? FK users, published_at? | UNIQUE(listing_id,revision_number), UNIQUE(listing_id,id); same snapshot completeness, immutable publication and effective quantity rules as packages |

Composite FK (id,current_revision_id) references direct_listing_revisions(listing_id,id); trigger verifies published status. Clone provenance stays in the same listing. Listing product/option identity is immutable after first publication. A different tray size is a distinct offering option and listing, not a price mutation. Published tray options use unit=tray; snapshot quantity records the tray count represented by one listing unit. No price exists on direct_listings or offering_options.

Versioned direct listings share the publication protocol with packages, without requiring a generic repository or a universal sellable table. No order lines, reservations or persisted previews are added.

## Navigation

| Entity | Fields | Keys and behavior |
|---|---|---|
| catalog_categories | id, parent_id? FK catalog_categories, slug, name, visible=true, sort_order | Globally unique slug; reject self-parenting and descendant cycles; lock taxonomy edits to prevent concurrent cycles |
| category_direct_listings | category_id FK catalog_categories, direct_listing_id FK direct_listings, sort_order | Composite PK; browse current published revision |
| category_variants | category_id FK catalog_categories, variant_id FK product_variants, sort_order | Composite PK; browse current published version |

Navigation visibility is inherited: a hidden ancestor hides descendants from storefront browsing. Assignment is explicit, not inferred from a culinary category. Stable ordering is sort_order then id; tied sort values are allowed. Reparenting/renaming does not affect purchasing rules. Delete only unreferenced nodes or explicitly reassign children/attachments in a transaction.

## Media

| Entity | Fields | Keys and behavior |
|---|---|---|
| media_assets | id, disk, path, mime, size_bytes, width, height, alt_text?, uploader_id FK users, status processing/ready/failed/deleting, checksum? | UNIQUE(disk,path); positive bytes/dimensions for processed images; private original until ready |
| media_derivatives | id, media_id FK media_assets, preset, disk, path, mime, size_bytes, width, height, status processing/ready/failed | UNIQUE(media_id,preset), UNIQUE(disk,path); retryable deterministic presets |
| dish_media | dish_id FK dishes, media_id FK media_assets, is_primary=false, sort_order | Composite PK |
| offering_media | offering_id FK dish_offerings, media_id FK media_assets, is_primary=false, sort_order | Composite PK |
| product_media | product_id FK products, media_id FK media_assets, is_primary=false, sort_order | Composite PK |
| variant_media | variant_id FK product_variants, media_id FK media_assets, is_primary=false, sort_order | Composite PK |
| category_media | category_id FK catalog_categories, media_id FK media_assets, is_primary=false, sort_order | Composite PK; navigation category, not culinary category |

Each attachment table has a partial unique index on owner WHERE is_primary and UNIQUE(owner,sort_order). Reordering uses a transaction with temporary free positions to avoid immediate unique collisions. Primary selection/replacement locks the owner. Public fallback chooses primary ready attachment, then first ready attachment by order, then the documented fallback.

Delete checks every attachment table under an asset lock, marks deleting, then queues file deletion after commit. Attachment creation locks that same asset and rejects deleting assets. Physical deletion is idempotent/retryable; remove derivative/asset rows only after successful cleanup and a final reference check. Database/storage failures must not produce public broken images.

## Migration/entity dependency order

1. Users, roles, permissions, access junctions and append-only audit.
2. Culinary categories, product families, family defaults, dishes, eligibility.
3. Offerings, options, products, variants without current pointer FK.
4. Variant versions, groups, options/snapshots and category/dish rules.
5. Direct listings without pointer FK, then listing revisions.
6. Add composite current-pointer FKs, provenance checks, immutable-publication triggers and supporting indexes.
7. Navigation categories and typed listing/variant junctions.
8. Media assets/derivatives and all owner attachments.

Use PostgreSQL integration tests for constraints/triggers and migrations. Publication feasibility cannot be an ordinary row CHECK. No claim of migration validity is made until actual implementation/tests run.

Database design reference: [PostgreSQL constraints](https://www.postgresql.org/docs/current/ddl-constraints.html) explains composite foreign keys, row-local CHECK limitations, partial unique indexes and why referencing foreign-key columns need deliberate indexing.
