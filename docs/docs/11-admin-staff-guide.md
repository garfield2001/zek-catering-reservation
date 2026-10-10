# Admin and Staff guide (Phase 3.5)

## First run

Run migrations, seed system roles/permissions, and create the first Admin with the existing interactive provisioning command (see [Phase 1 setup](07-phase-1-foundation.md)). A blank commercial catalog is expected. Sign in and follow the Dashboard setup checklist. No food items, collections, packages or prices are preloaded. Staff accounts have no catalog privileges until Admin assigns them.

## Collections and food catalog

Open **Collections** and create the one primary catering collection. Choose its customer-facing name; the primary behavior is fixed. You may add unlimited optional collections and choose a supported configuration type for each: package-based, packed meal, fixed bundle or direct-sale. Two collections may use the same type while retaining independent food eligibility and products. Names may be edited without changing behavior. Disable a collection to pause new selection; archive it to retire it permanently while preserving references. An archived name can be reused by a replacement. The system currently supports one *current* primary, not a limit of one package variant.

Create **Food Categories**, then **Food Items**. A food item is one canonical record; assign its eligible collections rather than duplicating it. Within each collection, create an **Offering** and one or more **Serving Options** (unit, quantity and optional approximate capacity). Eligibility and active status are checked together for new package choices. Kilograms never imply guest capacity. Deactivation retains history but prevents new selection. Stable codes are technical identifiers; ordinary business navigation uses names.

## Package, meal and bundle drafts

Open **Package drafts**. Create a package product in an eligible collection, then add as many variants as needed. Configure each variant's draft price in integer centavos, quantity minimum/step/maximum, and uniform or permitted split policy. Add inclusion groups using the collection's eligible serving options. A package-based add-on can use fixed items and optional configurable choice groups; configure a kilogram slot only if relevant. A packed-meal collection uses per-pack pricing and can define fixed inclusions, required choice groups, upgrades and split allocations. Split groups must independently satisfy selections and their quantities must total the purchase; total quantity rules apply by default. A fixed bundle uses fixed inclusion groups only; its customers cannot substitute items and option premiums are disallowed. Use the draft preview to verify authoritative server-side calculations and configured constraints. The price preview does **not** publish or offer anything for customer purchase.

Direct-sale collections provide catalog identity and collection-specific food eligibility now. Their independent listing revisions, published prices and storefront will be implemented in a later phase; do not represent their items as package variants. Publishing all package drafts is also a later phase. No reservation or checkout workflow exists.

## Staff and Activity History

Admin has full system access. Use **Staff** to create accounts and explicitly grant only the permissions needed. Publishing and permission administration are not granted to Staff by default. The backend checks policies on every write, including direct HTTP requests. Review **Activity History** for actor, time, action, record and changed fields. Technical JSON is secondary and sensitive keys are redacted in the interface. Audit events remain append-only.

## Practical cautions

An inactive collection, food item, category, eligibility, offering or serving option cannot be chosen for a new preview. Existing references stay intact for history. Keep draft prices and quantities current before the future publication step. Never use a display name as an internal pricing selector. The [architecture amendment](10-phase-3-5-dynamic-collections.md) explains the stable behavior codes and the migration of historical five-family installations.
