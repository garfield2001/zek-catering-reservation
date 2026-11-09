# Reservation Requests and Customer Progress

Status: implemented for owner milestone testing. This workflow is an inquiry and review process only. It does not hold capacity, confirm a reservation, verify a payment, schedule work, or create a customer account.

## Lifecycle

`Customer inquiry → Submitted → Under Review | Needs Information | Closed`

An anonymous visitor chooses exactly one current published Main Catering package, optionally adds current published add-on packages or individual listings, reviews the server-calculated estimate, and submits tentative contact and event details. Laravel locks and recalculates the selected commercial facts in a PostgreSQL transaction, then stores immutable request and line snapshots in integer centavos.

The event start and venue are tentative. The event duration is a fixed four hours. The current Business Policies minimum advance notice is 14 days by default. Public requests inside that period are rejected with a clear contact path; an emergency exception is an Administrator-reviewed operational decision, not an automatic surcharge or hold.

## Customer access and progress

Each newly created request receives an unpredictable reference and a separate Customer Access Code. The code is displayed only on the submitting browser’s acknowledgement page, can be copied independently of the reference, and is stored only as a password hash. The required public **Track Your Request** lookup uses all three values:

1. request reference;
2. registered mobile number; and
3. private Customer Access Code.

Lookup failures use one generic response and are rate-limited per IP/reference. A successful lookup grants a 30-minute, request-scoped session. The portal shows only the customer’s request, estimate, event summary, supported progress stages, and—once issued—the current quotation and its accepted deadlines. It does not claim payment verification or reservation confirmation exists.

An authorized internal user can generate a replacement code from the request detail. The previous code stops working immediately; only the hash, reset actor and reset timestamp persist, while Activity History records the reset. The replacement is flashed once to the authorized browser for private communication.

## Internal workflow

- The Reservation Requests list shows request reference, customer/event, quotation progress and an appropriate next action.
- **Prepare Quotation** opens a review page without writing a quotation row.
- **Save quotation draft** explicitly creates the single logical quotation case from the immutable request snapshot. Repeating preparation opens that existing case instead of creating another.
- Request status events are append-only lifecycle history; audit events separately record security-relevant submission, status change and access-code reset actions without copying customer contact details.

## Data model and integrity

- `reservation_requests` has a non-guessable reference, unique idempotency key, optional email, required mobile number, event facts, access-code hash/reset provenance and an immutable commercial snapshot.
- `reservation_request_lines` has exact published package/listing version references and immutable commercial snapshots. PostgreSQL enforces one stored Main Catering line.
- `reservation_request_status_events` is append-only; it complements `audit_events` rather than replacing it.
- Reusing an idempotency key returns the existing request without revealing or regenerating its access code.

## Deferred work

Customer accounts, OTP/SMS/email delivery, pencil bookings, scheduling, inventory, cancellation and rescheduling execution remain deferred. Payment proof, payment verification and the Administrator-controlled reservation confirmation workflow are implemented separately; none of them changes the inquiry's immutable submission snapshot.
