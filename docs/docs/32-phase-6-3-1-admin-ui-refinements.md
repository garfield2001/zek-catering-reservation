# Phase 6.3.1 — Admin UI refinements

Status: implemented pending owner acceptance testing. This is a focused
interface refinement over the routed Main Catering workflow and Food Catalog;
it introduces no schema, commercial-policy, publication-lifecycle, or Draw.io
ERD change.

## Main Catering menu configuration

The category picker remains category-first: it never defaults to an all-food
view. Its shared searchable control presents about five rows at once, filters
the full option set, scrolls the keyboard-active option into view, closes on
Escape, and closes immediately after a keyboard or pointer selection.

Selected foods are summarized by category rather than expanding every category
on the page. The compact category chips include selected-food counts. The page
shows one active category summary at a time with previous/next navigation;
selecting a chip opens **Configure {Category} Foods**. That modal edits the
same unsaved Draft state: removal, Always Include, category Customer Choice,
its exact count, and legacy-range replacement are not persisted until the
existing Menu save action runs. Closing the modal does not discard or save
those parent-form edits.

The Customer Choice toggle and exact-count field are deliberately grouped in
the configuration modal. Existing legacy minimum/maximum ranges are shown as
preserved and only become an exact count after an explicit replacement.

**Add Food Item** is again visible in Find & Select Foods. Its existing form
can create a new Food Category when the current user holds catalog-management
permission. The active discovery category is preselected where available; a
successfully created Food Item joins the in-memory picker without replacing
the package Draft. Creation does not select the new Food Item automatically;
the Administrator makes that explicit choice.

## Food Catalog

List view now defaults to five server-paginated rows. The row-count selector
is below the table and offers 5, 10, 30, 50, and All. It retains the current
search and filters through query strings. `All` is evaluated from the full
filtered PostgreSQL result by the existing `FoodDirectory` query rather than
from the visible browser page.

The prior List-only Sort select is replaced by accessible sortable Food Item,
Category, and Availability headers. Each cycles ascending, descending, then
the default ordering. Laravel performs each sort before pagination with a
deterministic secondary order. Existing legacy sort query strings remain
accepted for shared links. The compact catalog-category filter reuses the
shared SearchableSelect; short status controls remain native selects.

Food thumbnails in List view use the existing FoodImageViewer. An available
image is a separately focusable button that opens the existing larger-image
dialog; missing images continue to use the existing non-clickable placeholder.

## Shared accessibility and scrolling

Shared searchable lists, shared modals, and the selected-category modal use a
narrow `subtle-scroll` style. The scroll gutter remains stable; thin slate
thumbs appear on pointer hover or keyboard focus, while coarse-pointer devices
retain a visible thumb. It does not style the application viewport. Radix
Modal focus management, close handling, and unsaved-form protection remain
unchanged.

## Verification scope

Focused Vitest coverage covers bounded searchable selection, immediate close,
Escape, keyboard selection, modal scroll class, category-modal configuration,
and selection retention. PostgreSQL feature coverage verifies server-side
catalog sorting, effective availability ordering, page sizes, and All results.
Browser coverage continues to use the serial isolated `zek_catering_test`
runner; no test or implementation changes the development database.
