# Phase 5.1 catalog experience

Phase 5.1 improves Administrator catalog work without changing commercial pricing, package publication, or historical snapshots. It does not introduce customer, reservation, payment, inventory, or notification functionality.

## Food Catalog

Food Catalog opens in compact **List** view and retains an Administrator's optional **Grid** preference in browser local storage. Both views use the same paginated backend query, category/availability filters, text search, and supported ordering (name ascending/descending, recently updated, or category). Page sizes are 12, 24, or 48; the server performs both ordering and pagination. Category counts shown in the filter are total catalog counts, not counts after the other filters.

The filter hook delays its local loading state for 200 ms. Fast visits do not flash a placeholder; sustained visits show a list- or card-shaped, reduced-motion-safe result placeholder while the shared layout and filter controls remain stable. Requests are cancelled and sequenced so an older filter response cannot replace a newer one.

## Guided food discovery

The package Menu step separates food discovery from advanced food settings. An Administrator searches all loaded eligible foods, filters by category, selects or clears only the currently shown foods, and sees a bounded scroll region rather than an ever-growing category list. A separate **Selected foods** section remains visible as search/category filters change. It reports the configured total, remaining selections, fixed inclusions, required-category choices, and impossible states using the existing package rules; it does not invent a universal food limit.

Food Options, portions, size choices, price adjustments, and presentation exceptions remain outside the discovery list. Existing package validation, availability checks, draft persistence, review, and publication remain authoritative in Laravel.

## Sold Individually

A direct-sale service now exposes **Add Foods to Sell** rather than expanding an editor for every current listing. The guided three-step workspace is:

1. choose a category or search all active catalog foods; select one, several, or every currently shown unconfigured food;
2. deliberately apply shared selling-unit and purchase-quantity values, then set independent prices (with an explicit confirmed same-price action);
3. review every independent Draft before an all-or-nothing save.

Each row has its own stable creation token. Retrying the identical submitted rows returns the existing draft records rather than silently creating duplicates. The backend locks the service, verifies the active Food Item/category availability already used by individual listings, rejects an existing service/Food Item listing, and creates all rows through the established `SaveIndividualListing` action in one transaction. This workflow creates Drafts only; each listing remains independently reviewed and published. Existing listing search is compact and does not expand pricing editors.

## Images and account photos

New Food Item photos are re-encoded and stored on Laravel's configured public disk as `foods/<food-name-slug>-<opaque-ulid>.jpg`, with a corresponding thumbnail. Uploaded source filenames are never trusted. Existing image references are not renamed or moved.

Users can manage their own optional profile photo at **Profile photo**. Avatar paths use opaque identifiers beneath `avatars/`, are served only to the owner or an authorized Team & Access viewer, and are re-encoded as full and thumbnail JPEG derivatives. Replacing/removing a photo preserves transaction safety and appends an audit event. Initials are the shared fallback in the account menu and Team Members list.

## Historical boundary

The only schema addition is nullable avatar metadata on `users`. It does not alter Food Items, Offerings, Listings, Package Versions, published snapshots, prices, or package configuration. Existing Food images and published commercial facts remain untouched.
