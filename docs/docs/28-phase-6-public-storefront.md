# Phase 6 public storefront and planning selection

Phase 6 introduces public browsing of the currently available commercial catalog. It deliberately ends with a temporary browser selection. It does not create a customer account, inquiry, reservation, quotation, order, payment, notification, capacity hold, delivery commitment, or customer record.

## Public and internal entry points

- `/` is the public ZEK Catering home page for guests and authenticated internal users alike. It does not redirect to the administration workspace.
- `/login` remains an intentionally direct, internal-only entry point for Administrators and Staff. Public navigation, footer and mobile navigation never advertise it. Hiding the link is presentation only; Laravel authentication, throttling, session rotation, Policies and internal route middleware remain authoritative.
- `/catering-packages`, `/add-on-services` and `/my-selection` are public. Management routes remain under authenticated `/admin` routes.
- Logout invalidates the authenticated session and returns to the public home page. It does not grant access to management routes.

## Public commercial boundary

`PublicOfferingDirectory` is the read-only projection boundary. It returns only active, unarchived owners with a current Published Version that also passes the existing effective live-availability checks. Package and listing names, descriptions, menu/group facts, quantity rules and price facts originate from immutable published snapshots. Live category, food, collection eligibility, offering and serving-option checks still decide whether a new public selection is possible.

The customer-facing projection does not expose Drafts, audit events, technical codes as business controls, internal owners, permission state or unpublished price facts. A staff member with Package view permission may open the separate authenticated Draft customer-presentation route. That route is for presentation review only and clearly identifies the Draft as unpublished; it is not public and does not publish or persist a customer selection.

## Estimates and temporary selection

The public package endpoints reuse `PackagePricePreview::calculatePublished`; direct items reuse `ListingConfiguration::preview`. Laravel validates quantities, available food/serving selections, snapshots, and integer-centavo arithmetic before returning an estimate. Clients send no trusted price.

`Storefront\\Selection` stores only an anonymous browser-session selection keyed by published Package Product or independently sellable listing/option. Each `/my-selection` view recalculates from the current publication and availability. An item that has been disabled or superseded is shown as unavailable and can be removed; it is never silently substituted. Adding an item replaces only the matching temporary browser line, while unrelated package and individual-item lines remain intact.

The visible copy calls the result a planning estimate and explains that it is not a reservation or confirmation. No customer information is collected in this phase.

## User experience

- The public site uses a separate responsive customer layout with Home, Catering Packages, Add-on Services and My Selection.
- Main catering and package services expose included items and customer choices without internal Selection Group terminology.
- Individually sold items show their own absolute published price, option, and quantity constraints; they are not presented as a package add-on premium.
- Images use the existing safe Food Item image derivative/fallback component. Existing media access rules are unchanged.
- The public interface is light-only by owner decision. There is no dark-mode implementation in this phase.

## No schema or ERD change

Phase 6 adds no persisted commercial/customer entities, migrations, foreign keys, or Draw.io relationship changes. It consumes the existing `Product -> PackageVersion` and `IndividualListing -> ListingVersion` publication snapshots. The editable ERD remains accurate without modification.

## Verification scope

`StorefrontTest` covers public root/login separation, authenticated Admin/Staff browsing without a dashboard redirect, published package estimate/temporary selection/deactivation behavior, Admin-only Draft presentation review, and frozen direct-listing pricing/quantity constraints. Existing access/session tests cover login, throttling, logout, inactive accounts and protected administration routes.

Manual review still needs representative published Main Catering, package add-on and direct-sale catalog data. The planning selection is intentionally not an order workflow; commercial acceptance, reservation handling and customer communications remain separately authorized work.
