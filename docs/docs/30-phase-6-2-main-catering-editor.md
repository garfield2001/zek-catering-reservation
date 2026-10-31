# Phase 6.2 — Main Catering editor refinement

Status: implemented and verified. This record defines the approved Main
Catering editing experience; it does not change Add-on Package configuration,
customer reservations, quotations, payments, or notifications.

## Dedicated single-page editor

Main Catering Package create and edit routes continue to use the dedicated
Package Editor. They no longer use the package setup stepper, next/previous
controls, or forced stage progression. The page is arranged as four ordinary
sections: Package Details, Pricing & Guest Quantity, Menu Configuration, and
Review & Save. Add-on Package workflows retain their existing stepper because
they remain outside this refinement.

An incomplete Main Catering Draft is labelled **Complete Configuration** in the
directory. It opens the same resource-bound editor as an ordinary Draft; it is
not a separate lifecycle action.

Before menu and pricing facts exist, **Save package details as Draft** creates
the ordinary idempotent Package Product and its initial Draft through the
existing catering endpoint. Its inline explanation makes the boundary explicit:
it saves identity only, while unsaved menu and pricing entries stay in the
current page until the complete **Save Draft** action is used. This permits a
safe stop-and-return workflow without weakening the server validation applied
to a commercial configuration save or publication.

## Menu semantics

The Main Catering Menu Configuration separates two different facts:

- **Always Included** lists Food Items that are included without a customer
  decision.
- **Customer Choice Groups** list the Food Category, its selected available
  Food Items, and one exact **Customer must choose** count. The editor writes
  the existing minimum and maximum category-rule columns to that same exact
  value; Laravel remains the authoritative validator.

The Food Picker remains category-filterable and searchable. Filtering affects
only the visible results, never the package selection. The selected-Foods list
and menu summary retain names across filters so an Administrator can remove or
review an item without locating it again.

Existing Drafts with unequal category-rule minimum and maximum values are
explicitly marked as a preserved legacy range. The editor does not silently
narrow the range. An Administrator must enter a replacement exact count before
that rule changes. Published snapshots remain immutable and are not rewritten.

When a required category has one allowed Food Item, the existing server
invariant promotes it to an included item on save. Before saving, the UI says
that it will be automatically included. After reopening, the editor restores
the persisted `is_fixed` fact as Always Included rather than presenting a
fictional customer choice. A category with several allowed Food Items does not
qualify merely because all but one were manually marked Always Included.

## Development database transition

The editor query requires the category-choice count columns from
`2026_10_24_000025_add_menu_category_choice_counts.php`. A read-only inspection
found this migration pending in the owner's `zek_reservation` database. That
schema mismatch is the confirmed cause of the reported Continue Setup request
failure: loading a Draft joins data that the older table does not contain.

No development database operation is authorized or performed by this phase.
After a backup and owner review, the owner must apply the pending forward
migration with the normal Laravel migration procedure before testing this
checkout against `zek_reservation`. All automated destructive verification uses
only `zek_catering_test` on port `5432`.

## React controlled values and error feedback

The affected category and measurement-unit selects now normalize absent saved
values to the empty option. This avoids passing nullable values to native
controlled `<select>` elements during fresh creation, historical Draft loading,
and data refreshes. Generic non-validation Inertia failures retain the page and
tell the operator that unsaved input remains available, without exposing server
internals.

## Schema and ERD

Phase 6.2 adds no tables, columns, keys, constraints, triggers, or migrations.
The current editable Draw.io ERD remains accurate and intentionally has no
Phase 6.2 change. Migration `000025` is an already existing Phase 6.1 schema
dependency, not a new migration introduced by this refinement.

## Verification

The isolated PostgreSQL suite exercises incomplete Draft creation, exact
category choices, singleton promotion and resaving the promoted inclusion. The
serial Chrome suite covers the Main Catering single-page editor, category
filtering, publication history and the safe incomplete-Draft return path. The
browser harness verifies its `zek_catering_test` connection before each
destructive run; it never targets the owner's development database.
