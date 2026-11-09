# Cancellation and refund management

Status: implemented for milestone testing.

## Purpose and lifecycle

Cancellation is a reviewed case-management workflow, not an automatic payment
or reservation action. A customer uses the existing request reference, mobile
number, and Customer Access Code in **Track Your Request**. An authorised Staff
member may prepare an internal request with `cancellation.manage`; only an
active Administrator can finalize the outcome or administer refunds.

An unconfirmed quotation produces a **withdrawal**. A confirmed reservation
produces a **cancellation**. Both begin at `pending_review`; an Administrator
may decline, finalize without verified money as `cancelled`, or finalize into
the refund review lifecycle:

`refund_under_review` -> `refund_approved` -> `refund_completed`.

The customer sees only the current recorded status and completed refund amount.
Private notes, reviewer identities, evidence, and financial-review reasons are
never exposed through the customer portal.

## Operational and commercial rules

- A pending request never releases an event slot. An approved, finalized
  confirmed-reservation cancellation changes the reservation to `cancelled`,
  records a Reservation Status Event, and releases capacity because capacity
  counts confirmed reservations only.
- Completed reservations cannot use the ordinary cancellation workflow.
- Pending rescheduling and cancellation are mutually exclusive. The operator
  must resolve the active change request first; neither history is deleted.
- A cancelled reservation is never silently reactivated. Any later event is a
  new authorized booking workflow.
- The current Business Policy supplies a configurable cancellation-notice
  guideline (default 14 days) and customer guidance. Each request freezes the
  values, event date, deadline, and whether it was inside the notice period.
  A late request is accepted for review and does not create an automatic
  penalty, forfeiture, or refund promise.
- Administrator-initiated ZEK cancellations require a meaningful reason and
  are immediately finalized by that Administrator with an audit event. This is
  distinct from customer-request review.

## Financial integrity

Original payment, acceptance, allocation, and quotation-version history are
immutable and are not edited by cancellation or refund work. Refunds are linked
to one verified payment on the same quotation case. Money is stored in integer
centavos.

- A refund begins `pending_review`; Administrator approval is not proof of a
  transfer. Completion is recorded only after money was returned outside the
  application.
- A payment lock serializes creation, completion, and correction. Pending and
  approved refund amounts are reserved, so multiple requests cannot
  over-promise one verified payment. Completed refunds and append-only
  corrections determine the amount actually returned and money still held.
- Idempotency UUIDs prevent duplicate refund submission. One active cancellation
  case per quotation is enforced with a PostgreSQL partial unique index.
- Completed refund facts are never overwritten. A signed, reasoned
  `refund_corrections` row is the only correction path and cannot reduce a
  refund below zero or exceed money still held.
- Optional evidence is stored on Laravel's private local disk and is available
  only to authorized Administrators through secured preview/download routes.

## Data and audit boundaries

`cancellation_requests`, `refunds`, their status-event tables, and
`refund_corrections` use restrictive foreign keys, row-state checks, ownership
triggers, and append-only PostgreSQL guards. Workflow events record chronology;
Audit History records the responsible actor and important before/after facts
without replacing domain history.

## Admin workflow

1. Open **Reservations** and select the quotation case, or open
   **Cancellations & Refunds** to find the review queue.
2. Review event, reservation, policy snapshot, verified/payment facts, and any
   pending change request.
3. Decline with a reason, or finalize. Finalization preserves all commercial
   history and only then releases a confirmed event slot.
4. If money needs review, create a full or partial refund against the relevant
   verified payment, approve or reject it, then mark it completed only after
   offline disbursement. Upload private evidence when available.
5. Correct a completed amount with an append-only signed correction and reason;
   do not edit the original record.

Deferred: payment-provider payouts, automatic refund calculations or
forfeitures, inventory/staff scheduling, and customer accounts.
