# Architecture Guide

This project should stay feature-first: route files adapt HTTP/pages, feature folders own business behavior, and shared folders only keep reusable primitives.

## Folder Roles

- `app`: Next.js route adapters, layouts, and loading/error boundaries. Keep data orchestration thin here.
- `features/<feature>/domain`: feature types, constants, validation, and pure pricing/business rules.
- `features/<feature>/application`: use cases and workflows that coordinate domain rules and infrastructure calls.
- `features/<feature>/infrastructure`: Supabase clients, API calls, form-data readers, email/SMS adapters, and persistence details.
- `features/<feature>/ui`: feature-specific React components.
- `components`: shared UI building blocks only, not feature workflows.
- `lib`: cross-cutting helpers used by more than one feature.

## Line Count Guide

These are practical ranges, not strict laws:

- `app/**/page.tsx`: 20-120 lines. If it is longer, move feature UI into `features/*/ui`.
- `app/api/**/route.ts`: 10-80 lines. Routes should parse the request, call a use case, and return a response.
- Feature use cases: 60-220 lines. Split when one use case handles unrelated workflows.
- Domain files: 20-140 lines. Keep them pure and framework-light.
- Infrastructure adapters: 30-180 lines. One adapter should usually talk to one outside system or table group.
- UI containers: 80-220 lines. Split when state, form sections, and presentational pieces compete for space.
- Presentational components: 20-120 lines.
- Hooks: 40-160 lines.
- SQL migrations: can be longer, but prefer one concern per migration so schema history stays reviewable.

A file passing 250 lines deserves a quick review. A file passing 500 lines should almost always be split unless it is generated code or a migration.
