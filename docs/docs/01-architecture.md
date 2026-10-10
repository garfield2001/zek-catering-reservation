# Catering Catalog Architecture V3 — Approved Clarifications

Current-state note: the original five named family examples and fixed package examples below are historical V3 scenarios, not required records or seeded commercial data. Phase 3.5's dynamic collections, empty-catalog first run, behavior-driven configuration, and the approved [domain vocabulary](domain-terminology-audit.md) supersede conflicting prose. Physical ERD names such as `product_families` and `dishes` remain compatibility identifiers.

Status: V3 approved; Phases 1 and 2 (access foundation and master catalog) are implemented. Later phases remain unimplemented. Updated 2026-10-09 after client review. Read with the entity definitions and acceptance criteria. Approved business rules take precedence over examples. No production prices are inferred from fixtures.

## Scope and architecture

Laravel, Inertia.js, React/TypeScript, PostgreSQL and Tailwind; modular monolith and feature-organized frontend. Thin controllers, Form Requests for transport, Actions for workflows/transactions, dedicated domain validators/services for business rules, Policies, Eloquent persistence, and query classes for complex reads. No generic repositories, generic JSON rules engine or excessive abstraction.

Scope: canonical dishes, eligibility, offerings, five families, package configuration, direct listings, transient server price previews, navigation, media, administration and audit. Customer accounts, reservations, quotations, payments, notifications, scheduling and inventory remain excluded. Future order lines are explanatory only; no order/reservation relationships are created now.

## Five families and four add-on families

| Family code | Catalog behavior | Price basis |
|---|---|---|
| main_catering | Configurable variants; fixed/selectable inclusions; initial minimum 150 | per_pax |
| belly_lechon | Add-on package variants with required lechon sizes and selectable tray dishes | per_set |
| food_pack | Add-on packed meals with minimum quantities, drink premiums and uniform/split modes | per_pack |
| food_tray | Add-on individual tray dishes through direct listings; no package variant needed | per_tray |
| pica_pica | Add-on fixed bundles with administrator-defined inclusions | per_set |

All four add-on families are independent catalog offerings, eventually selectable alongside main catering. Add-ons do NOT mean Food Tray listings alone. Products classify offerings; variants identify package choices; published versions/revisions hold commercial facts.

For the future reservation journey, exactly one valid `main_catering` selection is the required primary product; the other four families are optional additions to it and cannot initiate a catering reservation on their own. Inquiry, quotation, availability confirmation and reservation persistence are outside Phase 3. The stable `code` and immutable `is_addon` identify this rule; customer-facing family names, descriptions and display order can change without changing pricing or eligibility behavior. The five supported family identities are seeded, not freely creatable business engines.

## Canonical catalog and selection

One canonical dish may be eligible for any subset of the five families. Family-specific preparations are dish offerings with named serving options. Multiple preparations per dish/family are allowed. A composite FK ensures an offering's dish/family pair exists in family eligibility. Dishes have one primary culinary category in this phase. Website navigation uses separate explicit assignments. Stable dish codes and administrative duplicate review preserve canonical identity; names alone are not unique identity.

- Eligibility requires the correct family, allowed group option, category rules and no exclusion. Draft option pools may be derived; publication ALWAYS materializes the explicit option set.
- Modes are fixed, select_one and select_many. Each fixed group has one option, min=max=1, automatically included. Multiple fixed inclusions use multiple groups. Required select-one has min=max=1; optional select-one may have min=0, max=1.
- Distinct canonical dishes are required by default within a selection group. Different dishes from one category are allowed. Separate groups may intentionally repeat an inclusion. If unique_dishes=false, the payload carries positive counts per option; occurrences count toward group/category limits and premiums. Preparation quantity is distinct from selection count.
- Free groups have no category quotas. Restricted groups whitelist their category-rule rows and enforce their quotas. Dish allow rules form an additional whitelist when present. Deny always wins; allow never bypasses family/category restrictions.
- Publication feasibility counts canonical dishes, not option rows. Defaults must obey quotas and uniqueness, and cannot exceed maxima. Fixed groups default their sole option; required select-one has exactly one default. Select-many defaults may be incomplete but selection must meet its minimum.
- Belly Lechon fixed 3kg versus selectable 3/4/5kg are configurations of the same group, without changing variant or tray slots. Pica-Pica has only fixed groups and zero option premiums; its administrator-defined total price is the base set price.

## Food Pack split rules

Uniform is the default. Split is explicitly enabled on a meal variant version. Each allocation uses that same version and independently satisfies required selections. Positive integer allocation quantities sum exactly to the total. Different meal variants are separate future order lines and cannot be mixed in one configuration.

Minimum, step and maximum apply to the total by default: q >= min, (q - min) % step = 0, and q <= max when set. A total of 10 with minimum 10 and step 5 can split into 3 and 7 packs. Identical normalized allocations are merged before optional subgroup restrictions are validated.

Typed nullable subgroup minimum/step/maximum fields reserve future per-group restrictions without redesign. Initially all are null; no subgroup-rule editor is required. When enabled later, subgroup minimum and step must both be positive; maximum is optional and at least minimum. Total rules always apply. These fields freeze in the version.

## Publication and current availability

Packages use variant_versions; Food Tray uses direct_listing_revisions. Both support draft cloning, validation, publication and atomic owner current-pointer updates. Moving a pointer leaves prior published records unchanged. Owners can be made unavailable separately. Only the current publication is selectable for new public previews; historical/admin views retain old records.

Freeze validation, pricing and fulfillment facts: family, price basis, money, effective quantity rules, split policy, group rules, explicit options, dish/category identity, preparation and option names, serving units/sizes/claims and inclusion quantities. Snapshot columns are intentional historical denormalization. Master edits cannot change frozen facts. Photos, navigation and noncommercial promotional text remain live presentation data.

Availability is checked separately against active family, product/variant/listing, dish, offering, option, culinary category and eligibility. Inactivation blocks new selection/publication without erasing history. If remaining options cannot satisfy a required group, the package is unavailable; never substitute silently. New publication resolves snapshots from current master data and validates eligibility. Draft cloning preserves references; draft previews flag stale/inactive references and publication reports changed commercial facts.

Database triggers and application guards forbid published parent/child updates, deletes, child inserts and reparenting. Draft-to-published is allowed after validation; published-to-draft is forbidden. Database constraints/triggers enforce current-pointer ownership and published status. Publication locks owner, draft and referenced mutable catalog rows in deterministic order; catalog edits/deactivation follow the same lock protocol. A draft revision detects stale edits. Snapshot, status, pointer and successful audit event commit together. Media jobs dispatch after commit.

## Money, quantity and pricing

All money is nonnegative integer centavos in BIGINT columns, PHP only; 100 centavos = one peso. No monetary floats or negative adjustments. group_options.price_adjustment_minor is the only package-option surcharge authority, specific to that package version. Charge it per purchased pax/pack/set and selected occurrence, never multiply it by serving size.

Food Tray's current published listing revision is its sole price authority. Offering options have NO standalone price. Draft prices never leak into public calculations. Check arithmetic overflow and reject values/totals outside signed 64-bit range. Serialize monetary values as decimal strings to avoid JavaScript integer precision loss. Laravel calculates authoritative totals; the frontend formats and displays them.

| Family/mode | Formula |
|---|---|
| Main Catering | guests × (base per pax + selected per-pax premiums) |
| Belly Lechon | sets × (base per set + selected per-set premiums) |
| Food Pack uniform | packs × (base per pack + selected per-pack premiums) |
| Food Pack split | sum(allocation packs × allocation effective per-pack price) |
| Food Tray | sum(listing quantity × published revision price) |
| Pica-Pica | sets × fixed base set price |

Order counts are positive integers. Serving sizes use exact NUMERIC quantities. Each group defines an inclusion count of its selected option per purchased price-basis unit: one 3kg option per set, one tray per selected dish per set, or one rice portion per pack. Fulfillment amount = purchased units × selected occurrences × inclusion count × option size. Approximate capacities never replace quantity rules or add across different meal components. Lechon kg implies no pax capacity.

Family defaults resolve through nullable variant/listing overrides and freeze at publication. Main Catering initially has minimum 150; its family enforcement flag prevents variants lowering the floor. Only Admin can change the floor/flag, with audit. Existing publications retain frozen rules. No cross-family aggregate add-on minimum is invented.

## Administration, media and operations

Active Admin has full permission access, subject to business invariants and immutability. Deny inactive accounts first. Staff evaluation: explicit deny, explicit grant, role grant, default deny. Publishing and permission administration are disabled for Staff by default. Only Admin deliberately delegates them. Delegated Staff cannot assign Admin, edit Admin accounts, change protected family floors or grant powers they do not possess. Protect the last active Admin.

Price permissions cover nested changes, imports, base prices, option premiums and listing revisions. Publishing rechecks authorization for commercial changes relative to the current publication, including initial prices. Staff-only session authentication, no public registration, CSRF protection, throttling, secure cookies and session revocation are required. Notification-based recovery is deferred; recovery procedure and MFA must be settled before launch.

Audit successful commercial edits, publication, availability, media and access changes transactionally. Audit rows are append-only, contain redacted before/after data and actor/request context, and survive archival. Denials go to security logs. Never log credentials or session secrets.

Media validates magic bytes/MIME, dimensions and limits; generates safe names; re-encodes approved raster formats; rejects SVG/executable uploads; tracks processing and derivatives. Only ready media is public. Offering image falls back to dish then placeholder; variant to product then placeholder; category to placeholder. Images are optional for publication. Typed attachments, one primary per owner and ordering avoid ambiguity. Reference checks and a deletion state prevent attaching assets during after-commit deletion. Back up database and originals and verify restoration/job retries.

## Remaining business decisions

Before production publication, approve the complete configuration matrix: actual prices/premiums, named variants, inclusions/counts, allowed upgrades, dish pools and minimum/step/maximum values beyond the approved Main Catering floor. Confirm serving claims and final imagery. Examples are not commercial approval. The four add-on families, split defaults, money representation, frozen publications and default Staff restrictions are settled.

Implementation after Phase 3 remains separately gated. Phase 3 adds administrator-only package drafts and price previews; publication remains Phase 4. Food Pack now defaults to a 10-pack minimum per the explicit Phase 3 instruction. See 05-database-entities.md, 06-implementation-plan.md and the 07–09 implementation notes.
# Phase 3.5 dynamic-collection amendment (2026-10-10)

The five named V3 families below describe configurations and historical upgrade identities, not required seeded business records. A fresh installation has zero commercial collections. `product_families` is retained as the durable collection identity so existing catalog/package foreign keys stay valid. Each current collection has an immutable role and one controlled `behavior_type`: `main_package`, `package`, `packed_meal`, `direct_sale`, or `fixed_bundle`. At most one non-archived primary exists; any number of add-ons may share a behavior. Names and descriptions are presentation data. Collection-specific food eligibility and offerings continue to use the family ID, not name or behavior code. See [Phase 3.5 design](10-phase-3-5-dynamic-collections.md) for migration and availability details. Publication, direct-sale listing management and booking remain outside Phase 3.5.
