# Post-commercial-configuration handoff

Status: prepared for the owner to commit the accumulated commercial-configuration and Phase 6.2–6.3.1 work after manual acceptance. This is a handoff record, not authorization to implement a reservation, quotation, payment, notification, scheduling, capacity or inventory workflow.

## Implemented checkpoint

- Internal Administrator and permission-scoped Staff access, policy enforcement, audit history, secure sessions and protected catalog/package administration.
- Shared Food Categories and Food Items, media handling, measurement units, current availability safeguards, service configuration, package Drafts, publishing, immutable history and revision/restore flows.
- Main Catering’s routed Details, Pricing & Quantity, Menu Configuration and Review workflow. Its menu is category-first, keeps Draft changes local until saved, uses selected-category configuration dialogs, and preserves immutable Published history.
- Add-on services retain their approved, distinct business rules: Separate Food Servings or Combined Contents presentation, optional serving amounts and size choices, fixed bundles, Food Pack/Belly Lechon package behavior and individual-sale listings. Add-on menu discovery now also begins with an explicit category choice; newly created Food Items require an explicit selection before they join a package Draft.
- Public customers can browse current published offerings and use a temporary planning selection. It is not an inquiry, reservation, quotation, order, payment, customer account or capacity hold.

The earlier approved add-on configuration engine is complete. The separately started effort to make every add-on category configuration use the exact Main Catering dialog presentation is **not complete** and is intentionally not represented as a finished redesign. It may be resumed later as a focused UI refinement without changing package behavior.

## Recommended next phase

Start with **Reservation Request Intake**, not quotations or payments.

The public flow should let a customer choose one current published Main Catering package, optional current published add-ons/listings where their rules allow them, validate the complete selection in Laravel, and submit contact/event details as a **Reservation Request**. Submission must not promise availability, reserve inventory, confirm a booking, collect payment or create a quotation.

The request should freeze the selected published-version/listing facts and server-calculated commercial breakdown needed for Staff review. Later changes to catalog records must not rewrite the submitted request. Staff needs a review queue, clear status/audit history and authorization; the exact statuses and transitions require owner approval before implementation.

## Decisions required before Reservation Request Intake

1. Required customer/event fields: contact channels, event date, start/end time, venue/address, guest count, service style and notes.
2. Whether a submitted request temporarily holds a date or is only an inquiry. The recommended default is **inquiry only, with no hold**.
3. Availability source and conflict policy: staff-only review initially, or automated capacity/date checks when authoritative capacity data exists.
4. Whether delivery/setup fees, taxes, discounts and manual adjustments belong in the first request estimate or begin with Staff quotation review.
5. Staff workflow states, ownership/assignment, customer communications, expiry/cancellation policy and which actions require Administrator versus Staff authority.

## Recommended sequence after approval

1. Reservation Request Intake and Staff review queue.
2. Quotation workflow with explicit commercial adjustments, expiry and customer acceptance.
3. Payment/deposit recording and confirmed reservation lifecycle.
4. Notifications, scheduling/fulfillment and inventory only after their business policies are approved.

## Safety boundaries

Automated destructive tests remain limited to `zek_catering_test` on port `5432`. The local development database `zek_reservation` must not be reset, freshly migrated or seeded without owner authorization. The owner continues to stage, commit and push through GitHub Desktop.
