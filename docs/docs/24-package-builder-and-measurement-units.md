# Phase 3.1 package builder and Measurement Unit management

## Scope

This refinement makes the administrator's add-on package menu workflow
business-facing without changing the commercial package lifecycle. It covers
Package and Packed Meal food options, active Measurement Units, and the
historical facts stored in package drafts and Published Versions. Customer
transactions, direct-sale listings, reservations, and catalog taxonomy changes
remain outside this work.

## Food options in a package

Each food has one collapsed **Food Options** entry point. An Administrator can
leave it at **Use standard serving**, set a **fixed portion**, or offer **size
choices** where the applicable service permits price choices. The summary
shows the current portion, packaging context, selection charge, and whether a
food is included or chosen by a customer.

Food portion and package/service packaging deliberately remain separate.
Changing a food's portion never changes the purchased container. A general
food selection charge is mutually exclusive with size choices: moving to or
from size choices requires confirmation because the incompatible commercial
configuration is replaced. Main Catering remains unable to configure food
measurements or food-specific premiums.

New fixed portions are written only through an active Measurement Unit selected
by ID. Laravel resolves that ID under a transaction and freezes the unit
abbreviation into the existing `package_version_food_items.included_unit`
fact. This preserves legacy values and Published snapshots without pretending
that a historical label should track later catalog edits.

## Measurement Units

Measurement Units are Administrator-managed catalog records with a name,
symbol, and active status. They are not seeded as a fixed list. Administrators
may create a unit inline while configuring a portion, or manage units from the
catalog unit page. The management page supports search, edit, deactivate,
reactivate, and deletion of an unused unit.

An inactive unit cannot be selected for a new portion. A unit that is
referenced by a Collection default, Package Version, food presentation
override, service packaging rule, or food-size choice cannot be renamed or
deleted through application workflows. It may be deactivated so historical
facts stay readable while new configuration cannot select it. The server
rechecks this rule and authorization; disabled controls are only guidance.

## Database decision

Migration `2026_10_20_000023_allow_configurable_food_portion_units.php`
replaces the obsolete PostgreSQL check that enumerated a small fixed list of
food-portion strings. It retains the integrity rule that a portion amount and
unit are supplied together and that the amount is positive. The migration does
not add a foreign key to historical `included_unit` labels: those labels are
intentionally frozen commercial facts, while current selections are validated
against `measurement_units` before being stored.

## Verification boundaries

Focused PostgreSQL feature tests cover active-unit resolution, forged or
inactive-unit rejection, used-unit rename/delete protection, safe
deactivation/reactivation, and Staff authorization. Browser tests cover the
collapsed Food Options workflow, inline unit creation, fixed portions, size
choice confirmation, service configuration, and package save/reopen behavior.
The final milestone report records the exact quality-gate counts actually run.
