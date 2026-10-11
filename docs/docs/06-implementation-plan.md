# V3 Implementation Plan and Decision Register

Phase 4 is authorized for package-oriented behaviors. The current additive design is documented in [Phase 4 package publication](13-phase-4-package-publication.md); the original Slice 4 row below is a scope summary, not a claim that all acceptance tests have passed. Direct-sale listing revisions remain a later milestone. Phase 4 completion requires an isolated PostgreSQL migration/upgrade run and the full quality gate.

Status: V3 and phased plan approved; Phases 1–3 implemented. Phase 3.5 adds dynamic collections and first-run administration. Phases 4–7 remain outside the current implementation scope. See 07-phase-1-foundation.md through 10-phase-3-5-dynamic-collections.md.

## Accepted decisions

The former four named add-ons are examples rather than required records. Admin creates one primary catering collection and any number of optional add-on collections using supported behavior types; add-ons alone cannot initiate a future reservation. No reservation coupling is introduced now. Collection codes, roles and behaviors are immutable while names, descriptions, order and active state are configurable. Package products and variants have editable descriptions/order; variant count and configuration are not fixed to example A-D names. Packed meals default to uniform; split allocations share one version, satisfy selections independently and use total quantity rules by default. Package facts freeze at publication, availability stays current, future direct listings use published revisions, all money uses centavos, and Staff publishing/access administration are off by default.

## Routine design decisions resolved

- Canonical dishes, multiple family preparations, composite eligibility FK, typed navigation/media junctions.
- Materialized option sets and explicit relational snapshot columns; live media separated from commercial facts.
- Owner-scoped current publication pointers, revision concurrency checks, transactional locks and database immutability guards.
- No standalone offering price and no redundant product type or untyped global business settings table.
- Typed nullable subgroup constraints reserve future Food Pack per-group restrictions.
- Duplicate dishes checked within and across customizable groups by default; canonical identity prevents bypass through multiple sizes. Deny takes precedence; allow rules whitelist; restricted categories whitelist.
- Archive referenced entities; do not cascade-delete commercial history. Append-only audits with redacted historical locators.
- PHP-only nonnegative BIGINT centavos, exact serving decimals and decimal-string money over the wire.
- Current catalog browsing/price previews only; no persisted quotations or order records.

These are documented defaults, not additional approval gates.

## Implementation slices after authorization

| Slice | Deliverable | Required verification |
|---|---|---|
| 1 | Compatible Laravel/Inertia/React/TypeScript scaffold, PostgreSQL, staff authentication, Policies/permissions, audit and CI | Login/inactive-user/forged-request tests, access precedence, audit transaction tests, migration smoke test |
| 2 | Canonical catalog, families/defaults, eligibility, offerings and serving options | PostgreSQL FK/check/unique tests, family isolation, archival and administrative CRUD |
| 3 | Package draft editor and server selection/quantity/price previews | CAT-01–11, CAT-14–16, CAT-18, CAT-22, CAT-26; split, quota, overflow and precision cases |
| 4 | Package publication, immutable snapshots and availability | CAT-17, CAT-19–20, CAT-23–24; direct SQL mutation, stale drafts and concurrency tests |
| 5 | Direct listing revisions, all-family add-on browsing and navigation | CAT-12–13, CAT-25; authoritative listing prices, publication and hidden ancestors |
| 6 | Media administration, processing and attachment workflows | CAT-21; content validation, fallbacks, reference races and retry tests |
| 7 | Complete workflows and commercial deployment readiness | All acceptance cases; Playwright flows, accessibility, PostgreSQL integration, PHP/TS analysis, builds, backup restore and deployment checks |

Tests accompany each slice; do not defer backend safeguards until UI completion. Only explicitly authorized slices may be implemented. Select compatible supported dependency versions and lock them; do not assume a version from this specification date.

## Remaining business approvals

1. Production configuration matrix: actual variant names/prices, package inclusions and quantities, allowed dish pools/upgrades, premiums, and ordering minimums/steps/maxima beyond the approved Main Catering minimum. Decide any actual exception to default duplicate/category rules through configuration.
2. Approved customer-facing serving claims and imagery. Existing approximate capacities are examples, not guaranteed commercial promises.
3. Before launch: account recovery procedure and MFA requirement, production hosting/storage/backup ownership, and audit/media retention duration.

These do not reopen accepted family, split, pricing, availability or Staff defaults. No additional approval is needed to document routine constraints. Business data can be supplied before production publication; demo/test fixtures must be visibly nonproduction. Tax, delivery fees, reservation conditions and combined-reservation minimums belong to future transaction scope, not invented catalog charges.
