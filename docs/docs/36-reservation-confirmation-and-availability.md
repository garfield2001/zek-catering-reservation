# Reservation Confirmation and Availability

Status: implemented for milestone testing. This workflow is the operational step after an accepted quotation and payment review; it is not an automatic side effect of an inquiry, quotation, acceptance, or verified payment.

## Confirmation rules

- Only an active Administrator may confirm, re-confirm, defer, decline or complete a reservation. Authorized Staff may view the confirmation queue and detail when they have `reservation.view`, but cannot mutate it.
- The newest accepted quotation Version is the candidate for confirmation. Its verified reservation-payment threshold must be satisfied; full payment is not required.
- A late payment that already satisfies the threshold remains visible as overdue history but does not by itself prevent confirmation.
- Only `confirmed` and `completed` reservations consume the capacity record for their date. An inquiry, issued/accepted quotation, verified payment, deferred decision and declined decision never holds capacity.
- The default daily confirmed-event capacity is configured in Business Policies (initially 3). An optional daily guest limit is unset unless an Administrator configures it. A date override may replace either capacity for future confirmation decisions.
- Confirmation obtains a transaction-scoped PostgreSQL advisory lock for the Asia/Manila event date, locks the policy and date override, and recalculates capacity before insertion/update. It cannot double-count the same reservation during re-confirmation.
- Defer and decline require a reason, retain every payment fact and record whether a positive quotation balance existed. They do not cancel, refund or reallocate anything. Older immutable decision snapshots remain unchanged; current screens derive their presentation from the stored balance facts rather than rewriting historical records.
- A post-confirmation accepted quotation revision does not change the reservation. The queue identifies that re-confirmation is required; only an Administrator can explicitly do so. A completed reservation cannot be re-confirmed.

## Data and history

`reservations` owns the current operational confirmation and immutable JSON snapshots of its selected accepted quotation facts and capacity assessment. Both confirmed and completed reservations consume operational capacity for their event date; cancellation follows its finalized release rules. A reservation may be completed only after its scheduled event end in Asia/Manila. `reservation_confirmation_events` records all confirmed, deferred and declined review decisions; `reservation_status_events` records confirmed/completed status changes. Both histories are PostgreSQL append-only and complement, rather than duplicate, the wider `audit_events` ledger.

## Administration workflow

1. Open **Reservations** and choose **Review confirmation** for an accepted quotation.
2. Review event facts, payment readiness and live capacity. This screen does not reserve a slot.
3. An Administrator either confirms the reservation, or records a deferred/declined decision with a reason.
4. For direct quotations, enter an expected guest count when required by a configured guest limit. Inquiry-sourced quotations retain their requested guest count.
5. After the event, an Administrator may mark the confirmed reservation completed with an optional operational note.

The confirmation detail has explicit field and business-rule messages: missing event dates, unpaid reservation requirements, full capacity and stale/already-confirmed versions are returned by Laravel and rendered next to the applicable action. It never presents a generic connection message for an ordinary validation or authorization result.

## Reservation changes and rescheduling

The subsequent [Reservation Changes and Rescheduling](37-reservation-changes-and-rescheduling.md) workflow leaves the current confirmed reservation intact until an Administrator applies a reviewed change. It locks the source and target dates before committing a capacity swap, creates a commercial quotation revision where required, and never automatically reallocates verified payments.

## Deliberately deferred

Cancellation, capacity holds/pencil bookings, staff confirmation delegation, scheduling/crew assignment, inventory allocation and automatic notifications remain outside this workflow.
