# Repository working rules

## Git ownership

The repository owner stages, commits, and pushes through GitHub Desktop. Do not run `git add`, `git commit`, `git push`, amend, reset, rebase, force-push, or rewrite history. Use read-only Git commands for inspection. Preserve unrelated work and never expose credentials or secrets. After a meaningful implementation milestone, suggest a Conventional Commit subject and a useful body; leave all Git mutations to the owner.

## Code architecture

Treat `docs/` as the primary product specification, with later explicit user decisions taking precedence. Implement only the currently authorized phase. Use a modular Laravel monolith: thin controllers, Form Requests for HTTP validation, Actions for meaningful workflows, focused services for reusable rules, Policies for authorization, Eloquent relationships and dedicated queries where useful. Keep transactions and backend business rules authoritative. Avoid generic repositories, oversized controllers, duplicate validation or business logic, unnecessary interfaces, and future-phase placeholders.

Organize React/TypeScript by feature. Keep pages small and compose reusable components; introduce hooks when stateful behavior warrants them. Keep shared UI components and types focused. Validate important failure paths with PostgreSQL integration and feature tests, then run Pint, Larastan, ESLint, Prettier, TypeScript, and the production build. Report only checks actually run.

## Domain terminology and naming review

Before adding or renaming a domain concept, check the approved vocabulary against its database definition, Laravel model and relationships, policies, routes, API/Inertia props, TypeScript types, UI labels, validation messages, tests, ERD, and documentation. Use one name for one concept and preserve distinct names for different concepts; avoid synonyms introduced only for stylistic variety. Record any intentional legacy identifier or compatibility boundary. The terminology proposal in `docs/docs/domain-terminology-audit.md` is pending owner approval, not a blanket rename instruction. The Phase 3.5 documentation defines the currently approved behavior, and later explicit owner decisions take precedence.

Persisted codes and behavior types are stable internal identifiers. Editable customer-facing names, descriptions, and variant labels are presentation data and must not select pricing, eligibility, authorization, or validation behavior. Prefer the current configured name and resolved quantities/units in user-facing messages. Do not rewrite applied migrations, break route/permission identifiers, or alter historical audit data for cosmetic consistency; plan coordinated, reversible changes with compatibility tests.

## Business policy and milestone reporting

Before implementing a new milestone, separate approved business rules, routine technical decisions, unresolved business policies, and explicitly deferred features. Ask for approval when an unresolved policy materially changes business behavior; do not invent reservation, quotation, payment, cancellation, capacity, or fulfillment rules. Only implement the authorized phase.

Every future phase completion report must include a section titled `MANUAL TESTING READINESS`. Classify it as `NOT YET READY`, `LIMITED WORKFLOW TESTING`, `READY FOR MILESTONE TESTING`, or `READY FOR FULL USER ACCEPTANCE TESTING`. Distinguish technical implementation, individual workflow, integrated business workflow, and commercial-production readiness. State fully implemented and partial workflows, deferred features, blocking dependencies, the milestone for broader testing without inventing dates, testable Admin/Staff/Customer perspectives, guided steps and expected outcomes only for genuinely ready workflows, and normal current limitations. Never equate passing automated checks with owner acceptance or commercial readiness. Report checks actually run as passed, failed, skipped, or environment-blocked; do not present previous results as newly executed.
