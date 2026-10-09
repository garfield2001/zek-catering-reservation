# Implementation Organization — Laravel Modular Monolith + Feature-First React

## Architecture rules
Thin controllers and pages; modest use-case actions; Eloquent only for persistence/relations/scopes; Form Requests validate transport; policies authorize; dedicated configuration/selection/price domain services for invariants. Avoid blanket abstract repository pattern, service-per-CRUD, interfaces for every class, global business helpers or single MegaCatalogService. No generic configurable JSON rules engine. Test services independently and use PostgreSQL integration tests for FK/check constraints.

## Suggested backend folders
app/
  Actions/Catalog/{CreateDish,UpdateDish,PublishVariant,CloneVariantDraft,PublishDirectListing,CloneListingDraft}.php
  Actions/Access/{UpdateStaffPermissions,DeactivateUser}.php
  Actions/Media/{UploadCatalogImage,RemoveCatalogImage}.php
  Data/Catalog/{VariantDraftData,SelectionData,SplitAllocationData,PricePreviewData}.php
  Domain/Catalog/{CatalogEligibility,CurrentAvailability,GroupSelectionValidator,PackageConfigurationValidator,SplitSelectionValidator,PriceCalculator,QuantityRuleValidator}.php
  Enums/Catalog/{SelectionMode,PriceBasis,ProductFamilyCode,PublicationStatus}.php
  Http/Controllers/{Admin/Catalog,Storefront/Catalog}/...
  Http/Requests/Catalog/{StoreDishRequest,SaveVariantDraftRequest,PublishVariantRequest}.php
  Http/Resources/Catalog/...
  Models/{Dish,DishOffering,OfferingOption,Product,ProductVariant,VariantVersion,SelectionGroup,DirectListing,DirectListingRevision,MediaAsset,...}.php
  Policies/{DishPolicy,ProductPolicy,VariantVersionPolicy,MediaAssetPolicy}.php
  Queries/Catalog/{BrowseCatalogQuery,PackageEditorQuery,EligibleOfferingQuery}.php
  Support/Money/{Money,PriceBreakdown}.php
  Jobs/Media/ProcessCatalogImage.php

Controller example (conceptual): authorize via policy; `$data=$request->validated(); $draft=$action->handle($data); return redirect()->route(...);`. No pricing formula, DB orchestration, filesystem manipulation or deeply nested eligibility logic in controllers.

## Suggested React/TypeScript
resources/js/
  pages/admin/catalog/{dishes/index.tsx,dishes/edit.tsx,variants/edit.tsx}
  pages/storefront/{addons/index.tsx,addons/show.tsx}
  features/catalog/components/{DishCard,DishPicker,CategoryTabs,ServingOptionSelector,QuantityInput}.tsx
  features/catalog/hooks/{useDishSelection,useCategoryNavigation}.ts
  features/catalog/types/{dish,offering,product,variant}.ts
  features/package-builder/components/{SelectionGroupEditor,CategoryRuleEditor,PriceAdjustmentEditor,PublishChecklist}.tsx
  features/package-builder/hooks/{useVariantDraft}.ts
  features/direct-listings/components/{ListingRevisionEditor,ListingPublishChecklist}.tsx
  features/addons/components/{AddonFamilyNavigation,AddonCard}.tsx
  features/food-pack/components/{SplitAllocationEditor}.tsx
  features/media/components/{AssetPicker,ImageGallery}.tsx
  features/access/components/{PermissionGrid}.tsx
  features/audit/components/{AuditEventList}.tsx
  components/ui/ (generic controls only)
  layouts/{admin-layout,storefront-layout}.tsx
  lib/{currency,formatters}.ts
  types/ (shared generated page props)

Pages assemble feature components and transport props; no giant JSX pages. A hook owns local interactions but never performs authoritative pricing. A stateless server price-preview endpoint returns a validated breakdown, current version/revision ID and availability errors; it creates no quotation or order. Money is transported as decimal strings. Keep form fields typed and client-side hints, use Inertia form submission when convenient. Only introduce a front-end state management library when component scope proves it necessary. Keep display category navigation independent from selection-rule validation. Add-on navigation includes all four add-on families.

Publication Actions coordinate owner/draft/reference locks, snapshot materialization, domain validation, pointer changes and audit in a transaction. Policies protect commercial fields as well as routes. Database constraints/triggers back application immutability. Reuse focused validators/calculators for draft previews and publication; distinguish live draft facts from frozen published facts explicitly. Do not introduce a generic publication framework solely because packages and listings share a workflow.

## Dependencies and quality gates
PHP code formatting: Laravel Pint. Static analysis: PHPStan/Larastan (choose compatible version). Tests: Pest or PHPUnit feature/integration + pricing unit tests. TS: strict TypeScript, ESLint, Prettier, React Testing Library/Vitest (compatible with selected starter), end-to-end Playwright on key editor/storefront flows. CI runs lint, static analysis, PHP tests, TS tests, build, and basic migration smoke test.

## Code review rules for Codex
1. No controllers > ~80-120 lines without a reason; size is a code smell, not a hard constraint.
2. No data access / pricing / business validation inside React components.
3. Avoid more than one source of truth for price adjustments.
4. Changes to published versions forbidden by DB/app, not only hidden UI.
5. No unauthorized staff operation through forged URL or API request.
6. No SQL/ORM calls in loops for listing rendering (eager load, pagination).
7. Test failure paths as well as successful workflows.
