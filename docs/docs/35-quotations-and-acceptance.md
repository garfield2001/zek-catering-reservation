# Quotations, Business Policies and Acceptance

Status: implemented for owner milestone testing. Quotations provide commercial drafts, issuance, revisions and explicit acceptance. They do not verify payment, confirm a reservation, hold availability, schedule fulfillment, execute refunds, or send customer messages.

## Workflow

`Reservation Request → Prepare Quotation → Save Draft → Issue → Customer Review → First receipt upload accepts the displayed Version → Administrator verification`

Opening **Prepare Quotation** is read-only and does not insert a quotation. The page loads the frozen inquiry, selected package/add-ons and server-derived draft pricing. **Save quotation draft** is the deliberate write that creates the one allowed quotation case for that request. PostgreSQL uniqueness and the creation transaction ensure repeated saves open the same case. Manual quotations remain available for authorized internal users.

Each case has numbered versions. A Draft may be adjusted or issued. An issued version is immutable; revision creates a later Draft and a newly issued revision supersedes a prior issued version without changing history. The first valid customer receipt upload accepts only the current, unexpired displayed Version in the same transaction that records its Pending Verification payment evidence. There is no separate public Accept Quotation control.

## Business Policies

Business Policies replace the old free-text Quotation Terms approval gate. A singleton typed record is available to authorized Staff for reference and editable only by active Administrators. It uses structured values:

- minimum advance booking (default 14 days) and the Administrator-reviewed emergency indicator;
- fixed four-hour event duration;
- standard quotation validity (default 7 days) and whether an issuer may override it;
- reservation payment as either a PHP fixed amount or percentage, plus its deadline (default 48 hours after acceptance);
- final-payment deadline (default 7 days before the event); and
- a hard customer receipt-upload cutoff before the event (default 7 days); and
- customer-facing account, e-wallet, bank and additional payment instructions.
- menu-finalization deadline (default 3 days after acceptance).

There is no required tax statement, owner-evidence form, test configuration, production/test issuance gate, cancellation text, or rescheduling text in the ordinary quotation workflow. Those future policies are not invented here.

Changing a policy is audited. Issuance copies the exact structured Business Policy snapshot into the immutable quotation version; acceptance copies it again and derives its deadlines. Later settings never change a previously issued or accepted commercial record.

## Adjustments and authorization

Laravel calculates manual and request-derived totals in integer centavos. Administrators may directly apply an exceptional discount, surcharge, override or waiver to a Draft with a meaningful reason; Activity History records actor, reason and before/after facts. Staff must have the adjustment-request capability and an Administrator must approve or decline their request. A manipulated Staff HTTP request cannot invoke the direct-adjustment path.

Administrators have all capabilities. Staff needs explicit quotation capabilities for view, management, issuance, adjustment request/review, delivery record, or offline acceptance. All policies and Actions enforce this server-side.

## Customer review, acceptance and receipt evidence

The primary customer path is **Track Your Request**, using the inquiry reference, registered mobile number and Customer Access Code. The portal shows the issued quotation, frozen selections, total, relevant policy facts and deadlines, and supports browser print/save-as-PDF.

The customer supplies only a JPEG, PNG or WebP receipt image. They do not select a provider, enter an amount, or enter a reference number. The first valid upload accepts the current issued Version and creates a **Pending Verification** evidence record; it neither marks a payment received nor confirms a reservation. Additional receipts are allowed. A Pending or Rejected receipt may be replaced, returning it to Pending Verification while retaining every prior private evidence version. Verified evidence is immutable.

The quotation-validity check is performed before the first acceptance upload. An expired quotation is marked Expired and the customer is directed to create a new inquiry. The hard receipt-upload cutoff is calculated in Asia/Manila from the frozen issued policy setting. It blocks ordinary new/replacement customer uploads through both UI and HTTP, but never prevents an Administrator from reviewing evidence submitted while uploads were open. A receipt submitted after the reservation-payment deadline but before the upload cutoff remains valid Pending Verification evidence.

Historical token/OTP quotation records and their schema remain preserved for backward compatibility, but they are not part of the ordinary customer journey or administration issuance feedback.

## Data model

- `business_policies`: singleton current structured settings with Administrator update provenance.
- `quotations`: one request/manual commercial case, customer/event source facts and current-version pointer.
- `quotation_versions`: numbered line snapshots, centavo values, validity, policy snapshot, immutable issue/withdrawal lifecycle facts and retained legacy token hash.
- `quotation_adjustment_requests`: reasoned Staff/Administrator adjustment records.
- `quotation_acceptances`: one acceptance per version, acceptance evidence, copied policy snapshot, reservation-payment due date, final-payment due date and menu-finalization due date.

## Payments and verification (2026-11-07)

ZEK records payments but does not collect money. Customer transfer instructions are Administrator-managed Business Policies and begin with clearly marked sample placeholders; an owner must replace those before real collection. Customer-facing provider selection and provider-management controls are not part of this workflow. Internal cash/offline recording retains a method for accounting provenance.

Customer proof submissions are private, file-type/size validated, hash-protected against reuse, rate-limited and stored outside public storage. Each immutable `payment_evidence_versions` row records the proof identity, submission time and version number; a payment points to its current proof only for convenience. PostgreSQL rejects changes or deletion of evidence history and rejects a submitted quotation Version belonging to another quotation case. They start **Pending Verification** with no claimed amount and do not affect a balance. Authorized Staff may record an internal payment for review; only an Administrator may verify, reject or reverse it. On verification the Administrator enters the PHP amount actually received and may send a customer-visible message. Rejection and reversal require a reason, and every action is audited.

Verified payments are explicitly allocated once to an accepted quotation Version. Laravel calculates centavo balances from verified allocations only: total due, reservation-payment requirement, verified total, remaining balance, reservation-payment shortfall, overdue state and overpayment. A payment below the reservation requirement is valid but does not satisfy it. An overpayment is retained, shown separately and never automatically refunded. Reversal removes the payment from the verified balance; it is an accounting correction, not a refund transfer.

Allocation is append-only financial history. Reallocating a verified payment first supersedes its active allocation with the Administrator, timestamp and reason, then creates one replacement allocation in the same transaction. PostgreSQL permits one active allocation per payment, prevents allocation facts from being rewritten or deleted, and rejects any allocation whose quotation does not match the payment's quotation. The balance service selects the highest-numbered accepted quotation Version and sums only its active allocations whose payments remain Verified. This keeps an accepted revision current without double-counting the superseded Version.

Early exceptional payments may be recorded only by an Administrator with an exception reason. They remain unallocated until an accepted quotation Version is deliberately selected. A verified payment does not confirm a reservation, create a hold, or schedule fulfillment.

The Administration Payment workspace provides private preview/download for each historic receipt version, review/rejection/reversal, allocation history, current balances and reasoned allocation controls. Customer validation is rendered beside the affected receipt field; successful submission is announced as Pending Verification.

## Deferred work

Payment gateways and automatic collection, automatic verification, reservation confirmation, availability allocation, customer accounts, automated delivery, cancellation/refund execution, rescheduling execution, capacity and operational scheduling remain separate future milestones.
