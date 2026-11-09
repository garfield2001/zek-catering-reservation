# Current catering administration specification

The latest owner-approved [Milestone B commercial catalog](docs/18-milestone-b-commercial-catalog.md) supersedes conflicting earlier phase rules. Read it with the [owner walkthrough](docs/11-admin-staff-guide.md), [clean baseline](docs/14-clean-schema-migration.md) and editable [Draw.io ERD](diagrams/catalog_v3.drawio). Collection → Product → Package Version is the current architecture. Independent package quantities replace inheritance; the shared food/category menu builder replaces ordinary manual Selection Group forms. Package publication is implemented; individual listing publication and Food Item images are implemented in Milestone B; customer transactions remain deferred. Milestone B verification has passed; see its report for exact results and the required owner-approved development migration.

The [final execution and handoff report](docs/16-final-refactor-verification.md) records local verification, screenshots, coverage reconciliation and manual-testing boundaries.

The [Milestone A UI modernization report](docs/17-milestone-a-ui-modernization.md) records the current component decisions, verification and owner-testing scope.

## Historical phase references

The [service-owned package configuration work](docs/23-service-package-configuration.md) records the approved Phase 3 change from version-only format selection to service configuration, migration safeguards, implementation boundaries, verification, and the owner transition path.

The [Phase 3.1 package-builder and Measurement Unit refinement](docs/24-package-builder-and-measurement-units.md) records the current simplified Food Options workflow, dynamic units, historical-label decision, and safety boundaries.
The [Phase 5 Add-on experience and local bootstrap refinement](docs/26-phase-5-addon-experience-and-local-bootstrap.md) records the current progressive service/package workflow, synchronized Measurement Units, and local-only development initialization.
The [Phase 5.1 catalog experience](docs/27-phase-5-1-catalog-experience.md) records the scalable Food Catalog, guided food discovery, independent-listing bulk Draft workflow, and account-image boundary.
The [Phase 6 public storefront](docs/28-phase-6-public-storefront.md) records published-only customer browsing, server-authoritative planning estimates, temporary browser selections, and the deliberate separation between the public site and internal `/login` entry point.
The [Phase 6.1 page-based commercial configuration](docs/29-phase-6-1-page-based-commercial-configuration.md) records the dedicated Admin configuration pages and the persisted customer-choice category bounds used by package menus.
The [Phase 6.2 Main Catering editor refinement](docs/30-phase-6-2-main-catering-editor.md) records the previous single-page editor and the owner-controlled pending migration required by the local development database. The [Phase 6.3 routed Main Catering workflow](docs/31-phase-6-3-routed-main-catering-workflow.md) supersedes its editing experience with persisted Details, Pricing, Menu and Review pages, category-first menu discovery, and explicit Draft review.
The [Phase 6.3.1 Admin UI refinements](docs/32-phase-6-3-1-admin-ui-refinements.md) records compact category configuration, bounded shared dropdowns, catalog list sorting/pagination, and scoped scrollbar behavior.
The [post-commercial-configuration handoff](docs/33-post-commercial-configuration-handoff.md) records the implemented checkpoint, the intentionally partial add-on UI consistency refinement, and the recommended reservation-request-first next phase.
The [Reservation Request Intake](docs/34-reservation-request-intake.md) record defines the inquiry-only lifecycle, immutable commercial snapshots and Customer Access Code-protected **Track Your Request** portal.
The [Quotations and Acceptance](docs/35-quotations-and-acceptance.md) record defines the versioned quotation lifecycle, structured Business Policies, immutable issuance snapshots, receipt-driven portal acceptance and manual payment verification.
The [Reservation Changes and Rescheduling](docs/37-reservation-changes-and-rescheduling.md) record defines the request/review/revision/agreement/re-confirmation workflow, frozen policy facts, explicit payment allocation and capacity-safe date moves.
The [Cancellation and Refund Management](docs/38-cancellation-and-refund-management.md) record defines reviewed customer withdrawals and reservation cancellations, manual Administrator refund recording, immutable financial history, and customer-safe progress updates.

The [Phase 2 maintainability review](docs/22-maintainability-review.md) records the current reliability follow-up's code-quality changes, intentional size exceptions, verification and owner-testing scope. Its phase numbering belongs to the Application Reliability milestone, not the original catalog implementation phases below.

1. [Architecture and approved rules](docs/01-architecture.md)
2. [Acceptance criteria](docs/02-acceptance-cases.md)
3. [Code organization](docs/03-code-organization.md)
4. [Historical implementation-agent kickoff (superseded)](docs/04-codex-start-prompt.md)
5. [Database entities and constraints](docs/05-database-entities.md)
6. [Implementation plan and remaining decisions](docs/06-implementation-plan.md)
7. [Updated logical ERD](diagrams/catalog_v3.drawio)
8. [Phase 1 implementation decisions and setup](docs/07-phase-1-foundation.md)
9. [Phase 2 master catalog implementation decisions](docs/08-phase-2-master-catalog.md)
10. [Phase 3 package drafts and pricing implementation](docs/09-phase-3-package-drafts.md)
11. [Phase 3.5 dynamic collections and migration](docs/10-phase-3-5-dynamic-collections.md)
12. [Admin and Staff guide](docs/11-admin-staff-guide.md)
13. [Approved domain terminology and compatibility audit](docs/domain-terminology-audit.md)
14. [Pre-Phase-4 access safeguards and terminology implementation](docs/12-pre-phase-4-access-and-terminology.md)
15. [Phase 4 package publishing and version lifecycle](docs/13-phase-4-package-publication.md)
16. [Phase 4 package lifecycle ERD](diagrams/phase4-package-lifecycle.mmd)

The former five family names are examples, not seeded or required business records. One primary Collection is provisioned automatically; Administrators create dynamically named add-on services using controlled behaviors. Customer accounts, confirmed reservations, payment verification, scheduling, inventory and notifications are deferred. Phase 4 package publication freezes commercial facts while checking live availability; individual-sale pricing uses immutable listing_versions snapshots.

Phases 1–6.3.1 provide access control, catalog/package/listing publication, public published-offering browsing, and commercial administration. Reservation Requests add inquiry-only requests, Staff review and protected customer progress; Quotations add explicit drafts, structured Business Policies, issuance, receipt-driven acceptance and manual payment verification, but not automatic payment collection, confirmed-reservation acceptance, notifications, customer accounts, availability holds, scheduling or inventory. Read all specifications before writing migrations.
