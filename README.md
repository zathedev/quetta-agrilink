# Quetta AgriLink

> **One platform for everything after harvest.**

Quetta AgriLink is a PHP and MySQL agricultural marketplace designed for Quetta and the wider Balochistan supply chain. It connects farmers with buyers while providing integrated discovery and workflow support for cold-storage and transport providers. The design system, **Orchard Ledger**, treats product grade, origin, quantity, capacity, price, and status as the primary interface elements.

## Product scope

The current codebase is a direct XAMPP-compatible PHP application. It does not require Docker, Composer, Node.js, npm, or a frontend build process. It uses HTML5, CSS3, Tailwind CSS in browser mode, vanilla JavaScript, protected AJAX endpoints, PHP 8+, PDO, and MySQL/MariaDB.

| Area | Current working capability |
|---|---|
| Public experience | Homepage, About, How It Works, Contact, marketplace, cold storage, transport, and market-price pages. |
| Identity | Registration, login, logout, session expiry, role-aware authorization, secure password hashing, and demo accounts. |
| Marketplace | Active listing discovery, server-side filtering, listing detail, buyer favourites, buyer offers, farmer counteroffers, acceptance/rejection, and order creation. |
| Storage | Facility discovery, compatible-produce checks, requested booking creation, estimated storage cost, provider approval/rejection, activation, completion, and status history. |
| Transport | Provider discovery, farmer request creation, provider acceptance/decline, and recorded dispatch progression from driver assignment through delivery. |
| Workspaces | Farmer, buyer, cold-storage provider, transport provider, and administrator dashboards, with account-scoped records. |
| Notifications | In-app notifications created for offers, booking requests, storage status, transport status, and orders. |
| Market intelligence | Administrator-recorded reference price ranges and asynchronous product filtering. |

The database includes the broader normalized foundation for messages, announcements, reviews, payments, audit logs, password-reset tokens, and all specified role/profile relationships. **No review, rating, testimonial, or customer-logo data is seeded or displayed.**

## Requirements

| Requirement | Recommended version |
|---|---:|
| XAMPP | Current stable release with Apache, PHP, and MariaDB/MySQL enabled |
| PHP | 8.1 or newer; the source uses strict types, match expressions, PDO, and password APIs |
| MySQL/MariaDB | MySQL 8+ or MariaDB 10.6+ |
| Browser | Current Chrome, Edge, Firefox, or Safari |

Enable Apache `mod_rewrite` if it is available. The project remains usable without a custom rewrite rule because pages use explicit PHP paths, but `.htaccess` protects the configuration, database, include, and upload directories where Apache permits overrides.

## Local XAMPP installation

1. Install XAMPP and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy or clone this repository into `C:/xampp/htdocs/quetta-agrilink/`.
3. Open `http://localhost/phpmyadmin/` and import `database/quetta_agrilink.sql`. The script creates and selects the `quetta_agrilink` database, tables, indexes, relationships, and fictional demonstration data.
4. Confirm the database settings in `config/config.php`. The default XAMPP values are `127.0.0.1`, port `3306`, database `quetta_agrilink`, username `root`, and a blank password.
5. If your XAMPP MySQL password differs, copy `config/config.example.php` to `config/config.php` and update `DB_USER` and `DB_PASS`. Keep production credentials outside version control.
6. Visit [http://localhost/quetta-agrilink/](http://localhost/quetta-agrilink/).

The `APP_URL` constant must remain `/quetta-agrilink` for this default XAMPP location. Change it if the application is hosted under a different path.

## Demo accounts

All records below are **fictional local demonstration accounts**. They do not belong to real people. Every demo account uses the same password:

```text
AgriLinkDemo2026!
```

| Role | Email | Demonstration purpose |
|---|---|---|
| Farmer | `farmer.demo@quettaagrilink.test` | Listings, offer review, storage bookings, and transport requests. |
| Buyer | `buyer.demo@quettaagrilink.test` | Marketplace browsing, saved listings, and purchase offers. |
| Cold Storage Provider | `storage.demo@quettaagrilink.test` | Capacity, booking review, and storage status changes. |
| Transport Provider | `transport.demo@quettaagrilink.test` | Vehicle-backed requests and transport lifecycle status. |
| Administrator | `admin.demo@quettaagrilink.test` | Cross-platform summary and account visibility. |

## Folder structure

```text
quetta-agrilink/
├── admin/                  # Administrator workspace
├── ajax/                   # Protected JSON endpoints
├── assets/
│   ├── css/                # Orchard Ledger shared interface system
│   └── js/                 # AJAX helper and lightweight pub/sub store
├── auth/                   # Registration, login, logout
├── buyer/                  # Buyer dashboard and offer desk
├── config/                 # Local configuration and example
├── database/               # Importable normalized schema and demo seed data
├── farmer/                 # Farmer dashboard and offer desk
├── includes/               # PDO, authentication, services, and reusable layout
├── marketplace/            # Listing discovery and listing detail
├── storage/                # Storage discovery and provider workspace
├── transport/              # Transport discovery and provider workspace
├── uploads/                # Non-executable user-upload directory
├── index.php               # Public homepage
├── market-prices.php       # Market intelligence page
└── README.md               # This guide
```

## Role model

Each `users` record references exactly one role, while role-specific business information lives in a normalized profile table. This keeps common identity data separate from farms, buyer businesses, cold-storage operations, and transport companies.

| Role | Primary permissions |
|---|---|
| Farmer | Create and manage produce availability, review offers, request cold storage, request transport, and track downstream work. |
| Buyer | Search active produce, save listings, submit offers, respond to counters, and track purchases. |
| Cold Storage Provider | Maintain storage capacity and process only bookings belonging to owned facilities. |
| Transport Provider | View only assigned requests and advance those requests through allowed trip milestones. |
| Administrator | View platform-level indicators and account information. |

## Security implementation

The project keeps critical business and authorization decisions in PHP. The browser may request actions asynchronously, but it does not become a trusted source of role, price, availability, order, booking, or transport state.

| Control | Implementation |
|---|---|
| Password security | `password_hash()` stores passwords; `password_verify()` validates credentials. |
| Database access | PDO uses native prepared statements with emulated prepares disabled. |
| Sessions | Strict session mode, regenerated identifiers after login, HTTP-only cookies, SameSite Lax, and idle expiry. |
| CSRF protection | Every state-changing form and AJAX request includes a session-bound token. |
| Authorization | Protected pages and AJAX routes call role and ownership checks before accessing data. |
| Output safety | Dynamic HTML is emitted through the `e()` escaping helper. |
| Upload safety | The `uploads/` folder disables PHP-family execution through its own `.htaccess`; future upload handlers must retain MIME, size, and image validation. |
| Auditability | Account registration, authentication, offers, booking actions, transport actions, and order actions can write structured audit records. |

## AJAX and state management

The shared `assets/js/app.js` helper sends same-origin requests with `Accept: application/json`, `X-Requested-With: XMLHttpRequest`, and the page CSRF token. Endpoints return a consistent envelope:

```json
{
  "success": true,
  "message": "Storage booking request sent.",
  "data": {}
}
```

`assets/js/store.js` provides a deliberately small publish/subscribe store for transient UI state such as marketplace filters, loading flags, selected listings, and notification counts. The MySQL database and PHP endpoints remain authoritative for all operational states.

## Operational lifecycle rules

| Workflow | Supported progression |
|---|---|
| Offer | Pending → Countered, Accepted, Rejected. An accepted offer creates an order and deducts the relevant listing quantity transactionally. |
| Storage booking | Requested → Approved or Rejected → Active → Completed. Approval reserves available facility capacity; completion returns capacity. |
| Transport request | Requested → Accepted or Cancelled → Driver Assigned → Pickup Scheduled → Picked Up → In Transit → Delivered. |
| Notifications | Created unread; each is owner-scoped and may be marked read using the protected JSON endpoint. |

## Troubleshooting

| Problem | Check |
|---|---|
| Database connection error | Confirm Apache/MySQL are running, import the SQL file, and verify `config/config.php`. |
| Login not working | Confirm the imported database contains the demo users and use the documented password exactly. |
| Session or CSRF error | Ensure `APP_URL` matches the application folder, then clear local browser cookies for localhost and sign in again. |
| 403 error on a workflow | Sign in with the correct account role. Provider actions are scoped to facilities or requests the provider owns. |
| Images are unavailable | The current development visual assets use project-hosted URLs. Confirm the machine has internet access, or replace those URLs with owned local image assets before an offline deployment. |
| `.htaccess` does not apply | Enable Apache `mod_rewrite` and `AllowOverride All` for the htdocs directory if your local Apache configuration disables overrides. |

## Future improvements before a live production launch

This foundation is intended for a demonstrable commercial MVP. A production launch should add a verified public support channel, secure image resize/processing service, password-reset delivery, complete admin CRUD controls, provider facility and vehicle editors, customer-controlled notification preferences, privacy/legal notices, rate limiting, backup strategy, HTTPS enforcement, monitoring, and a payment provider selected for the intended operating market.

Do not replace the fictional demo records with real customer information until consent, data governance, verified support operations, and appropriate production controls are in place.

## GitHub workflow

This repository is configured for a private GitHub remote. Each implementation increment is committed with a focused, readable conventional commit and pushed to the `main` branch. The live project history can be reviewed at [github.com/zathedev/quetta-agrilink](https://github.com/zathedev/quetta-agrilink).
