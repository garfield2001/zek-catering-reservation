# Priority 4B — Customer-facing UI/UX refinement

Status: implementation and verification complete; ready for owner milestone testing. This document records a presentation and accessibility refinement of established public workflows. It does not add customer accounts, payment collection, availability holds, capacity allocation, notification delivery, or new commercial policy.

## Customer journey presentation

The public header now keeps the current browser-based inquiry selection visible on desktop and mobile. Its count is a navigation aid only: Laravel still revalidates every selected published offering, configuration, quantity, and price when a planning estimate or inquiry is submitted.

Published Main Catering packages continue to use their configured Food Item total, included items, category bounds, and category-specific Customer Choices. The configurator announces the current total and remaining choices, keeps the existing category navigation and image preview, and presents all authoritative validation responses together with a business-facing field label. It does not make a client-side selection valid by presentation alone.

The inquiry review and event-detail screens use inquiry language and continue to explain that submitting a request creates neither a quotation nor a date hold or confirmed reservation. The acknowledgement still exposes the reference and private Customer Access Code only to the submitting browser session.

## Track Your Request

The progress presentation is derived only from already supplied request, quotation, payment, reservation, and cancellation facts. It deliberately distinguishes:

- receipt submitted and awaiting verification;
- partially verified payment and a satisfied reservation-payment threshold;
- payment readiness from Administrator availability review and reservation confirmation;
- actual confirmed, completed, and cancelled reservation states; and
- cancellation/refund review from a completed cancellation or refund.

A verified reservation-payment threshold is presented as **Awaiting confirmation** until the existing Administrator-only confirmation transaction succeeds. A cancellation/refund request is likewise never represented as an automatic cancellation or refund.

Public quotation event facts and acceptance deadlines are formatted in Asia/Manila. Existing receipt upload, receipt-replacement, instruction-change notice, customer change request, cancellation request, and immutable commercial-history behavior remains unchanged.

## Accessibility and responsive behavior

- The persistent inquiry link has a readable accessible name and an item count while retaining a compact mobile treatment.
- Quantity errors are associated with the quantity control; menu validation uses an alert with every returned authoritative error rather than a vague success/failure message.
- The food-selection total is announced through a polite status region, while fixed food, Customer Choice category, image-preview, selected/replacement, and disabled-limit behavior remains explicit.
- The customer progress steps use text labels and current-step semantics rather than color alone, collapse from five columns on narrow viewports, and retain contextual explanations.

## Verification boundary

Vitest covers the customer progress distinction between verified payment readiness and reservation confirmation, and confirmed/completed/cancellation states. The serial Chrome workflow retains its end-to-end coverage for public package selection, image preview, inquiry acknowledgement/access-code protection, quotation availability, receipt-driven acceptance, additional receipts, and aggregate verified balances. Laravel feature tests remain the authority for eligibility, pricing, access-code protection, upload windows, payment facts, cancellation/refund workflow, and Administrator confirmation/capacity rules.

No ERD change is required: this refinement adds no persisted entity, relationship, column, constraint, or lifecycle transition.
