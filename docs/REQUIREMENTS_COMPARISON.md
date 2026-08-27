# Requirements Comparison — Quetta AgriLink

This reconciliation compares the supplied `pasted_content.txt` specification with the **authoritative PHP 8+/MySQL/MariaDB/XAMPP application**, the importable schema, the active routes, and the completed Git delivery history through commit `162fc78`. The managed React/Vite project is a visual/workflow preview only and is not treated as production implementation evidence.

## Scope and status terms

| Status | Meaning used in this comparison |
|---|---|
| Implemented | A protected PHP route and/or a complete supporting workflow currently provides the requested behavior. |
| Partial | The normalized database foundation or a limited workflow exists, but the requested user-facing or lifecycle capability is incomplete. |
| Remaining | A requested production-facing capability has no authoritative route, editor, or full workflow. |
| Intentionally not seeded | The specification’s sample operational data was deliberately removed at the user’s direction. Documented local development accounts and reference data remain. |

## Implemented foundation

| Requirement area | Status | Evidence in the authoritative application |
|---|---|---|
| Core stack and XAMPP use | Implemented | PHP 8+, PDO, MySQL/MariaDB, Apache/XAMPP structure, HTML/CSS/vanilla JS, and browser-mode Tailwind are documented in `README.md`; no production dependency on Node, Composer, Docker, React, or a frontend build exists. |
| Design, responsive public site, and role workspaces | Implemented | Public home, About, How It Works, Contact, marketplace, storage, transport, and prices routes exist; the shared Orchard Ledger/Quetta Workbench styles provide responsive public and workspace surfaces. |
| Authentication and role gates | Implemented | Registration, login, logout, session safeguards, password hashing/verification, CSRF validation, escaping, role checks, and owner checks are present across PHP routes and AJAX endpoints. |
| Marketplace discovery and listings | Implemented | Farmers can publish, amend, pause, reactivate, sell out, and delete owned listings. Buyers can filter/sort active produce, view listing detail, save/edit/default buying briefs, favourite listings, make offers, and respond to counteroffers. Server-rendered pagination and filter-wide counts are included. |
| Offers and initial order creation | Implemented | Offers retain buyer, farmer, listing, quantity, unit price, total, status, timestamps, and events. Accepted offers create order and order-item records transactionally, deduct availability, write initial order-status history, and create notifications. |
| Storage discovery and booking | Implemented | Facility search/filtering, capacity/compatible-produce validation, farmer booking requests, estimated-cost calculation, provider-only approval/rejection/activation/completion, capacity changes, history, notifications, saved search creation/editing, and pagination are present. |
| Transport discovery and dispatch progression | Implemented | Farmer request creation, provider-scoped visibility, refrigerated-service selection, requested through delivered/cancelled progression, history, audit records, and notifications are present. |
| Notifications and local support | Implemented | Account-scoped in-app notifications support unread counts, filtering, individual/all read updates, optional local bell chime, and notification preferences. The local support desk stores role-routed requests, replies, assignment, status history, and in-app attention without email, SMS, SMTP, or third-party delivery. |
| Market prices and local import | Implemented | Administrator CSV intake validates rows before atomic saving, retains source/batch accountability without retaining the CSV, and serves public source-aware price filtering. |
| Governance, audit, and exports | Implemented | Audit records cover key workflows; protected attachment, recovery, contact-review, listing-activity, dashboard-summary, and administrator dashboard-export-audit CSV flows are implemented. The dashboard-export audit register supports safe filters, export-event date ranges/presets, allowlisted sorting, and pagination. |
| Schema and local documentation | Implemented | `database/quetta_agrilink.sql` provides normalized role/profile, marketplace, storage, transport, message, notification, payment, review, audit, and history tables with keys and indexes; `README.md` and `docs/LOCAL_XAMPP.md` document XAMPP setup, migrations, credentials, security, and troubleshooting. |

## Verified partial or remaining specification work

The following items remain in `remaining-work.md` because the requested user-facing behavior is not yet complete in the authoritative application. They are ordered by business dependency rather than by the order in the source prompt.

| Workstream | Status | Verified gap |
|---|---|---|
| Role-specific business and asset editors | Remaining | Shared contact-profile editing is present, but farmer farm/location, buyer business, storage-provider/facility/supported-produce, and transport-provider/vehicle/service-area creation and editing routes are not yet exposed. |
| Commerce order desk and lifecycle | Partial | An accepted offer creates an order and initial history record, but neither farmers nor buyers have a dedicated account-scoped order/history desk, and no protected order-status progression through the full requested lifecycle is available. |
| Transport commercial assignment details | Partial | Provider-only status progression exists, but the current update endpoint does not persist driver assignment, vehicle assignment, or a provider quote/estimated-price decision. |
| Operational images and document ownership | Partial | A secure administrator attachment workflow exists, but farmers, storage providers, and transport providers cannot yet manage produce, facility, or vehicle media in their own workflows; image resize/compression is also not implemented. |
| Internal listing/order messaging | Remaining | The schema contains `messages` and local support conversations work, but there is no buyer–farmer listing/order message thread or account-scoped messaging interface. |
| Reviews, ratings, and payments | Remaining | The normalized `reviews` and `payments` tables are foundations only; there is no post-transaction review/rating flow or payment-provider workflow. No fictional ratings or reviews should be seeded. |
| Administrator operating controls and analytics | Partial | The administrator has account, attachment, recovery, contact-review, market-price import, support, and export-audit tools, but not complete CRUD and status controls for categories, listings, offers, orders, facilities, vehicles, transport requests, and announcements. The dashboard has factual summary cards but not the requested time-series/category/top-product analytics. |
| Error routes and release hardening | Partial | AJAX failures and empty states are handled, but dedicated branded 403/404/500 routes are absent. Before public launch, the documented release gate still requires an owned support channel, verified operational data, password-reset delivery approach, rate limiting, privacy/legal notices, backups, HTTPS, monitoring, and a payment-provider decision. |

## Intentional deviations from the supplied specification

The source specification called for realistic seeded operational listings, facilities, orders, prices, and other demo records. The user subsequently directed that all fabricated operational data be removed. Accordingly, the schema retains only required reference data and documented demonstration credentials; fresh installations correctly show empty states until verified local records are entered or approved price data is imported.

Likewise, no external email, SMTP, SMS, webhook, or hosted helpdesk integration has been introduced. The current local in-app support implementation follows the user’s explicit local-only support requirement. The `reviews` foundation remains unused until a genuine, transaction-linked review workflow can be implemented without fabricating user-generated content.

## Reconciliation conclusion

The codebase satisfies the core local commercial-MVP foundation: role-based authentication, secured and auditable marketplace offer creation, storage and transport workflows, discovery, notifications, local support, price reference management, responsive UI, and XAMPP deployment. The active register now accurately identifies the remaining operational modules needed to reach the full scope of the original supplied specification.
