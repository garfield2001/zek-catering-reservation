# Phase 5 Add-on experience and local bootstrap

Phase 5 refines existing commercial configuration. It adds no customer, reservation, quotation, payment, inventory, or notification capability, and it changes no published commercial facts.

## Add-on Service setup

The create-Service dialog follows the business order: **Service details**, **How will you sell this service?**, then, only for **Sold by Package**, package format and default presentation. The two selling choices retain stable behavior codes behind the UI:

- **Sold by Package** creates complete packages with fixed or customer-selectable Food Items.
- **Sold Individually** keeps the existing individual listing workflow and hides package-format controls.

For packaged services, **Separate Food Servings** explains that each Food Item uses the configured presentation unless it has an exception. **Combined Contents** explains that each purchased unit is one complete pack or box; the interface never multiplies that container by the constituent Food Item count. The presentation preview derives from the entered quantity and selected active Measurement Unit. A missing value remains visibly incomplete rather than being represented as a tray or quantity one.

## Package builder

Package composition, Food Options, pricing, Draft saving, review, publication, history and cloning remain the existing shared workflow. Standard Food Items need only be selected: they inherit the service presentation. Each Food Item retains one collapsed **Food Options** surface for mutually compatible standard serving, fixed portion, size choices, selection surcharge and presentation exception configuration.

The optional package-wide **Default Food Portion** is now inside **Advanced Package Settings** in the Menu step for non-Main Catering packages. It is collapsed when unused. If a Draft already has a common portion, the summary says that it is configured and opens the area, so the persisted setting is not hidden. Physical presentation and food portion remain separate facts.

## Measurement Unit synchronization

`MeasurementUnitManager` remains the sole management surface and reuses the existing authorized API, Requests, Actions, Policies, audit trail and PostgreSQL safeguards. Its refreshed active list is supplied back to its owning Service or Package form after create, edit, deactivate, reactivate or safe deletion. Quick creation still selects the new active unit in its originating selector. Neither action reloads or discards unsaved parent state.

Referenced units cannot be renamed or deleted; deactivation preserves saved and published labels but removes the unit from new selection. Backend active-unit validation remains authoritative.

## Local development bootstrap

For a new disposable **local** database only:

```powershell
php artisan migrate:fresh
php artisan db:seed
```

The regular `DatabaseSeeder` creates roles, permissions, Staff's initial dashboard grant and the primary service first. It then invokes `LocalDevelopmentAdministratorSeeder` and `DevelopmentDemoSeeder` only for `APP_ENV=local`. This supplies the active `Admin Tester` development account (`admin@gmail.com` / `adminpass123`), 11 Food Categories, 42 Food Items and four incomplete Main Catering Drafts (A-D at PHP 380/450/480/520 per guest, minimum 150, increment 50, no maximum).

The local account and fixtures are idempotent. Existing password, access and ordinary commercial edits are not overwritten; identity conflicts fail clearly. Production and staging do not receive known credentials or demo commercial records. Their first Administrator remains the secure, interactive `app:create-admin` workflow.

## Schema and historical boundary

No schema migration or ERD change is required. This milestone uses the existing Collection -> Product -> Package Version model, stable behavior codes, active Measurement Unit rule, version ownership guards and immutable Published snapshots. Service defaults apply when a new package is created; later service edits never rewrite existing Drafts, Published Versions or historical snapshots.
