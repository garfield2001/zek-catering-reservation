# Milestone A — Admin/Staff UI modernization

Historical milestone report: [Milestone B](18-milestone-b-commercial-catalog.md) now supersedes the image/listing deferrals and separate package-editing steps below. These statements describe Milestone A at its verification point, not current capability.

This milestone builds on the uncommitted Product → Package Version refactor documented in [the preceding report](16-final-refactor-verification.md). It does not begin reservations, Direct Sale publication or Phase 5. The PostgreSQL baseline and editable ERD require no additional schema changes for these UI improvements.

## Problems addressed and implementation

- Replaced the custom dialog behavior with one Radix Dialog/Sheet foundation: focus containment, Escape, focus restoration, labelled headings and constrained scrolling. Radix AlertDialog handles publication, archive, permission changes, Administrator transfer and unsaved package navigation. Native before-unload protection remains appropriate for closing the browser itself.
- Standardized restrained emerald/slate styling, focus rings, button variants, form errors, responsive spacing and Lucide actions. Native labelled selects/checkboxes remain where accessible and sufficient; unused component wrappers were not added.
- Renamed Overview to Dashboard. The responsive sidebar, mobile navigation sheet, active section, breadcrumbs, account menu and navigation progress share one permission-filtered layout. Dashboard counts use real records and are omitted server-side without the corresponding access.
- Reorganized Food Catalog into collapsible category sections with counts, compact food cards, contextual Add Food, global creation, server-side search/filtering and pagination. Category preselection is contextual. The Food Item dialog supports inline category creation without nesting dialogs.
- `SaveDishRequest`, `AvailableCategoryName`, existing `SaveCategory` and `SaveDish` implement validated, authorized, transactional category+food creation. Food failure rolls back the category and its audit event. Malformed types, duplicate names and conflicting category inputs are rejected.
- Reusable FoodImage supports loading, absent/broken image fallback and replacement sources. Existing foods honestly show placeholders: upload/storage remains deferred.
- Package cards show saved draft/published prices, selection counts, minimum quantities and status. The compact four-step workspace exposes unsaved/saved state and field-error attention states. These are editing indicators, not a substitute for backend publication readiness.
- Category Select All/Clear All only changes permitted foods; it does not alter required-category or explicit fixed-inclusion intent. Existing exact-count, feasibility, frozen publication, quantity, pricing and add-on behavior remain backend-authoritative.
- Add-on creation first distinguishes Packages from Individual Items. Packages retain configurable, packed-meal and fixed-bundle behavior choices. Individual Items maps to the existing `direct_sale` contract and explicitly states that listing prices/publication/purchases are unavailable. Names remain presentation data.
- Sonner is the sole toast system. Success comes from backend flash data, with per-response identity deduplication; validation is inline with a general warning, slower writes show loading, and request failures show errors. Separate successful saves may legitimately produce separate identical messages.
- One small TanStack Table foundation serves Team & Access. Search, active status, whitelisted ordering and pagination execute on Laravel; policies still determine row actions. Category/package browsing remains card-based; Activity History retains its working filtered presentation.

## Dependency decisions

Installed Radix Dialog, AlertDialog and DropdownMenu, Sonner and TanStack React Table (v9 API). Reused React, Inertia forms, TypeScript, Tailwind and Lucide. Components are local Radix/Tailwind compositions, not a claim that a shadcn CLI generated the application. Kept one form-management approach: no React Hook Form/Zod. No SweetAlert, second toast/modal library, calendar, charting, animation or upload package.

References: [Radix Dialog](https://www.radix-ui.com/primitives/docs/components/dialog), [TanStack React Table quick start](https://tanstack.com/table/latest/docs/framework/react/quick-start). The checked-in lockfile records exact dependency versions.

## Verification

Executed on 2026-10-14. All destructive database tests are guarded to uncached PostgreSQL `zek_catering_test`, port `5432`. Browser HTTP port `8014` is separate from the database port. The development database and account permissions are untouched.

| Check | Latest executed result |
|---|---|
| Full PHP/PostgreSQL suite | 123 passed, 843 assertions, 0 failed |
| Vitest | 7 passed across 4 files, 0 failed |
| TypeScript / ESLint | Passed, no diagnostics |
| Larastan | Passed, no errors |
| Pint / Prettier | Passed |
| Production build | Passed |
| Route inspection | 76 application routes; 0 obsolete Variant routes |
| git diff --check | Passed |
| Chrome browser suite | 13 passed, 0 failed (2.1 minutes); final package-copy change rechecked with 2/2 focused tests |

The added PHP tests cover atomic inline category/food creation, failure rollback including audits, malformed/duplicate/conflicting category input, denied Staff writes, permission-filtered dashboard data and server-side directory filtering/order validation. Added Vitest tests cover image failure/replacement and flash deduplication versus separate saves. Browser coverage includes every existing service behavior, scoped package navigation, menu/category bulk selection, independent quantities, publication confirmation, persisted historical revisions/archive outcomes, Staff access, inline category creation and mobile keyboard/focus behavior.

Earlier verification failures were investigated rather than omitted: an unsupported Laravel validation rule was replaced with conditional prohibition; the TanStack v9 generic constraint was corrected; Sonner assertions replaced obsolete banner selectors; separate-save notifications are not mistaken for duplicated events; image-test cleanup was added; package tests wait for confirmed saved state before advancing. Final passing runs supersede those failures. The browser test router deliberately ignores a local Vite hot marker and uses the production build without deleting the owner's dev-server marker.

Visual review inspected Dashboard, inline category/food dialog, mobile catalog/navigation, publication review and published-history screenshots. Dialog focus restoration and viewport overflow are automated assertions. This is representative Chrome/mobile-viewport coverage, not exhaustive screen-reader/device certification. Images remain in ignored `test-results/`, not committed source.

## MANUAL TESTING READINESS

**READY FOR MILESTONE TESTING — isolated environment only.** The implemented Admin/Staff workflows and integrated package publication journey are ready for owner review; this is not full customer acceptance or production readiness. Development database migration/reset is **not authorized**. The previously documented development-schema mismatch remains an owner transition decision; test using the isolated environment until that is resolved.

Implemented workflows to exercise: Admin login → Dashboard → Food Catalog → Add Food Item → Create new category inline → save → contextual category Add Food. Then create a Catering Package, select permitted foods, configure required categories, save menu, set price/quantity, review, confirm publication and inspect history. Staff sees only supported assigned capabilities; unavailable actions explain the relevant restriction.

Deferred: image uploads, individual listing publication, customer inquiries/reservations, quotations, payments, scheduling, inventory and operational analytics. Advanced internal subgroup/selection rules retain the boundaries in the preceding report. Automated success is not owner acceptance or commercial-production readiness.

## Repository and owner handoff

The working tree includes both the earlier domain/schema consolidation and this UI milestone: 112 modified, 33 deleted and 54 untracked Git entries at closure (untracked directory entries can contain multiple files). Nothing is staged. The changes are appropriate for owner review as a combined milestone; inspect the documented removed legacy paths and baseline migrations before committing. Environment files, the Vite hot marker, build output and browser artifacts remain ignored. No Git mutations were performed.

No blocking automated failure remains. The development-data transition is still an explicit owner decision, not an authorization to reset it. Light mode is the supported presentation; a dark theme, exhaustive assistive-technology testing and multi-browser/device acceptance are not claimed. Broader customer acceptance requires the separately authorized transaction milestones. Image storage and Direct Sale listings are deferred functional work, not working UI capabilities.

Suggested Conventional Commit subject:

`refactor(admin): unify package configuration and modernize workspaces`

Suggested copy-ready body:

- Consolidate the unreleased PostgreSQL baseline around Collection → Product → Package Version ownership; remove obsolete Variant structures and competing editors.
- Preserve publication snapshots, ownership/immutability constraints, draft revisions, concurrency protection, live availability checks and audit history.
- Unify Main Catering and compatible add-on menus with exact food counts, required categories, fixed inclusions, independent quantity policies and integer-centavo pricing.
- Modernize Dashboard, responsive navigation, Food Catalog, package configuration and service-scoped add-on workspaces.
- Add transactional inline category/food creation and category-level food selection controls without changing required/fixed semantics.
- Standardize accessible Radix dialogs, lifecycle confirmations, Sonner notifications, image placeholders and TanStack-powered Team & Access listing.
- Preserve server-side permissions and last-Administrator protection; restrict dashboard data and catalog writes by policy.
- Verify 123 PostgreSQL tests / 843 assertions, 7 Vitest tests and 13 Chrome workflows, plus static analysis, formatting, types, routes and the production build.
- Update specifications, owner guidance, migration transition documentation and editable ERD while leaving development data untouched.
- Explicitly defer image uploads, individual listing publication and customer transaction modules.
