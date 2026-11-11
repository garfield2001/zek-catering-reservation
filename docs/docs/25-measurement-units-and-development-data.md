# Measurement Units and Development Data

Phase 4 introduced shared Measurement Units and development fixtures. Phase 5 makes their synchronization reliable in the Add-on Service and Package Builder, and makes the development bootstrap predictable. It does not add reservation, inventory, pricing conversion, or customer-facing behavior.

## Measurement Unit management

`MeasurementUnitManager` is the single React management surface. It is available from **Food Catalog → Catalog Settings → Manage Measurement Units** and from each Package Builder measurement control.

- **Add Measurement Unit** remains the quick-create flow in the Service form and Package Builder. A successful creation selects the new unit in the originating field.
- **Manage Measurement Units** supports search, create, edit, activate/deactivate, and safe deletion without discarding the parent Service or Package editor state. Every successful manager refresh emits the current active list to the owning form, so a newly created, reactivated, edited, or removed unit is immediately reflected by its selectors.
- The manager reuses `MeasurementUnitController`, `SaveMeasurementUnit`, `DeleteMeasurementUnit`, `MeasurementUnitUsage`, the existing Requests, Policies, auditing and PostgreSQL constraints. It does not introduce another CRUD path.
- Editing the name or symbol of a referenced unit is prevented by the existing backend rule and the modal makes the reason clear. Referenced units can be deactivated, preserving saved package and published facts.
- The former `/admin/catalog/measurement-units` route remains as a compatibility route. A normal browser visit redirects to the Food Catalog; the modal obtains the same authorized data through its JSON representation.

Only users authorized to view the catalog can obtain the unit list. Only users with catalog-management access can write. The server remains authoritative for direct requests.

## Development fixtures

On an explicitly local environment, `DatabaseSeeder` invokes the local bootstrap in dependency order: roles, permissions, the primary service, the current Business Policy default, the local Administrator, Food Catalog fixtures, then published Main Catering fixtures. The expected fresh local sequence is:

```powershell
php artisan migrate:fresh
php artisan db:seed
```

It creates the known local-only development Administrator `Admin Tester`, followed by the approved 11 demo Food Categories and 42 active Food Items, plus four published Main Catering packages:

| Package   |  Per guest | Food choices | Minimum guests | Increment | Maximum |
| --------- | ---------: | -----------: | -------------: | --------: | ------- |
| Package A | PHP 380.00 |            4 |            150 |        50 | None    |
| Package B | PHP 450.00 |            5 |            150 |        50 | None    |
| Package C | PHP 480.00 |            6 |            150 |        50 | None    |
| Package D | PHP 520.00 |            7 |            150 |        50 | None    |

Every package includes all active demo Food Items from Beef, Chicken, Pork, Desserts, Rice and Drinks. Rice and Drinks are customer choices with exactly one selection each; Beef, Chicken, Pork and Desserts have no extra category quota. Total distinct-food selection remains authoritative in Laravel, so a food cannot be chosen twice. The seed uses the normal version, menu and publication Actions and therefore exercises the ordinary customer selection and inquiry flow.

The seeded initial `BusinessPolicy` converts only the untouched migration placeholder from a zero fixed requirement to a 20% reservation-payment requirement (2,000 basis points). It never replaces a policy updated by an Administrator or any different existing configured value.

The fixtures use stable technical codes for categories and Food Items, and immutable UUID creation tokens for packages. Rerunning the seeders finds records by those identities, does not duplicate them, and does not overwrite Administrator edits to an identified fixture. The local Administrator seeder never resets the known account password or changes an existing account's access; a conflicting inactive/non-Administrator account produces a clear error. A collision on an owned food/category code fails rather than changing an unrelated record. The seeders do not delete, deactivate, reprice, or otherwise modify unrelated or published commercial records.

The automatic account and fixtures are permitted only when `APP_ENV=local`. Test coverage can opt in through the ignored `LOCAL_DEVELOPMENT_SEED_DATA` setting; it is not a production/staging permission. Non-local environments receive the baseline roles, permissions and primary service only. Use the secure `app:create-admin` command for first-Administrator provisioning outside local development.

No database migration or ERD change is required: this increment is presentation and development-data work over the existing schema.

## Manual verification

1. In Food Catalog, open **Catalog Settings** and choose **Manage Measurement Units**.
2. Add a unit, search for it, rename it while unused, deactivate/reactivate it, and delete it after confirming.
3. Create or open an unsaved Add-on Service or Package Draft. In a measurement control use **Add Measurement Unit** and confirm it is selected. Open **Manage Measurement Units**, create a different active unit, close it, and confirm it is immediately selectable while the parent inputs and food selections remain intact.
4. Reference a unit in an allowed package/service configuration. Reopen the manager: renaming and deletion are unavailable with an explanation; deactivation remains available.
5. In a deliberately selected local environment, run the two commands above. Verify the local Administrator, 11 categories, 42 Food Items, and four published packages. Open each public package: choose exactly one Rice and one Drink, fill the remaining 2/3/4/5 distinct choices, and submit a test inquiry. Run `db:seed` a second time and confirm the counts do not grow or overwrite your test edit.
