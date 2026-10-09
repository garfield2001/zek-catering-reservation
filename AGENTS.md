# Repository working rules

## Git ownership

The repository owner stages, commits, and pushes through GitHub Desktop. Do not run `git add`, `git commit`, `git push`, amend, reset, rebase, force-push, or rewrite history. Use read-only Git commands for inspection. Preserve unrelated work and never expose credentials or secrets. After a meaningful implementation milestone, suggest a Conventional Commit subject and a useful body; leave all Git mutations to the owner.

## Code architecture

Treat `docs/` as the primary product specification, with later explicit user decisions taking precedence. Implement only the currently authorized phase. Use a modular Laravel monolith: thin controllers, Form Requests for HTTP validation, Actions for meaningful workflows, focused services for reusable rules, Policies for authorization, Eloquent relationships and dedicated queries where useful. Keep transactions and backend business rules authoritative. Avoid generic repositories, oversized controllers, duplicate validation or business logic, unnecessary interfaces, and future-phase placeholders.

Organize React/TypeScript by feature. Keep pages small and compose reusable components; introduce hooks when stateful behavior warrants them. Keep shared UI components and types focused. Validate important failure paths with PostgreSQL integration and feature tests, then run Pint, Larastan, ESLint, Prettier, TypeScript, and the production build. Report only checks actually run.
