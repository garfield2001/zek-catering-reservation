# Priority 4A — Administration and Staff Workflow Refinement

Status: implemented for milestone testing. This refinement improves the internal presentation of existing reservation, quotation, payment, and confirmation workflows. It does not add customer-facing features or change commercial, payment, capacity, acceptance, cancellation, or authorization policies.

## Case workflow presentation

Reservation Request, Quotation, and Reservation Confirmation detail views use a common four-step case workflow: Inquiry, Quotation, Payment, and Reservation. Each step identifies whether it is completed, the current activity, blocked, or a future step, and links to the related internal record when the viewer is authorized to access it.

The indicator deliberately distinguishes the following facts:

- An accepted quotation completes only the quotation step; it is not a confirmed reservation.
- A submitted receipt is awaiting verification, not a verified payment.
- Meeting the reservation-payment threshold is not full payment; any remaining full balance remains visible.
- Capacity is never committed by an inquiry, quotation, acceptance, or payment. It is committed only by the Administrator confirmation transaction.
- Confirmed, completed, and cancelled reservations retain their actual backend status and lifecycle history.

Staff continue to see only links and write controls for which their existing server-side permissions authorize them. View-only screens explain why a sensitive action is unavailable instead of presenting a silent or misleading control.

## Detail-page composition

The large Reservation Confirmation and Quotation detail pages now keep transactional forms and authorization-sensitive actions near their routes while moving stable display regions into feature components:

- `features/quotations/components/quotation-overview.tsx` owns quotation/customer summary, immutable commercial facts, payment/balance presentation, receipt links, and version history.
- `features/reservations/components/reservation-review-overview.tsx` owns event summary, payment-readiness facts, capacity review facts, and append-only confirmation/lifecycle history.
- `features/reservations/components/reservation-change-requests.tsx` owns the existing change-request submission, review, and re-confirmation presentation. Its routes, validation fields, Inertia form transport, and Laravel authority are unchanged.

The page-level components still own the meaningful decision forms: issue/withdraw/adjust quotation and confirm/defer/complete a reservation. No pricing, payment allocation, capacity, or permission check moved to the browser.

## Verification scope

Frontend unit coverage verifies workflow states and contextual links. Browser coverage verifies accepted quotation confirmation review, related quotation navigation, the distinction between payment readiness and reservation confirmation, and normal customer inquiry-to-quotation-to-payment navigation. Existing backend feature coverage remains the authority for permission, payment, capacity, immutable history, and transaction rules.

## Manual review boundaries

This is an internal Administration/Staff refinement. Public storefront changes, new business policies, payment allocation semantics, and capacity rules are outside this document's scope. Owner acceptance must still exercise an Administrator and a least-privileged Staff account against the existing server-side policy checks.
