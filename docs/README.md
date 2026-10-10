# Catering Catalog Specification V3
Updated 2026-10-10 with the approved dynamic-collection Phase 3.5 architecture. Phases 1–3 are implemented; Phase 3.5 adds administrator-created collections and an empty-catalog setup flow. Publication and later phases remain specifications only. Read:

1. [Architecture and approved rules](docs/01-architecture.md)
2. [Acceptance criteria](docs/02-acceptance-cases.md)
3. [Code organization](docs/03-code-organization.md)
4. [Implementation-agent instructions](docs/04-codex-start-prompt.md)
5. [Database entities and constraints](docs/05-database-entities.md)
6. [Implementation plan and remaining decisions](docs/06-implementation-plan.md)
7. [Updated logical ERD](diagrams/catalog_v3.drawio)
8. [Phase 1 implementation decisions and setup](docs/07-phase-1-foundation.md)
9. [Phase 2 master catalog implementation decisions](docs/08-phase-2-master-catalog.md)
10. [Phase 3 package drafts and pricing implementation](docs/09-phase-3-package-drafts.md)
11. [Phase 3.5 dynamic collections and migration](docs/10-phase-3-5-dynamic-collections.md)
12. [Admin and Staff guide](docs/11-admin-staff-guide.md)

The former five family names are examples, not seeded or required business records. One primary collection and unlimited add-on collections can be created by Admin using controlled behavior types. Customer accounts, reservations, quotations, payments, scheduling, inventory and notifications are deferred. Future published package facts freeze; availability stays current; direct-sale pricing will use published listing revisions.

Phases 1–3 provide access control, master catalog administration, package draft configuration and administrator-only price previews. Publication, direct listings, reservations and other transaction workflows remain unimplemented. Commercial configuration values must be approved before production publication; examples remain fixtures. Routine database decisions are documented and do not require repeated approval. Read all specifications before writing migrations.
