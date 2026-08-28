# Requirements Comparison — Quetta AgriLink

Audit date: 28 August 2026. The authoritative application is the PHP 8+/MariaDB/XAMPP implementation.

| Requirement area | Result | Implementation evidence |
|---|---|---|
| XAMPP-only stack | Complete | Native PHP, PDO, HTML, local CSS, vanilla JavaScript and JSON AJAX; no runtime CDN or build process is required. |
| Five roles and authorization | Complete | Role-specific dashboards, protected routes, ownership checks, CSRF, secure sessions and prepared statements. |
| Farmer operations | Complete | Farm/profile editor, full listing create/edit/pause/delete, secure images, offers/counters, orders, storage, transport, sales/transaction history and notifications. |
| Buyer operations | Complete | Business profile, marketplace filters/pagination, favourites, offers/counters, orders/history, commercial messages, reviews and notifications. |
| Storage operations | Complete | Facility editor, supported produce, capacity/pricing/availability, secure images, public detail page, booking estimates and protected lifecycle/history. |
| Transport operations | Complete | Provider/service-area/vehicle editors, vehicle media, discovery filters, requests, provider estimates, eligible vehicle/driver assignment and dispatch history. |
| Orders and negotiation | Complete | Transactional order creation from accepted offers, quantity deduction, counteroffers, role-scoped order desk and permanent status history. |
| Messaging and notifications | Complete | Database-backed buyer–farmer listing/order threads, new-message notifications, unread counts and AJAX read actions. |
| Reviews and transactions | Complete for local MVP | Only completed-order participants can review. Manual payment instructions and farmer-confirmed receipt are recorded; no uncontracted gateway is simulated. |
| Administrator operations | Complete for local MVP | User/category/listing/facility/vehicle status controls, workflow oversight, orders, announcements, market-data imports, factual role/product/order/value/capacity analytics and audit registers. |
| Database/import | Complete | `database/quetta_agrilink.sql` contains 45 normalized tables, foreign keys, indexes, all formerly separate feature tables, and clearly labelled fictional demo data. |
| Errors/performance | Complete | Branded 403/404/500 pages, centralized exception handling, pagination, indexed queries, lazy images, bounded uploads and project-local assets. |

## Deliberate production boundaries

The application does not pretend to send external support/reset messages or move money. Organization ownership, payment-provider contracting, HTTPS, rate limiting, backups, monitoring, privacy/legal approval and verified live data remain release gates in `remaining-work.md`.

## Removed or superseded excess

The unreferenced React/Vite/Express preview, its package/build configuration, and its obsolete preview-only notes were removed. The repository now contains one authoritative application: the PHP/MySQL/XAMPP implementation.
