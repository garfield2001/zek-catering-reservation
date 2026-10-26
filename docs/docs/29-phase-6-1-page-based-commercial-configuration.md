# Phase 6.1: page-based commercial configuration

## Decision

Commercial configuration is a focused Admin workflow, not a large modal. Creating or editing an Add-on Service, Package Product, Package Draft, individual listing, or bulk individual listings uses a dedicated route with breadcrumbs and an explicit return path. Small, local operations remain dialogs: adding a Food Item while configuring a menu, adding a Measurement Unit, confirmation, and short lifecycle actions.

The existing lifecycle boundaries remain unchanged. A Draft may be changed through its editor page; review, publication, immutable history, cloning, availability and archive actions remain in their established workspaces. No customer transaction behavior is introduced.

## Customer Choice Groups

`package_version_required_categories` now records a category-specific `minimum_count`, `maximum_count`, and `maximum_count_explicit`. The stored rule is part of the Draft, copied to a cloned Draft, and frozen into the published snapshot (schema version 6). Laravel validates the rules and the actual customer selection; client-side limits are only guidance.

Legacy required-category rows are retained as an inherited rule: minimum one and an upper bound of the package selection total, with `maximum_count_explicit = false`. The migration preserves that behavior. A new rule entered through the package editor is explicit and must have enough non-included eligible Food Items for its maximum.

When a configured category has exactly one allowed Food Item, Laravel promotes that item to a fixed inclusion during menu replacement. This implements the business rule that a one-item required category is not a meaningless customer choice. It applies to both legacy and current request payloads, and is revalidated from persisted data.

## Verification boundaries

Feature coverage verifies explicit ranges, minimum and maximum enforcement, publication snapshots, cloning, and automatic single-choice inclusion. Browser coverage exercises the page routes, filtered food selection, package save/reopen and history flows. Run migrations only against the isolated test database for automated verification; the development database is not modified by this milestone.
