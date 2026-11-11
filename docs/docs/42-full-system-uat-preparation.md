# Full-System User Acceptance Testing Preparation

Status: prepared for owner-led development/UAT testing, 2026-11-11.

This guide is a manual acceptance checklist for the implemented customer and
internal workflows. It is not a production launch certification, a migration
instruction for the owner's existing development database, or permission to
process real customer or payment data.

## Scope and test rules

Use fictional data only. Prefix records with `UAT` and record the generated
request reference, quotation number, payment IDs, reservation ID, and browser
used for each scenario. Suggested fictional records:

- customer: `UAT Maria Santos`, `uat.maria@example.test`, `09170000001`;
- customer: `UAT Paolo Reyes`, `uat.paolo@example.test`, `09170000002`;
- venue: `UAT Function Hall — Do Not Use`;
- event date: choose a Manila date that meets the *currently displayed*
  advance-notice policy and receipt-upload cutoff;
- payment evidence: a clearly marked fictional image such as
  `UAT-NOT-A-REAL-RECEIPT.png`.

Never enter live card, bank, e-wallet, address, or identity data. The
application records offline payment facts; it does not transfer money or
contact a payment provider.

Use the current Business Policies page as the authority for the test's actual
notice periods, reservation-payment threshold, quotation validity, payment
deadlines, receipt-upload cutoff, daily event capacity, and any configured
daily guest capacity. In this guide:

- **R** means the reservation-payment threshold shown for the issued
  quotation;
- **T** means the quotation's full amount due;
- **C** means the configured confirmed-event capacity for the selected Manila
  event date.

Do not substitute a guessed amount or policy for R, T, or C.

## Owner-controlled environment setup

### Choose the correct database

The protected owner development database is `zek_reservation`. Do **not**
run `migrate:fresh`, `db:seed`, destructive tests, or an unreviewed forward
migration against it as part of UAT preparation.

There are two safe choices:

1. **Existing owner development environment.** Use it only after the owner has
   reviewed and applied the required forward migrations through the normal
   owner-controlled process. Inspect status first with
   `php artisan migrate:status`; that command is read-only.
2. **A disposable local UAT database.** Create a separate local database,
   configure it in an ignored local `.env`, and verify its name before any
   destructive command. This is the only context in which the commands below
   are appropriate.

Automated tests remain separate: they may use only
`zek_catering_test` on `127.0.0.1:5432`, and PHP and browser suites must
not run concurrently.

### Disposable-local bootstrap only

For a newly created, disposable local database (never `zek_reservation`):

1. Install dependencies with `composer install` and `npm ci`.
2. Copy `.env.example` to ignored `.env`, configure the disposable local
   PostgreSQL connection, then run `php artisan key:generate`.
3. Confirm the target database name in `.env`, then run
   `php artisan migrate:fresh` and `php artisan db:seed`.
4. Start Laravel with `php artisan serve` and Vite with `npm run dev`.
   Open `http://localhost:8000`.

In the local environment, the controlled fixture seeds the primary Catering
Collection, a local-only `Admin Tester` account, 11 Food Categories, 42 Food
Items, and published Main Catering Packages A–D. Obtain the local fixture
credentials only from the controlled local setup, then change or deactivate
the account before exposing any non-disposable environment. Outside that local
fixture path, bootstrap the first Administrator interactively with
`php artisan app:create-admin`.

The fixture packages are deliberately useful for UAT:

| Package | Distinct Food Item total | Quantity rule |
| --- | ---: | --- |
| Package A | 4 | Minimum 150; increments of 50 |
| Package B | 5 | Minimum 150; increments of 50 |
| Package C | 6 | Minimum 150; increments of 50 |
| Package D | 7 | Minimum 150; increments of 50 |

Each requires exactly one Rice and one Drink, with additional distinct Foods
chosen from its published choices. The fixture is a local UAT aid only; it is
not an approved commercial menu or production price list.

### Roles and access setup

Create at least one fictional Staff account from **Team & access**. Do not
reuse a real person's account. Test with these deliberately different access
sets:

| UAT account | Grant | Intended check |
| --- | --- | --- |
| UAT Administrator | Administrator role | All review, issue, verification, confirmation, refund and policy actions |
| UAT Coordinator | reservation-request, quotation, payment-record, pencil-booking, reservation-view and cancellation preparation capabilities | Normal preparation without Administrator-only finalization |
| UAT Viewer | only the relevant view capabilities | Read-only access and explanatory blocked actions |
| UAT Restricted | dashboard only (or an explicit deny override) | Forged/direct URL protection |

Permission names and effective access must be read from the current **Team &
access** page. A visible control is not authority; each sensitive check below
must also be attempted through its direct route only where the test account is
otherwise authorized to sign in.

### UAT data readiness checklist

Before testing customer journeys, confirm:

- [ ] Business Policies have owner-approved, non-placeholder instructions for
  the UAT environment and a known daily capacity. Do not use a placeholder
  payment destination for any real transfer.
- [ ] Package A–D (or equivalent owner-configured published packages) are
  visible under **Catering Packages**; each has current live Food Items.
- [ ] At least one optional published add-on package or individual listing is
  available if optional add-on scenarios will be run. If none is configured,
  record those steps as *not applicable*, not failed.
- [ ] An event date is selected far enough ahead for the active notice and
  upload-cutoff policies.
- [ ] A second date is available for rescheduling tests, and a third date is
  reserved for capacity-exhaustion tests.
- [ ] The Administrator has recorded the actual daily capacity C and whether
  a guest-capacity limit is configured. Do not assume a guest limit exists.
- [ ] Fictional receipt images are ready and are safe to upload.
- [ ] A separate browser profile or private window is available for the
  anonymous customer; do not use an internal authenticated browser session for
  public access-code tests.

## Critical workflow scenarios

### C-01 — Public Main Catering inquiry and acknowledgement

**Actor:** anonymous customer. **Prerequisites:** a published Package A–D and
a qualifying future event date.

1. Open `/`, select one Main Catering package, and verify the temporary
   browser selection is only a planning aid.
2. Configure the exact required food total. Select the required Rice and
   Drink, then distinct allowed Foods for the remaining count.
3. If configured, add an optional published service; keep a note of its
   displayed estimate. Use the package workflow's explicit **Continue to event
   details** action to review the selection, then continue to the inquiry form.
4. Enter fictional contact and tentative event facts. Submit once.
5. Copy and store the reference and Customer Access Code shown on the
   acknowledgement page. Do not refresh or rely on being able to reveal the
   code later.

**Pass criteria:** one request is created with the expected immutable main and
optional line snapshots; the acknowledgement explains that neither a quotation
nor date hold exists. The request appears as Submitted internally. No
reservation, payment, or capacity record is created.

### C-02 — Public selection and inquiry boundary checks

Repeat C-01 with one invalid condition at a time:

- omit Rice or Drinks;
- choose too few/many foods, a duplicate food, an unavailable Food Item, or
  an invalid guest quantity;
- attempt an add-on-only inquiry;
- select two Main Catering packages;
- choose an event inside the displayed minimum-notice period.

**Pass criteria:** the affected control or section has an identifiable,
actionable error. No vague “check highlighted fields” response occurs without
an associated field/section. Laravel rejects invalid or stale selections and
does not create a request. A short-notice public inquiry is directed to the
internal emergency-review path; it does not receive an automatic exception,
surcharge, quotation, capacity hold, or confirmation.

### C-03 — Customer access and progress privacy

**Actor:** anonymous customer in a separate browser profile.

1. Use the reference, matching mobile number, and Customer Access Code from
   C-01 to open **Track Your Request**.
2. Retry with a wrong mobile number and separately with a wrong code.
3. From the internal request detail, have an authorized user reset the access
   code. Verify the old code no longer works and the replacement is exposed
   only once to that authorized browser.

**Pass criteria:** valid access creates a request-scoped progress view; invalid
attempts use the same generic failure response and reveal no customer details.
The portal shows only the correct request and supported current stages.

### C-04 — Request review, quotation draft, issuance, and customer access

**Actor:** UAT Coordinator/Administrator, then anonymous customer.

1. Open **Reservation Requests**, locate C-01, and transition it through only
   the offered status actions (for example Submitted to Under Review).
2. Use **Prepare Quotation** and confirm it is read-only. Save one Draft.
3. Review commercial values against the immutable request snapshot. Make a
   documented draft adjustment only when its actor is authorized; an
   Administrator must supply a reason for an exceptional direct adjustment.
4. Issue the quotation. In Track Your Request, open the issued quotation and
   verify event dates/times and deadlines use Asia/Manila.

**Pass criteria:** there is one logical quotation case for the request, with a
numbered Draft then an immutable Issued Version. Preparing does not make a
second case. An accepted quotation is not displayed as a confirmed reservation.

### C-05 — First receipt acceptance and quotation revision history

**Actor:** anonymous customer, then Administrator.

1. From the issued quotation, upload one fictional JPEG, PNG, or WebP receipt
   before the quoted upload cutoff.
2. Verify that it accepts the displayed quotation Version and creates a
   Pending Verification payment record, without claiming that money was
   received or a reservation was confirmed.
3. Administrator creates a revision, issues it, and compares Version history.
   Confirm the original issued/accepted snapshot is still readable and
   unchanged.
4. Where the change requires it, have the customer explicitly agree to the
   new issued revision through Track Your Request.

**Pass criteria:** historical versions remain immutable and numbered; the new
version does not silently replace prior commercial facts. A receipt is not a
payment verification or capacity allocation.

## Financial integrity scenarios

### F-01 — Pending receipt and Administrator verification

**Actor:** Administrator.

1. Open the quotation/payment workspace for C-05.
2. Confirm the uploaded receipt is **Pending Verification** and does not
   change verified balances.
3. Verify it with a fictional received amount less than R and an appropriate
   internal/customer-visible message where offered.

**Pass criteria:** the payment becomes Verified with an immutable review fact;
it is allocated only to an eligible accepted quotation Version in the same
logical quotation case. The page shows a reservation-payment shortfall and
does not enable ordinary confirmation.

### F-02 — Threshold, partial, full, and overpayment presentation

**Actor:** Administrator.

1. Record/verify an additional fictional payment that brings the verified
   allocation to at least R but less than T.
2. Verify that the progress and internal views distinguish **reservation
   payment satisfied** from a zero full balance.
3. Record/verify another payment that brings the balance to T. If safe for
   the isolated UAT data, record a small additional verified amount.

**Pass criteria:** balances use verified active allocations only. Partial
payments remain valid; threshold satisfaction permits availability review but
does not confirm automatically. A full payment yields no remaining full
balance; an overpayment is retained and displayed separately, never
automatically refunded.

### F-03 — Rejection, evidence replacement, deadline, and allocation controls

**Actor:** customer and Administrator.

1. Submit a second fictional receipt. Have the Administrator reject it with a
   reason, then replace the evidence through the customer portal while the
   upload window remains open.
2. Confirm every evidence version remains available privately to authorized
   staff/administrators and the customer sees only safe status.
3. Attempt a new or replacement upload after the frozen cutoff using an
   appropriate isolated test quotation.
4. After an accepted commercial revision, use the explicit Administrator
   allocation/reallocation workflow only if the current accepted Version needs
   an existing verified payment.

**Pass criteria:** rejected/replaced proof retains append-only history; the
cutoff blocks public upload but does not erase prior evidence. Reallocation
records a reason and supersedes rather than rewrites the prior allocation.
Draft, unaccepted, unrelated, or withdrawn quotation Versions cannot receive a
new verified allocation.

## Reservation confirmation and capacity scenarios

### R-01 — Administrator confirmation gate

**Actor:** Administrator, with a Staff observer.

1. Use a quotation whose newest accepted Version has verified allocations of at
   least R. Open **Reservations** and review payment readiness and live
   capacity.
2. Have the Staff observer attempt the same confirmation action.
3. Administrator confirms the reservation.

**Pass criteria:** Staff can view only when granted `reservation.view` and
cannot finalize. The Administrator confirmation creates the reservation and
capacity snapshot transactionally. Acceptance, payment verification, and
threshold satisfaction alone never create a reservation or consume capacity.
Late-but-sufficient verified payment remains visible as overdue history but
does not automatically block confirmation.

### R-02 — Same-date capacity and guest-capacity behavior

**Actor:** Administrator.

1. Using distinct fictional requests, confirm events for the same Manila date
   until C is reached.
2. Attempt one additional confirmation on that date.
3. If an optional guest limit is configured, repeat using quantities that
   reach that configured limit. If it is unset, record guest-limit testing as
   not applicable.
4. Create a per-date override and repeat the capacity review with its
   documented value.

**Pass criteria:** only Confirmed and Completed reservations consume event
capacity. Inquiries, quotations, payments, Pencil Bookings, deferred/declined
reviews, and pending cancellations never consume it. The additional
confirmation fails with a clear capacity explanation and no partial
reservation write.

### R-03 — Completion, defer/decline, and competing requests

**Actor:** Administrator.

1. On a separate eligible quotation, record a deferred or declined
   confirmation decision with a reason. Inspect the preserved payment facts.
2. Attempt to mark a confirmed reservation complete before its scheduled
   four-hour Manila event end, then complete it after the event end.
3. Recheck capacity after completion.

**Pass criteria:** defer/decline preserves financial history and identifies
unresolved obligations without cancelling or refunding. Early completion is
rejected. Completed reservations continue to consume their event-date
capacity; a completed reservation cannot be re-confirmed.

## Change, rescheduling, cancellation, and refund scenarios

### CH-01 — Customer or Staff change request

**Actor:** verified Track Your Request customer and UAT Coordinator.

1. For a confirmed reservation, submit a date/time/venue/guest/menu/add-on
   change through Track Your Request. Submit a separate Staff-prepared request
   only if the Staff account has the explicit change capability.
2. Confirm neither submission changes the current confirmed event, quotation,
   payment allocation, or capacity record.
3. Have an Administrator decline one request with a reason. For another,
   approve it. Supply an explicit exception reason if the active policy makes
   the date move short-notice or menu change late.

**Pass criteria:** exactly one active change request exists per reservation.
The request preserves original/proposed facts and lifecycle history. Staff
cannot apply the change or bypass Administrator review.

### CH-02 — Commercial revision, agreement, payment allocation, and
re-confirmation

**Actor:** Administrator and customer.

1. For an approved commercial change, review/issue the Draft quotation
   revision.
2. Customer agrees to the new version in Track Your Request.
3. Review whether the revised accepted Version still meets R. Explicitly
   reallocate a verified payment only when appropriate.
4. Administrator applies the change and re-confirms after live capacity is
   rechecked.

**Pass criteria:** published prior Versions and payment allocation history are
unchanged. The system never silently moves money, changes the confirmed
reservation, or swaps date capacity. Applying a change locks the source/target
date capacity and leaves the original reservation intact if payment or
capacity fails.

### CH-03 — Cancellation, capacity release, and refund review

**Actor:** customer, Staff, and Administrator.

1. Submit a customer cancellation request for a confirmed reservation. Have
   Staff prepare a different case if authorized.
2. Verify the pending request does not release capacity. Administrator reviews
   one as declined and one as finalized cancellation with a meaningful reason.
3. Confirm only the finalized cancellation changes the confirmed reservation
   to Cancelled and releases its event-date capacity.
4. For a verified payment on the same quotation case, create a partial refund,
   approve/reject it as appropriate, then mark it complete only after the
   fictional offline return is documented. Add private evidence if available.

**Pass criteria:** cancellation and refund are separate reviewed workflows.
Refund approval is not payment transfer; completed refund amount is explicit.
Original acceptance, payment, allocation, and quotation history remain
unchanged. Completed reservations cannot use ordinary cancellation.

## Pencil Booking and manual-request scenarios

### M-01 — Pencil Booking lifecycle

**Actor:** UAT Coordinator with pencil-booking permissions.

1. Create a Pencil Booking with fictional contact, tentative event date,
   notes, and follow-up date.
2. Edit notes/follow-up, close one booking, and mark another Did Not Proceed.
3. Inspect lifecycle history and try conversion from each non-active booking.

**Pass criteria:** Pencil Bookings are non-binding and never use capacity.
Closed/Did Not Proceed bookings retain history and cannot be converted.

### M-02 — Conversion and internal manual request

**Actor:** UAT Coordinator with both Pencil Booking and Reservation Request
management.

1. From an active Pencil Booking, start **Create reservation request**. Check
   that contact/event facts are prefilled, configure a published Main Catering
   package and optional live add-ons, then save.
2. Retry conversion or reopen the linked Booking.
3. Separately use **Reservation Requests → Create request** to make a manual
   request with the same type of published configuration.

**Pass criteria:** conversion is transactional/idempotent: one linked request,
two-way provenance, and append-only booking history. Manual and converted
requests reuse the public request validation/pricing/snapshot engine. Neither
path fabricates a quotation, acceptance, payment, capacity hold, or confirmed
reservation.

### M-03 — Emergency internal booking exception

**Actor:** Staff and Administrator.

1. Attempt an internal manual request and Pencil Booking conversion within the
   current advance-notice period as Staff.
2. Attempt the same as Administrator without an exception reason, then with a
   meaningful reason.
3. Inspect the resulting request and Activity History.

**Pass criteria:** Staff cannot forge an exception. Administrator approval is
explicit, immutable, reasoned, and policy-snapshotted. It changes neither
quotation/payment/acceptance facts nor operational capacity.

## Authentication, authorization, and security scenarios

### A-01 — Internal login and public separation

1. As anonymous visitor, open `/`, browse public offerings, and inspect
   desktop/mobile navigation.
2. Confirm no Admin/Staff/Sign In promotion appears publicly. Visit `/login`
   directly and sign in with each fictional internal account.
3. While authenticated, return to `/`.

**Pass criteria:** `/` remains public for anonymous and authenticated users;
`/login` remains the manual internal entry point. Public browsing never
redirects to login. Logout removes internal access but does not prevent public
browsing.

### A-02 — Least privilege and direct-route checks

For UAT Viewer and UAT Restricted, attempt direct URLs/actions for:

- creating/issuing a quotation;
- verifying/rejecting/reversing/reallocating a payment;
- confirmation, defer/decline, completion, capacity override;
- refund review/completion/private evidence;
- Business Policy changes;
- Staff/permission administration.

**Pass criteria:** Laravel rejects unauthorized writes and protected reads
server-side. The interface either omits unavailable actions or states the
permission limitation and safe next step. A crafted request must not gain
authority.

### A-03 — Activity and historical integrity review

As Administrator, open **Activity History** and the domain histories on the
request, quotation, payment, reservation, change, cancellation/refund, and
Pencil Booking records used above.

**Pass criteria:** expected actor/timestamp/reason events exist without
duplicating private customer data in the general audit ledger. Issued
quotation, accepted payment evidence, allocation, confirmation, status, and
refund histories are presented as retained history rather than editable facts.

## Usability and responsive review

Run these checks at a narrow mobile viewport and ordinary desktop width:

- [ ] Customer can discover a published Main Catering package, choose foods,
  see the required/remaining count, reach inquiry review, and understand that
  it is an inquiry—not a date hold.
- [ ] Validation identifies the affected field or section, including a
  different tab/section; keyboard focus/scroll behavior makes the first
  issue reachable.
- [ ] Track Your Request separates receipt submitted, pending verification,
  partial payment, threshold satisfied, awaiting confirmation, confirmed,
  completed, cancellation review, and refund completion. Color is not the
  only status cue.
- [ ] Internal case workflow distinguishes inquiry, quotation, payment, and
  reservation states; accepted quotation is never labelled confirmed.
- [ ] Staff see only permitted action controls, and blocked actions explain
  the next safe step.
- [ ] Date/time presentation is consistent with Asia/Manila.
- [ ] Tables, dialogs, forms, primary actions, and error messages remain
  usable without horizontal clipping or hover-only instructions.

### Public selection navigation

The persistent public-header **My selection** link is intentionally absent.
It was removed under the owner's approved presentation preference. After a
customer saves a package choice, the relevant configurator presents explicit,
contextual actions to choose optional services or continue to event details.
The temporary browser selection remains neither a cart nor an availability,
price, authentication, or reservation claim.

## Defect-report template

Record one report per issue:

```text
UAT ID / scenario:
Date and browser/device:
Role and fictional account:
Record references (request / quotation / payment / reservation):
Preconditions and Business Policy values (R, T, C where relevant):
Exact steps:
Expected result:
Actual result:
Screenshot or safe redacted evidence:
Severity: blocker / major / minor / presentation
Repeatable?:
```

Classify a **blocker** when it creates/changes financial, capacity, access, or
historical facts incorrectly; exposes private data; allows an unauthorized
action; or prevents a critical workflow. Do not alter live data to “repair”
the test result—preserve the record references for investigation.

## Deferred and production-review boundaries

The following are not UAT pass criteria because they are not implemented as
customer-facing production workflows: customer accounts, payment gateways or
automatic collection/verification, automated notification delivery,
scheduling/crew assignment, inventory allocation, and automatic payout/refund
execution.

Before production launch, the owner still needs a separate review of real
payment instructions, commercial terms, legal/privacy policy, business
continuity and backups, hosting/HTTPS, mail/SMS delivery if introduced,
retention/access governance, monitoring, operational capacity policy,
disaster recovery, security review, and production data migration. Passing
this checklist validates the implemented development workflow; it does not
approve commercial launch.

## UAT completion checklist

- [ ] All applicable scenarios above have a pass/fail/not-applicable result.
- [ ] All blocker and major reports have an owner disposition.
- [ ] Administrator, least-privileged Staff, and anonymous customer paths have
  been exercised.
- [ ] No real personal or financial data remains in the UAT environment.
- [ ] The owner has separately decided whether the protected
  `zek_reservation` database needs a controlled migration/backup plan.

## Manual testing readiness

**READY FOR FULL USER ACCEPTANCE TESTING** in a controlled development/UAT
environment, once the owner has chosen a safe database path, reviewed the
current Business Policies, and prepared only fictional data. This status means
the guide covers the implemented end-to-end workflows for Administrator,
least-privileged Staff, and anonymous-customer perspectives. It does not
certify production operations or the deferred capabilities listed above.
