# Phase 6.3 — Routed Main Catering Package Workflow

Status: implemented; final milestone verification is recorded in the Phase 6.3
handoff report. This refinement supersedes the Main Catering single-page
editing description in `30-phase-6-2-main-catering-editor.md`. It does not
change Add-on Package configuration, customer reservations, quotations,
payments, or notifications.

## Purpose and routing boundary

Main Catering now uses four persisted configuration pages rather than one
long editor:

1. **Package Details** — package name, description, and active status.
2. **Pricing & Guest Quantity** — per-guest price and package-owned quantity
   rules.
3. **Menu Configuration** — Food Item discovery, inclusions, exact category
   choices, and the total distinct-food count.
4. **Review & Save** — complete Draft review, direct links back to each
   section, and explicit publication.

Each successful save persists only its own facts and redirects to the next
page. A later page never fabricates missing pricing or menu data. The
workspace still provides the authoritative Draft, published-history and
archive views.

The ordinary HTML Main Catering entry points redirect to this route family:

- `/admin/catering-packages/create`
- `/admin/catering-packages/{product}/details`
- `/admin/catering-packages/{product}/pricing`
- `/admin/catering-packages/{product}/menu`
- `/admin/catering-packages/{product}/review`

The former generic editor's JSON representation remains an authorised
compatibility boundary for internal consumers, but it is not a competing
browser workflow. Add-on Packages retain their existing editor and stepper.

## Menu experience and business rules

The Main Catering Menu page begins with an empty Food Category selection. It
does not expose an initial **All eligible foods** browse state. After a
category is chosen, the page searches only that category and paginates up to
eight Food Items at a time. **Select this page** affects only that visible
page. Selected Food Items remain in a separate, category-grouped
configuration section even if the discovery filter changes.

Administrators can use the existing inline Food Item form from this page. New
Food Categories and Food Items return to the same Draft context without
replacing the other unsaved menu state. The new Food Item is made available in
the picker but is not selected until the Administrator explicitly selects it.

Within the saved configuration section, an Administrator can:

- remove a selected Food Item;
- mark an individual Food Item as **Always Included**;
- require a category as a **Customer Choice** and set one exact customer
  count; and
- set the total number of distinct foods in the menu.

The summary makes the union of Always Included foods, Customer Choice rules,
and the exact total visible. Existing legacy category ranges remain preserved
until an Administrator deliberately replaces them with an exact count. A
required category with only one selected eligible Food Item is promoted by
Laravel to an included item on save. Before the save, the UI explains the
pending promotion; after reopening, it displays the persisted inclusion.

Laravel remains authoritative. The routed endpoints retain all existing
policies, Draft revision checks, transactions, food eligibility, active-status
checks, exact-distinct-selection validation, Main Catering per-pax/uniform
rules, and the prohibition of per-food premiums, servings, sizes and
measurements.

## Validation and responsive feedback

Inputs use the shared field label, input and error relationship, with
`aria-invalid`, a programmatic error description, and a visible invalid
border. Errors are retained until a valid correction or the next server
response; successful section saves use server flash status. Success
notifications are non-intercepting so a narrow-screen status message never
blocks the next primary action.

The page navigation is ordinary linked navigation rather than a stepper.
There is no required visit order: an Administrator may return to a persisted
section, and the Review page names the missing or invalid facts that block
publication.

## Data, security and development environment

Phase 6.3 adds no schema object and therefore makes no Draw.io ERD change.
Published snapshots and audit records remain immutable. A normal Edit route
for Main Catering is redirected to the routed workflow; no published fact is
rewritten and no implicit revision is created.

The local development database `zek_reservation` remains untouched. The
existing owner-controlled migration `2026_10_24_000025_add_menu_category_choice_counts.php`
must still be applied to that development database before this checkout can be
tested there. All destructive automated verification uses only
`zek_catering_test` on `127.0.0.1:5432`.

The serial Playwright runner explicitly overrides Laravel's local config and
route cache paths for its PHP server, verifies the actual database identity,
and terminates its own direct PHP server when a run finishes. It therefore
does not reuse a developer's Vite server, Laravel route cache, or database.
