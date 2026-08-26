# Preview Repair Tasks

- [x] Confirm why the managed preview is serving the untouched React scaffold instead of the XAMPP PHP application. The preview project was still rendering its untouched React starter page.
- [x] Replace the scaffold screen with a preview-compatible Quetta AgriLink interface that reflects the implemented product workflows.
- [x] Capture desktop and mobile screenshots to verify the visible preview. Both show the complete Orchard Ledger landing interface, with a compact mobile layout at 403 px.
- [x] Commit and push the preview repair with a readable GitHub commit message: `fix: render Quetta AgriLink in managed preview`.

## Multi-Page Preview Expansion

- [x] Add visible routes for the marketplace, cold storage, transport, market prices, and public information pages.
- [x] Add role-specific workspace routes for farmer, buyer, storage provider, transport provider, and administrator views.
- [x] Connect header, footer, calls to action, and workspace navigation to the managed preview routes.
- [x] Verify desktop and mobile route rendering. Marketplace, storage, transport, market prices, and farmer workspace are distinct pages; the commodity ledger and compact workspace remained legible at 403 px.
- [x] Verify the desktop and mobile routed experience, then commit and push the expansion: `feat: add routed Quetta AgriLink preview`.

## Operational Preview Expansion

- [x] Add listing-detail pages with produce context, offer composition, and confirmation feedback.
- [x] Add interactive storage and transport request feedback within the managed preview.
- [x] Expand the administrator workspace into management tables for marketplace listings, storage capacity, fleet, and market-price records.
- [x] Verify desktop and mobile operational flows. Listing offer composition, storage request preparation, and administrator registers are distinct and legible at 403 px.
- [x] Verify desktop and mobile operational flows, then commit and push the extension: `feat: add operational marketplace workflows`.

## Persisted Operational Experience

- [x] Add a sign-in route with role-based workspace entry aligned to the production authentication model.
- [x] Persist submitted offers, storage requests, and transport requests in browser storage and surface them in the relevant workspace.
- [x] Add administrator create and edit forms for marketplace, storage, fleet, and price records.
- [x] Verify desktop and mobile interaction surfaces. Account entry, trade-ticket offer terms, browser-persisted record registers, and administrator forms are responsive and compile successfully.
- [x] Commit and push the production-alignment extension: `feat: persist operational workspace records`.

## Integration-Ready Workflow Layer

- [x] Map the managed routes and field names to the existing PHP/MySQL action contract.
- [x] Add validated image-upload and document-upload surfaces to administrator record forms.
- [x] Add account-scoped notification surfaces for offers, storage bookings, and transport requests.
- [x] Verify integration-ready interaction surfaces and XAMPP handoff documentation. Notification registers, administrator records, and compact mobile dormant states are legible; production builds pass.
- [x] Commit and push the integration-ready extension: `feat: add integration-ready workflow surfaces`.

## XAMPP Deployment Readiness

- [x] Confirm the XAMPP document-root placement, database import, and local application URL configuration.
- [x] Add a server-side, role-protected upload handler with MIME, size, extension, and generated-name validation.
- [x] Add a migration for attachment metadata and wire secure administrator record attachments.
- [x] Document localhost/XAMPP setup, session, CSRF, database, and upload maintenance settings.
- [x] Lint and test the PHP/MySQL package locally. Administrator authentication and a permitted PNG attachment were validated against MariaDB; the local test record was removed afterward.
- [x] Commit and push the XAMPP-readiness extension: `feat: add local XAMPP attachment workflow`.

## Local XAMPP Error Repair

- [x] Make the administrator attachment register degrade safely with an actionable migration notice when its table is missing.
- [x] Correct the missing marketplace `sort` parameter fallback.
- [x] Validate both paths against the local PHP/MySQL test environment. The administrator register renders a migration notice without the table, then resumes normally after restoration; an empty marketplace filter resolves to `recent`.
- [x] Commit and push the local XAMPP error repair: `fix: handle local XAMPP migration state`.

## Attachment and Marketplace Discovery Improvements

- [x] Add attachment-name search and inclusive date-range filters to the administrator attachment register.
- [x] Add a clear category filter and asynchronous loading feedback to the local PHP marketplace.
- [x] Validate the filtering and loading interactions. The local attachment register returns filtered results safely, and the category/sort endpoint returns successful JSON markup.
- [x] Commit and push the feature update: `feat: add attachment and marketplace filters`.

## Attachment Accountability and Saved Discovery

- [x] Add a protected attachment download endpoint that records a user, timestamp, and attachment-specific audit entry before serving the permitted file.
- [x] Add authenticated saved marketplace filters, including a migration, management controls, and a reusable filter application flow.
- [x] Validate local XAMPP download auditing and saved-filter create/apply/delete workflows. The permitted attachment response was downloaded byte-for-byte after an audit write; anonymous access redirected to sign-in; a saved filter was created, applied, and removed through account-scoped PHP requests.
- [x] Commit and push the completed enhancement: `feat: audit attachment downloads and save filters`.

## Preview Route Integrity

- [x] Repair the managed-preview `/services` route so it presents storage and transport as accountable Orchard Ledger trade records rather than a fallback screen.
- [x] Apply the accepted visual review refinements to services copy, cross-route styling, and design decisions; verify the preview route renders and the TypeScript build passes.

## Accountable Discovery Follow-through

- [x] Add a role-protected attachment download-audit CSV export for administrators.
- [x] Add a default saved marketplace filter per user, including a migration and an explicit apply/reset flow.
- [x] Notify a filter owner in the account notification register when a newly published listing matches their default marketplace criteria.
- [x] Add an optional, accessible browser bell chime for newly received in-app notifications without requiring an external audio service.
- [x] Validate the PHP/MySQL workflows locally and document required migration steps. The default filter was saved and applied; a matching farmer publication generated a buyer in-app alert; the unread summary and CSV response succeeded; temporary records were removed afterward.
- [x] Commit and push the completed enhancement to GitHub: `feat: add default listing alerts`.

## Account Management Controls

- [x] Add owner-scoped notification read controls for individual alerts and the full alert register.
- [x] Add owner-scoped editing for saved marketplace filters, including default-filter continuity.
- [x] Add farmer-only listing status controls for pausing, reactivating, and marking produce sold out.
- [x] Validate the local PHP/MySQL workflows. A default saved filter was edited while retaining default state; an associated new listing generated an alert; individual and all-alert read controls completed; and lifecycle transitions reached paused, active, then sold out. Temporary records were removed afterward.
- [x] Commit and push the completed account-management update: `feat: add account management controls`.

## Listing and Alert Operations

- [x] Add farmer-owned available-quantity amendments with validation and activity logging.
- [x] Add account-scoped notification filtering by alert type and read state.
- [x] Add protected downloadable CSV activity history for an individual farmer listing.
- [x] Validate local PHP/MySQL workflows and document the new exports. An owned listing quantity was amended and export contained its activity event; non-farmer export access returned 403; filtered read and unread matching alerts remained account-scoped; temporary validation data was removed afterward.
- [x] Commit and push the completed operational update: `feat: add listing activity operations`.

## Whole-Application UX Redesign

- [x] Audit the current public, authentication, marketplace, dashboard, and role-workspace journeys for clarity, hierarchy, and ordinary-user usability issues.
- [x] Refine the established Orchard Ledger direction into a calmer, task-led information architecture while retaining its existing color philosophy.
- [x] Redesign the public, marketplace, sign-in, and sign-up experiences across the PHP application and managed preview.
- [x] Redesign dashboards and role workspaces around clear next actions, progress, and understandable operational records.
- [x] Validate desktop and mobile redesigned journeys. The local PHP home, sign-in, registration, and authenticated farmer dashboard served the redesigned structures; all PHP/JS checks and preview builds passed; mobile marketplace listings were converted from a horizontal ledger into stacked summaries.
- [x] Commit and push the completed UX redesign to GitHub: `feat: redesign application experience`.

## Recovery and First-Use Guidance

- [x] Add a privacy-preserving password recovery request and administrator-issued, one-time local reset flow suitable for an XAMPP deployment without external email.
- [x] Add first-time role-specific onboarding that explains the initial operational path without blocking access to the workspace.
- [x] Add role-specific dashboard shortcuts for the most frequent account actions.
- [x] Validate local PHP/MySQL recovery, onboarding, and shortcut workflows. A generic recovery request created a protected admin record; an administrator issued a one-time link; the password reset completed and the same link was rejected on reuse; onboarding completion was account-scoped and hid the guide; temporary state was restored afterward.
- [x] Commit and push the completed follow-through update: `feat: add local recovery guidance`.

## Profile and Accountability Follow-through

- [x] Add account-scoped profile editing with email and contact uniqueness checks plus an audit trail.
- [x] Add administrator-only recovery-verification notes that stay attached to the recovery request without storing reset links or passwords.
- [x] Add concise role-specific dashboard activity summaries that direct each account to the most recent relevant record.
- [x] Validate the local PHP/MySQL workflows. A farmer updated only their own profile; a duplicate account email was rejected; the activity summary reflected the update; a reset link could not be issued before an administrator recorded an offline verification note; and temporary validation data was restored afterward.
- [x] Commit and push the completed account and accountability update: `feat: add profile accountability controls`.

## Contact and Operational Audit Follow-through

- [x] Add an administrator-recorded account contact-verification status with accountable notes and timestamps, without claiming automatic email or phone verification.
- [x] Add a protected administrator CSV export for password-recovery request and verification audit history.
- [x] Add account-scoped date-range filtering for role-specific dashboard activity summaries.
- [x] Validate local PHP/MySQL workflows and document the migration. An administrator recorded email/phone review; an altered phone cleared only its phone-review state; the farmer saw only its date-matched activity; recovery CSV download excluded selectors, token hashes, and password hashes; temporary records were removed afterward.
- [x] Commit and push the completed accountability update: `feat: add contact audit controls`.

## Review Reason and Time-Range Follow-through

- [x] Add a structured administrator contact-review reason catalog with a constrained explanation field and preserved audit trail.
- [x] Add protected date-range filtering to the administrator recovery-audit register and CSV export.
- [x] Add account-scoped saved dashboard activity date presets for common operational time windows.
- [x] Validate local PHP/MySQL workflows, document any migration, then commit and push the completed follow-through update.

## Accountability Operations Follow-through

- [x] Add administrator search and status filtering to the local contact-review register.
- [x] Add account-scoped notification delivery preferences with a documented local-only default.
- [x] Add a protected printable recovery handover record that excludes passwords, reset links, tokens, and hashes.
- [x] Validate PHP/MySQL workflows and desktop/mobile preview alignment, then commit and push the completed follow-through update.

## Accountability Discovery and Mobile Access Follow-through

- [x] Add an administrator-only contact-review CSV export that excludes sensitive recovery or credential fields.
- [x] Add administrator recovery-register filters for account role and regional location that compose safely with existing date filters and CSV export.
- [x] Refine compact mobile workspace navigation into an accessible task menu without squeezing the desktop sidebar into narrow screens.
- [x] Validate PHP/MySQL exports and filters, then check desktop/mobile preview alignment before committing and pushing the completed follow-through update.
