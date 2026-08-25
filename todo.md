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
