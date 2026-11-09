# Pencil Bookings and Manual Reservation Creation

Status: implemented for milestone testing.

This milestone adds an internal prospect workflow and a controlled way for
authorized Staff or Administrators to start the existing Reservation Request
workflow. It does not create a quotation, accept a quotation, record a payment,
hold capacity, confirm a reservation, schedule staff, or allocate inventory.

## Business workflow

1. An authorized user records a **Pencil Booking** with a customer contact,
   tentative event date, and optional time, venue, guest count, notes, and
   follow-up date.
2. The record stays `active` until the user updates it, closes it, marks it
   `did_not_proceed`, or converts it. It is explicitly not an availability
   hold.
3. An authorized user can create a normal internal Reservation Request directly
   from published offerings, or open an active Pencil Booking and configure the
   request from there.
4. Conversion reuses the established request engine. It requires one published
   Main Catering package, optional validated add-ons/listings, and the existing
   authoritative quantity, food-selection, availability, and pricing rules.
5. The converted request follows the normal inquiry → quotation → acceptance →
   payment → Administrator confirmation path. No conversion bypasses any later
   safeguard.

## Authority

- Administrators retain full access.
- `pencil_booking.view` permits viewing the list and detail history.
- `pencil_booking.manage` permits creating, editing, closing, and proposing a
  conversion.
- Creating or converting to a Reservation Request additionally requires the
  existing `reservation_request.manage` capability. The interface explains the
  missing safe next step rather than displaying an unexplained disabled action.
- Laravel Policies and Form Requests remain authoritative. UI visibility never
  grants a forged request access.

## Integrity and history

- Pencil Booking status is one of `active`, `closed`, `did_not_proceed`, or
  `converted`. A converted booking must have exactly one linked request and a
  conversion time; another state must have neither.
- The conversion locks the booking row inside a PostgreSQL transaction. Retried
  or concurrent conversion attempts return the existing request rather than
  duplicating it.
- A unique link in both directions preserves provenance. Request source is
  `public`, `manual`, or `pencil_booking`, with a database check matching that
  source to its actor and booking links.
- `pencil_booking_events` are append-only lifecycle chronology. Activity
  History separately records security-relevant create/update/close/conversion
  operations without copying customer contact data.
- Request lines and their commercial snapshots remain the normal immutable
  Reservation Request records. The internal UI never trusts a browser price or
  persists a client-side estimate.

## Operator walkthrough

1. Open **Pencil Bookings** and save a prospect. This is optional; a known
   customer can begin with **Reservation Requests → Create request** instead.
2. On the detail page, update follow-up notes/date or close the prospect when
   appropriate. Neither action reserves the date.
3. For a qualifying active prospect, choose **Create reservation request**,
   review the prefilled contact/event facts, select exactly one Main Catering
   package, and configure any optional add-ons or individual items.
4. Save. The resulting request detail visibly identifies its Pencil Booking
   origin and links back to the preserved prospect history.
5. Continue with the normal **Prepare Quotation** workflow. The system does
   not fabricate a quotation, acceptance, payment, or confirmed reservation.

## Verification scope

Automated PostgreSQL feature coverage includes permission boundaries, stale or
invalid published selections, manual-request provenance, conversion
idempotency, append-only history, origin constraints, and a concurrent
conversion attempt. Browser coverage creates a manual request and edits then
converts a Pencil Booking against an isolated published package fixture.

## Deferred work

Customer-facing Pencil Booking access, notifications/reminders, availability
holds, automatic follow-up escalation, quotation issuance, payment processing,
reservation confirmation, and scheduling remain separate workflows.
