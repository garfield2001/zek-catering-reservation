# Admin and Staff owner walkthrough

Current authority: [Milestone B commercial catalog](18-milestone-b-commercial-catalog.md). This guide describes the implemented controls; consult its verification status before owner testing. Older Variant, package-floor and separate Menu/Pricing save instructions are superseded.

## Before testing

Automated browser fixtures erase only `zek_catering_test` on `127.0.0.1:5432`. Never run browser and PHP database tests concurrently.
Read-only inspection confirmed that the owner has reset development and its 12 baseline migrations are current. The earlier missing-column warning is historical. Milestone B adds forward migrations 000012–000014; these still need owner authorization before applying to development. No development records or accounts were changed.

## Necessary Main Catering setup

1. **Food Catalog → Add Food Item**: enter a name and select an existing category. **Create new category…** is optional and saves category and food together. Enable the food for Catering Packages. Rice and Drinks are examples, never system-mandated categories.
2. **Catering Packages → Create Package → Details**: enter Package Name. Description is optional. Next opens Menu without creating any database records.
3. **Menu**: choose the exact distinct-food total and allow foods by category. For an illustrative five-food package, allow Fried Rice and require its category; allow two drinks and require theirs; allow other foods to fill remaining choices. A sole allowed food in a required category is automatically fixed. **Always Include** optionally fixes any allowed food, even with other choices in that category. Fixed foods count only once toward the exact total. Select All/Clear All never changes required-category or fixed intent.
4. **Pricing & Quantity**: enter price per pax, minimum guests and quantity increment. Maximum is optional. Copy quantity settings is optional and copies independent values, not an ongoing relationship.
5. **Review & Save → Save Package**: The system validates and saves all steps in one transaction. A failed save leaves no partial package. Saving is not publication.
6. **Review & Publish**: review the saved terms, acknowledge the review, choose Publish Package and Confirm Publication. The immutable Published Version is now the current publication, subject to live catalog availability.

Optional: Add Food within any Menu category preselects that category. The same dialog supports a new category without nesting dialogs. Closing the food dialog preserves unsaved package input. Previous/direct step navigation also retains input. Leaving the editor warns about unsaved changes; there is no autosave.

## Details, revisions and availability

**View Details** is read-only and defaults to the current Published Version. Authorized editors can separately choose **Preview Draft Changes**. These views never merge draft and published facts. Foods are grouped by their saved categories; quantities, pricing, fixed intent and required choices are shown.

**Edit** reuses the same editor with direct step navigation. Editing a published package creates its new Draft only when Save Package succeeds. Publication History can clone an older publication into a higher-numbered Draft; it never moves the current pointer backward. Disable is reversible; Archive is permanent and retains history.

## Food images (optional)

Upload JPEG, PNG or WebP when creating or editing a Food Item. Maximum upload is 5 MB and 3000 × 3000 pixels. The local preview is not a saved image. Saving validates decoded content, strips metadata by re-encoding to JPEG, and creates a bounded full image and thumbnail. Replace or Remove saved image from the same form. Click a saved thumbnail to enlarge; Escape or Close dismisses the accessible viewer. Missing images show name-derived initials.

Images use a private Laravel disk with a controlled read route. Only active, eligible catalog foods are readable without catalog authorization. Images are current presentation assets, not immutable commercial facts. Individual listings inherit their food image; package-specific image uploads are not implemented.

## Add-on Services

Create a dynamically named service and choose one of two selling methods:

- **Sold by Package**: use the shared Details → Menu → Pricing & Quantity → Review & Save editor. Purchase minimums may be one, fifty or another valid quantity. Optional food measurements describe each purchased package, not distinct-food counts or estimated people served. Configure an entirely fixed menu using Always Include for every allowed food and the matching total.
- **Sold Individually**: prepare eligible Food Items, then **Create Item**. Set Item Name, selling unit, independent price and purchase minimum/increment; description, maximum and Serving Option are optional. Each size is a separate listing, not a Variant. Saved food/size identity cannot be changed to another source; create another listing instead.

For packed meals, open **Packed meal settings (optional)** within package Pricing, enable per-pack selling, then choose uniform or split selections. Split groups must each satisfy the menu and their quantities sum to the total. Purchase rules apply to the total. Existing legacy packed-meal and fixed-bundle services retain their stricter contracts; their behavior codes are not renamed or inferred from display names.

Individual items use **Save Item Draft → Publication Review → Publish Item → Confirm Publication**. View Details defaults to the current publication. Preview Draft Changes, history cloning, disabling and archiving remain distinct authorized actions. Published price and size facts remain frozen when master foods/options are edited. Current active/eligibility rules still govern availability.

## Staff and current limitations

Catalog editing, service management, package/listing editing, pricing, publishing, history and lifecycle permissions are separate predefined capabilities. Staff needs service viewing access to navigate Add-on Services plus the applicable listing permissions. No Staff publishing grants are added by default. Backend Policies reject forged direct requests.

The last fully authorized Administrator remains protected. Development account ID 2's intentional Staff/dashboard Deny configuration is untouched.

Customer inquiries, reservations, quotations, checkout, payments, notifications, inventory and scheduling remain deferred. This is Admin/Staff catalog milestone testing, not commercial-production or customer acceptance. Owner acceptance, additional assistive-technology/device checks and deployment hardening remain separate.
