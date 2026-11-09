# Reservation Changes and Rescheduling

Status: implemented for milestone testing.

## Lifecycle

Only a `confirmed` reservation can receive a change request. Customer requests
continue to use the request reference, matching mobile number and Customer
Access Code; no customer account is introduced. Authorized Staff may prepare a
request for a customer, while an active Administrator alone can review and
apply it.

`pending_review` → `awaiting_customer_agreement` (commercial change) →
`awaiting_reconfirmation` → `applied`; an Administrator may instead move a
pending request to `declined`. Only one active request may exist for one
reservation. Retrying an idempotency UUID returns the existing request.

Every request snapshots original and proposed event facts, change types, the
relevant Business Policy facts, requester/reviewer/applier identities and
timestamps. `reservation_change_request_events` is PostgreSQL append-only;
`audit_events` records the corresponding administrative/security activity
without replacing that workflow history.

## Rules

- Date, time, venue and guest-count requests never mutate the confirmed
  reservation at submission or review.
- A normal date move inside the frozen configured rescheduling-notice period
  requires a recorded Administrator exception reason. The current default is
  14 days. An exception never bypasses payment or capacity safeguards.
- Menu and add-on proposals are validated with the same published package and
  direct-listing calculators as an inquiry. Laravel also requires the current
  product, Collection and published version to remain available for a new
  proposal; raw client selection payloads are not trusted. The three-day
  menu-finalization deadline comes from the accepted quotation and requires an
  exception reason once elapsed.
- Guest-count changes use the original package Version's quantity rules when
  a package-backed quotation is involved. They, menu changes and add-on
  changes create a Draft quotation revision; historical issued/accepted
  Versions remain immutable.
- An Administrator reviews the new Draft and issues it through the existing
  quotation screen. The customer then explicitly agrees in Track Your Request.
  Agreement accepts that revised Version only; it is neither a payment nor a
  reservation confirmation.
- Verified payments are never automatically moved, refunded, reversed or
  deleted. If the new accepted Version needs an existing payment, an
  authorized Administrator must use the existing explicit allocation workflow.
  This exposes increased balance, partial payment and possible overpayment
  facts through the existing payment balance service.
- Apply/re-confirm locks the change and reservation, checks the appropriate
  accepted Version's verified reservation-payment threshold, then locks both
  source/target Manila dates in a stable order and recalculates capacity in
  the same transaction. A failed check leaves the prior confirmed reservation
  and its capacity snapshot intact.

## Administration and customer flow

1. Open a confirmed reservation. A Staff member with `reservation.change.manage`
   may submit date/time/venue/guest facts supplied by a customer, use the same
   guided Main Catering menu selector, and prepare a complete replacement set
   of optional add-ons. These controls only prepare a proposal; they do not
   alter the confirmed reservation or quotation.
2. An Administrator compares original and proposed facts, declines with a
   reason, or approves. Short-notice and late-menu exceptions require reasons.
3. For commercial changes, review and issue the generated quotation revision.
   The customer agrees through Track Your Request.
4. Reallocate an existing verified payment only when appropriate; no automatic
   financial change occurs.
5. An Administrator applies the change. Laravel repeats payment and capacity
   checks and records a new confirmation event.

The customer-facing change form exposes event date/time, venue, guest count, a
guided Main Catering menu configurator preloaded from the confirmed menu, and
replacement controls for currently published add-on packages and direct-sale
items. A proposed add-on replacement is recalculated server-side on submit;
the browser never supplies a trusted total.

## Deliberately deferred

Cancellation/refund execution, automatic refunds, capacity holds, customer
accounts, scheduling, inventory allocation and notifications remain outside
this milestone.
