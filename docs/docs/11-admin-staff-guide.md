# Admin and Staff owner walkthrough

This guide describes the current Admin/Staff catalog and package workflows. Automated browser fixtures erase only `zek_catering_test` on `127.0.0.1:5432`; do not run them concurrently with PHP database tests. The owner development database is not a test target.

## Necessary Main Catering setup

1. In **Food Catalog**, create Food Items and select their categories. Creating a category in the Food Item dialog is optional and preserves the current package input.
2. In **Catering Packages**, choose **Create Package** and enter Package Name in **Details**. Description is optional; no package is created until Save Package succeeds.
3. In **Menu**, choose the exact distinct-food total and allow Food Items by category. A sole allowed Food Item in a required category becomes included automatically. **Always Include** explicitly fixes an allowed Food Item. Fixed foods count once toward the total.
4. In **Pricing & Quantity**, enter the price, minimum and increment. Maximum is optional.
5. In **Review & Save**, save the complete Draft transactionally. Review it, acknowledge the facts, then explicitly confirm publication when it is ready. Saving is never publication.

## Add-on Services

Create a dynamically named service in this business order:

1. Enter **Service details**: name and optional description.
2. Choose **How will you sell this service?**
    - **Sold by Package** creates complete packages with fixed or customer-selectable Food Items.
    - **Sold Individually** keeps the individual listing workflow and does not show package-format controls.
3. For a packaged service, choose **How are foods provided?**
    - **Separate Food Servings** means each Food Item uses the service presentation unless a Food Option explicitly changes packaging.
    - **Combined Contents** means each purchased unit is one complete pack or box. Its physical container is never multiplied by its Food Item count.
4. Select the default physical presentation quantity and Measurement Unit. The preview explains the actual entered facts. This packaging setting does not describe food weight or food portion.

For a standard package: create the package, select Food Items, set the exact menu total and pricing, then save. Selected Food Items inherit the service presentation without repeated input. **Food Options** is one collapsed surface per Food Item for intentional advanced choices: standard serving, fixed portion, size choices, selection charge and a packaging exception. Existing rules keep incompatible configurations from being saved together.

The package-wide **Default Food Portion** is optional and appears in **Advanced Package Settings** in the Menu step for non-Main Catering packages. It is collapsed when unused. A persisted portion is visibly identified as configured and remains editable; it is not inferred from packaging.

Legacy packed-meal and fixed-bundle services preserve their existing contracts. Their stable behavior codes are not selected from editable names.

## Measurement Units

Use **Add Measurement Unit** to create and select a new active unit in the current Service or Package form. Use **Manage Measurement Units** to search, create, edit, activate, deactivate or safely delete units. The manager synchronizes its active list back to the parent form without a reload or loss of unsaved input. Referenced units cannot be renamed or deleted; deactivation retains saved and Published labels but prevents new selection.

## Revisions and availability

**View Details** defaults to the current Published Version. Authorized editors can separately preview a Draft. Editing a Published package creates or updates a separate Draft; it never changes historical facts. Publication History can clone an older publication into a higher-numbered Draft. Disable is reversible; Archive retains history.

## Local development initialization

For a disposable local database only, run `php artisan migrate:fresh`, then `php artisan db:seed`. The local-only bootstrap creates roles, permissions, the primary service, `Admin Tester` (`admin@gmail.com` / `adminpass123`), 11 Food Categories, 42 Food Items and four published Main Catering packages. Packages A–D require 4–7 distinct customer choices, including exactly one Rice and one Drink; they can be used immediately in the normal public inquiry flow. The untouched initial policy requires a 20% reservation payment. Rerunning the seed does not duplicate fixtures or overwrite the known Administrator password, an edited fixture, or an Administrator-managed Business Policy. Outside the local environment, provision the first Administrator with `php artisan app:create-admin`.

## Cancellation and refunds

Open a quotation from **Reservations** to prepare a cancellation request, or
use **Cancellations & Refunds** to find cases awaiting review. Staff with the
explicit cancellation permission can prepare or view a case but cannot
finalize it, approve a refund, record a completed disbursement, or open private
refund evidence. An Administrator reviews a reason before finalizing; only a
finalized confirmed-reservation cancellation releases date capacity.

Where verified money exists, create a full or partial refund against its
matching payment. Approval is not proof of money returned. Record completion
only after the offline transfer/cash return actually happened, and attach
private evidence if available. If the recorded completed amount is wrong, add a
reasoned correction rather than editing history. The customer sees safe status
updates in Track Your Request but never internal notes or evidence.

## Staff and current limitations

Administrators retain full access. Staff access is capability-based; catalog, service, package, pricing, publication, history and lifecycle permissions remain separate, server-enforced capabilities. Account and permission changes retain last fully authorized Administrator protection.

Customer inquiries, reservations, quotations, checkout, payments, notifications, inventory and scheduling are deferred. This is an Admin/Staff catalog milestone, not a commercial-production or customer-acceptance release.
