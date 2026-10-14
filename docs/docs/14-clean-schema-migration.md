# Clean fresh-install schema migration strategy

The application is unreleased, so the project-owned migrations are the authoritative fresh-install baseline. They no longer create a Product → Variant → Variant Version chain and then transform it. A new installation creates the approved Package Product → Package Version hierarchy directly.

## Baseline order

1. `2026_10_09_000001_create_access_tables.php` and `2026_10_09_000002_create_audit_events_table.php` establish accounts, permissions and append-only audit events.
2. `2026_10_09_000003_create_master_catalog_tables.php` creates Food Categories, Collections, Food Items, eligibility, Offerings, Serving Options and typed Collection ordering defaults, including identity and availability constraints.
3. `2026_10_09_000004_require_family_order_defaults.php` completes the one-default-per-Collection invariant.
4. `2026_10_09_000005_create_package_tables.php` creates Products, Package Versions, Selection Groups, Group Allowed Options, rules and shared package menu tables. Version-owned minimum/increment/maximum replace all override fields. Capacity is separate from optional per-food add-on measurements and price adjustments. Package Version foreign keys are created before the Product current-publication pointer.
5. `2026_10_11_000008_clarify_permission_descriptions.php` and `000010_add_package_lifecycle_permissions.php` retain the supported permission catalog and business-facing descriptions.
6. `2026_10_11_000009_publish_package_versions.php` adds publication pointer, ownership, immutability, archive and technical-code guards.
7. `2026_10_11_000011_allow_configurable_package_inclusions.php` adds cross-table package configuration checks for supported collection behaviors.

The former `2026_10_09_000005_create_package_drafts.php`, `2026_10_10_000006_add_catalog_presentation_fields.php`, `2026_10_10_000007_support_dynamic_collections.php`, `2026_10_12_000012_replace_variants_with_package_versions.php` and `2026_10_12_000013_add_main_menu_configuration.php` are not part of the clean baseline. Their responsibilities are represented directly in the baseline or final constraint migrations; no duplicate schema objects or obsolete Variant tables/functions remain. The former `tests/Support/phase4_upgrade_seed.php` and `phase4_upgrade_verify.php` helpers were removed because the unreleased application deliberately has no in-place upgrade path to exercise.

## Current development state and Milestone B forward migrations

On 2026-10-14, read-only inspection confirmed the owner reset development: all 12 baseline migrations, the always_include column, 1 user, 1 category, 3 Food Items, 4 services, 4 Products and 4 Package Versions are present. The historical transition below is resolved. No development writes were performed.

Milestone B adds three functional forward migrations, without rewriting those 12 applied baseline files:

- `2026_10_14_000012_extend_package_configuration.php`: atomic creation fingerprint; Main explicit fixed intent; optional per-pack semantics for generic package services. Replaces the existing validation function, not its triggers.
- `2026_10_14_000013_add_food_image_metadata.php`: nullable file references/dimensions with complete-or-null CHECK.
- `2026_10_14_000014_create_individual_listings.php`: listings, versions, restrictive ownership FKs, indexes, immutable publication/current-pointer guards and predefined listing permissions.

The isolated `tests/Support/milestone_b_upgrade.php` creates the 12-migration baseline, publishes a representative package, then applies these three forward migrations and verifies unchanged IDs and snapshot. Fresh installs also apply all 15 migrations directly, with no Variant conversion chain.

**Owner approval still required:** take a development backup, verify its target, then run ordinary `php artisan migrate` (not fresh/reset/seed). Review the three pending migrations first. Do not discard or reprovision the existing Administrator. Rollback deliberately refuses data loss if listings/images/new configuration facts are in use; preserve/export data and plan a controlled reversal instead.

## Historical development transition (resolved by owner's reset)

The local development database is not reset or migrated by this change. Read-only inspection on 2026-10-13 found `zek_reservation` already uses Product/Package Version tables and records all 12 baseline migrations in batch 1. It has 1 user, 1 Collection, 18 Food Items, 1 Product, 2 Package Versions (1 Published, 1 Draft), 36 menu-food rows and no Selection Groups. There are no Variant tables. However, its existing `package_version_food_items` lacks the new `always_include` column. Recorded migration names therefore do not prove schema parity, and ordinary `migrate` will not apply rewritten baseline definitions. No other active database transaction was observed. At that time the proposed explicit transitions were:

- **A — Preserve records:** take a verified export, compare the full existing schema to the final baseline, map any missing fields (including explicit versus derived fixed inclusion intent), and import into a separate fresh-baseline database. Validate record counts, IDs, relationships, publication pointers and unchanged historical snapshots before an approved switch. If an older database still has Variants, its separate conversion must also map those records. Do not update immutable published rows in place or assume a default can reconstruct add-on intent.
- **B — Reset:** after explicit authorization to discard local records, run `migrate:fresh --env=local` and seed again.

Neither option is performed automatically. The isolated `zek_catering_test` database is the verification target and may be refreshed by tests.

## File inventory for the uncommitted consolidation

- Retained unchanged: 000001 access, 000002 audit, 000004 required catalog defaults.
- Rewritten: 000003 master catalog (absorbs presentation/dynamic Collection schema), 000009 publication constraints, 000011 behavior/configuration constraints. Their schema objects are created once, in final form.
- Updated descriptions: 000008 permission clarity and 000010 lifecycle permission definitions.
- Created: `2026_10_09_000005_create_package_tables.php`, the direct Product/Package Version and shared/advanced configuration baseline.
- Removed: `2026_10_09_000005_create_package_drafts.php`, `2026_10_10_000006_add_catalog_presentation_fields.php`, `2026_10_10_000007_support_dynamic_collections.php`.
- Earlier partial uncommitted 000012 Variant conversion and 000013 Main menu patch are absent, superseded by the baseline; no new historical conversion chain is introduced.
- Framework infrastructure migrations are retained. Old Phase 4 upgrade seed/verification scripts are removed because the owner explicitly chose a fresh baseline, not an incremental upgrade path.

The retained catalog-default record is not a package quantity constraint. Current package rules and copied values are independent of it. Published and abandoned children remain protected regardless of which configuration model they use.
