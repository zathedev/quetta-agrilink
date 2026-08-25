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
