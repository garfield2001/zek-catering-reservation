# Milestone B — commercial catalog completion

Verification date: 2026-10-15. The owner's Milestone B request supersedes earlier image/direct-listing deferrals and permits Main Catering explicit fixed inclusions. Customer transactions remain deferred. Owner acceptance is separate from automated verification.

## 1. UI/UX problems resolved

One cohesive editor replaces separate creation/menu/pricing saves. Cards distinguish Edit/Continue Setup, View Details and Publication & History. Contextual food creation preserves unsaved input. Staff creation controls require editing plus pricing; editing a publication without an existing Draft requires history access. Unavailable actions explain restrictions and next steps.

## 2. Package creation and editing

Details → Menu → Pricing & Quantity → Review & Save. Required fields gate progression; backward movement retains input; existing packages permit direct step navigation. SaveCompletePackageRequest, SaveCompletePackage and PackageEditor provide validation, atomic workflow and queries. Failed saves roll back package/version/menu/success audits. Actor-bound fingerprints reject mismatched creation retries. GET/navigation creates no records. Saving never publishes; successful published edits create a new Draft. Leaving warns about unsaved changes; there is no autosave.

## 3. Package details

DetailsController, PackageDetails and ConfigurationSummary render saved commercial terms, category-grouped foods, fixed intent, quantities, availability and publication metadata. Published configuration is the default; authorized Draft preview remains separate. The shared responsive dialog supports Main and add-on packages. Package-specific image uploads are not supported.

## 4. Menu selection

Food tiles provide thumbnails/placeholders, selected state, category Select All/Clear All, required categories and Always Include. Automatic sole-required-category foods and explicit fixed foods form a distinct union, counted once toward the exact total. Required means at least one, not exactly one. Impossible configurations fail Laravel validation. Main still rejects food quantities and surcharges; add-on measurements retain their separate meaning. Frontend summaries are advisory, not authoritative validation.

## 5. Food image workflow

Create/edit upload, local preview, replacement, removal and enlarged viewer are implemented. JPEG/PNG/WebP: maximum 5 MB and 3000 × 3000 pixels. Decoding/re-encoding strips metadata; generated JPEG full images and thumbnails are bounded to 1600 and 360 pixels respectively. Private Laravel storage, generated names and controlled responses avoid trusting client paths. SaveDish authorizes/audits changes; nested rollback removes new files and committed replacement removes old files. Missing images show initials. Images remain current presentation, not frozen commercial facts. Crash-proof orphan reconciliation is not claimed; unexpected cleanup failure emits a generic operator warning.

## 6. Add-on Services

New services expose Sold by Package and Sold Individually. Editable names never select behavior. Shared package configuration supports independent purchase rules, fixed/flexible menus and optional measurements/capacity. Advanced packed-meal settings select per-pack pricing and uniform/split choices. Legacy packed-meal/fixed-bundle codes deliberately retain their contracts. Minimum quantity never determines selling method.

## 7. Individual listings

IndividualListing → ListingVersion models Food Items and optional serving sizes with independent price/unit/minimum/increment/maximum. Sizes are separate listings, not Product Variants. Sources must match the service and eligibility. Saved source identity is immutable. Draft editing, publication review/confirmation, immutable history, restoration into higher Drafts, disabling and archiving are implemented. Images inherit the Food Item; no listing-specific image override or checkout is claimed.

## 8. Dependencies

Reused Laravel, Inertia, React/TypeScript, Tailwind, Radix, Sonner, Lucide and TanStack Table. No additional Milestone B dependency was installed. PHP GD handles image decoding. Existing Milestone A dependency changes remain uncommitted.

## 9. Backend/database changes

Thin controllers, Form Requests, Actions, Policies and focused queries remain the architecture. Packages retain Collection → Product → Package Version. Integer-centavo money, ownership FKs, indexes, transactions, optimistic revisions and one-Draft constraints remain authoritative. Listing permissions are predefined and not automatically granted to Staff.

Three new forward migrations preserve the owner's current 12-migration baseline:

- 2026_10_14_000012_extend_package_configuration.php: creation fingerprint; replacement of the existing configuration validation function for Main explicit fixed intent and generic per-pack mode, without duplicating triggers.
- 2026_10_14_000013_add_food_image_metadata.php: nullable safe references/dimensions and complete-or-null CHECK.
- 2026_10_14_000014_create_individual_listings.php: listing/version tables, ownership/current-publication FKs, immutable/identity guards, indexes and permissions.

Earlier uncommitted consolidation is separately inventoried in [migration documentation](14-clean-schema-migration.md). No obsolete Variant conversion chain is introduced.

## 10. Publication safeguards

Published Versions/snapshots remain immutable. Parent locks serialize publication; PostgreSQL enforces one Draft. Current pointers must reference an owned publication and cannot move backward. Restoration clones a higher-numbered Draft. Current active/eligibility rules govern availability separately, including a frozen category's status after master reclassification.

## 11. Removed competing UI

Removed package-draft-editor.tsx, product-form.tsx and inline-catalog.tsx. Ordinary workflows no longer expose Variants/manual Selection Group construction. Authorized granular backend endpoints remain compatibility boundaries for existing configuration/tests; legacy GET forms return to the canonical workspace. Type-only package-menu-form.tsx and pricing-form.tsx retain legacy filenames, not competing forms. Audit history identifiers are not rewritten.

## 12. Automated checks

Tests exclusively use zek_catering_test at 127.0.0.1:5432 with uncached configuration guards. PHP and browser database tests run sequentially.

- PHP/PostgreSQL: 142 passed / 1,027 assertions, including the final permission-navigation regression.
- Isolated upgrade: passed; all 15 migrations, six representative IDs retained, published snapshot unchanged, listing tables present and Variant tables absent.
- Pint, Larastan, TypeScript, ESLint, Prettier: passed.
- Vitest: 12 passed across 6 files.
- Production build: passed.
- Routes: 89 non-vendor routes, zero duplicate names.
- Draw.io XML: valid, 5 pages.
- git diff --check: passed, including the final documentation update.
- Chrome: final complete run 16 passed / 0 failed (3.1 minutes), using installed Chrome and the guarded test server. An earlier run had 15 passes and one image-removal completion timeout with an unfinished request. Restarting the local test server with verbose access output suppressed resolved the rerun; no longer assertion timeout or weakened test was introduced. The initial server-not-running attempt was refused by global setup before tests executed.

## 13. Browser walkthroughs

Coverage includes Admin/Staff access, first-run Catering provisioning, catalog/category/food/size setup, Main and add-on creation, publication confirmation, history/restoration, disable/archive, packed meals, fixed bundles, independent copied quantities, images and individual listings. Reload assertions check persistence. Ephemeral fixture credentials are never committed.

## 14. Responsive/accessibility checks

Chrome verifies keyboard dismissal/focus restoration, mobile navigation, required-field gating and a 390-pixel menu without horizontal overflow. Actual mobile package-details and desktop listing-details screenshots were inspected: commercial facts and actions are readable and fit their layouts. These are focused checks, not a full WCAG audit, screen-reader certification or multi-browser/device acceptance.

## 15. Documentation/ERD

Architecture, entity definitions, acceptance criteria, code organization, migrations, business workspaces, in-app guide and owner walkthrough are updated. Editable Draw.io depicts actual listing/version ownership and food-image metadata, replacing speculative media/navigation structures. Historical phase documents are historical; this milestone and later owner decisions control conflicts.

## 16. Limitations

No inquiries, reservations, checkout, quotations, payments, notifications, inventory, scheduling or fabricated analytics were added. No customer acceptance workflow is available. Images are current presentation assets; package images and listing overrides are unsupported. Deployment needs GD, writable persistent storage/backups, operational/security review and broader accessibility/device checks. Automated success is not owner acceptance or production readiness.

## 17. Development migration approval

Read-only inspection confirmed development zek_reservation:5432 has all 12 baseline migrations, always_include and no Variant tables. Counts then: 1 user, 1 category, 3 foods, 4 services, 4 Products and 4 Package Versions. The owner's reset resolved the previous missing-column issue. No development data/schema/account writes were made; account ID 2 was not promoted or granted permissions.

Owner approval is still required to back up/verify the target and run ordinary php artisan migrate for the three new migrations. Do not run fresh/reset/seed or reprovision the Administrator. Rollback refuses data loss when new listings/images/configuration facts are in use; use a controlled preservation plan instead.

## 18. MANUAL TESTING READINESS

**READY FOR MILESTONE TESTING** in the verified isolated environment. Technical implementation and integrated Admin/Staff catalog workflows passed the checks above; owner business acceptance remains outstanding. Development testing needs the approved forward migration first. Follow the [owner walkthrough](11-admin-staff-guide.md): necessary food eligibility, package Details/Menu/Pricing, Save, then explicit Publish. Images, descriptions, new categories, copied quantities, maximums and applicable advanced measurements/split settings are optional. Admin and appropriately permitted Staff are testable perspectives. Customer workflows require a separately approved milestone; no date is assumed. This is not full customer UAT or commercial-production readiness.

## 19. Git handoff

All work remains unstaged/uncommitted for GitHub Desktop owner review. The tree includes earlier domain/baseline refactoring and Milestone A, not only Milestone B. No Git mutation/discard operation was performed. Review source/tests/docs together; ignored credentials, dependencies, build output and browser artifacts are not commit material. Status contains 115 modified, 34 deleted and 95 untracked entries (some entries are directories); these represent the combined milestone, not only the latest corrections. Last commit remains 720b74b.

**Recommendation:** ready for owner review and a milestone commit; no remaining automated-check blocker. Development migration and owner acceptance are explicit separate steps.

Suggested Conventional Commit:

\`feat(catalog): unify package workflows and complete commercial catalog\`

Copy-ready description:

- Consolidate the unreleased schema around Collection → Product → Package Version, removing the Variant layer and superseded configuration UI.
- Modernize Admin/Staff navigation, catalog cards, contextual food/category forms, accessible dialogs, feedback and permission-aware actions.
- Introduce a cohesive atomic package wizard with independent quantity policies, explicit/automatic fixed foods, read-only details and safe published edits.
- Add validated Food Item images, thumbnails, replacement/removal and transaction-aware filesystem cleanup.
- Simplify service creation to package or individual selling while preserving advanced packed-meal behavior and integer-centavo pricing.
- Implement independently priced individual listings with optional serving sizes, immutable published revisions, history cloning, availability and archive workflows.
- Preserve server-side authorization, last-Administrator protection, audit history, concurrency and immutable commercial snapshots; add predefined listing permissions without default Staff grants.
- Update architecture, entity definitions, owner walkthroughs, acceptance criteria and editable Draw.io ERD.
- Verify 142 PostgreSQL/PHP tests (1,027 assertions), 16 Chrome workflows, 12 Vitest tests, isolated 12-to-15 migration upgrade, static/format checks, production build and route/schema checks.
- Keep customer transactions deferred and require owner approval before applying forward migrations to development.
