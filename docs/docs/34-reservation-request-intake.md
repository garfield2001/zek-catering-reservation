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

## Internal creation and pencil-booking conversion

Authorized internal users with `reservation_request.manage` can create a
standard Reservation Request from **Reservation Requests → Create request**.
The internal form exposes only currently available published Main Catering
packages, add-on packages, and individual listings. It uses the same Laravel
selection validators, availability checks, price calculations, published
snapshots, and transactional persistence as the public inquiry. Browser totals
are a preview only; the server recalculates them before saving. Internal
creation has no access-code acknowledgement and is recorded with
`origin = manual` and its creating User Account.

A **Pencil Booking** is an internal, non-binding prospect record. It has its
own lifecycle (`active`, `closed`, `did_not_proceed`, `converted`) and
append-only follow-up history. It never holds date capacity, changes a
published package, creates a quotation, fabricates acceptance/payment facts,
or confirms a reservation. A user must have both pencil-booking management and
reservation-request creation authority to convert an active Pencil Booking.

Conversion opens the same internal request configuration surface with the
booking's contact and event facts prefilled. On save, Laravel locks the booking
row and creates the ordinary request in the same transaction. It then records
the permanent two-way provenance link, marks the booking converted, appends a
lifecycle event, and writes the security audit event. A repeated conversion
returns the already linked request; it cannot duplicate commercial or
operational records. Closing or marking a booking as Did Not Proceed prevents
conversion but preserves its history.

## Data model and integrity

- `reservation_requests` has a non-guessable reference, unique idempotency key, optional email, required mobile number, event facts, access-code hash/reset provenance and an immutable commercial snapshot.
- `reservation_request_lines` has exact published package/listing version references and immutable commercial snapshots. PostgreSQL enforces one stored Main Catering line.
- `reservation_request_status_events` is append-only; it complements `audit_events` rather than replacing it.
- Reusing an idempotency key returns the existing request without revealing or regenerating its access code.
- `reservation_requests.origin` distinguishes public, manual, and
  pencil-booking sources. A database check enforces the valid provenance pair:
  public requests have no internal actor or Pencil Booking; manual requests
  have an internal actor only; converted requests have both.
- `pencil_bookings.converted_reservation_request_id` and
  `reservation_requests.pencil_booking_id` are unique, restrictive foreign
  keys. Their lifecycle event table is append-only in PostgreSQL. Those facts
  complement, rather than duplicate, the general audit ledger.

## Deferred work

Customer accounts, OTP/SMS/email delivery, scheduling and inventory remain
deferred. Payment proof, payment verification, cancellation/rescheduling, and
the Administrator-controlled reservation confirmation workflow are implemented
separately; none of them changes an inquiry's immutable submission snapshot.
