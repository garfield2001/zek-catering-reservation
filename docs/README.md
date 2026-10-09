# Catering Catalog Specification V3
Updated 2026-10-09 with client-approved clarifications and database design decisions. Phases 1 and 2 are authorized and implemented; later phases remain specifications only. Read:

1. [Architecture and approved rules](docs/01-architecture.md)
2. [Acceptance criteria](docs/02-acceptance-cases.md)
3. [Code organization](docs/03-code-organization.md)
4. [Implementation-agent instructions](docs/04-codex-start-prompt.md)
5. [Database entities and constraints](docs/05-database-entities.md)
6. [Implementation plan and remaining decisions](docs/06-implementation-plan.md)
7. [Updated logical ERD](diagrams/catalog_v3.drawio)
8. [Phase 1 implementation decisions and setup](docs/07-phase-1-foundation.md)
9. [Phase 2 master catalog implementation decisions](docs/08-phase-2-master-catalog.md)

The five families comprise Main Catering plus four independent add-on families: Belly Lechon, Food Pack, Food Tray and Pica-Pica. Customer accounts, reservations, quotations, payments, scheduling, inventory and notifications are deferred. Published package facts freeze; availability stays current; Food Tray pricing uses published listing revisions.

Phases 1 and 2 provide access control and master catalog administration. Package configuration, publication, direct listings, reservations and other transaction workflows remain unimplemented. Commercial configuration values must be approved before production publication; examples remain fixtures. Routine database decisions are documented and do not require repeated approval. Read all specifications before writing migrations.
