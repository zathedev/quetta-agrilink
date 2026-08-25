# Quetta AgriLink — Implementation Architecture

## Purpose and technical boundary

Quetta AgriLink is specified as a deployable **PHP 8+, MySQL/MariaDB, Apache, HTML5, Tailwind CSS, and vanilla JavaScript** application. The product package will remain directly installable in `C:/xampp/htdocs/quetta-agrilink/`, with no Docker, Composer, Node.js, or frontend build step needed for normal local use.

The application follows a server-authoritative model: PHP owns authentication, authorization, validation, pricing, workflows, and persistence. Browser JavaScript improves responsiveness through small, protected JSON requests; it does not become an alternate source of truth.

## Module boundaries

| Module | Responsibility | Primary users |
|---|---|---|
| Public site | Explain the product, surface available services, and lead users to marketplace and registration paths. | All visitors |
| Identity and access | Registration, login, session hardening, password lifecycle, role checks, CSRF validation, and protected-route enforcement. | All roles |
| Marketplace | Listing discovery, filters, detail pages, favourites, offers, orders, and negotiation. | Farmers and buyers |
| Storage | Facility discovery, availability, booking calculations, approvals, occupancy, and provider revenue views. | Farmers and storage providers |
| Transport | Provider discovery, vehicle capability, trip requests, lifecycle status, and earnings views. | Farmers and transport providers |
| Messaging and notifications | Contextual buyer–farmer messages and in-app operational alerts. | All authenticated roles |
| Market intelligence | Admin-entered daily produce prices, price history, range calculations, and browser-side filtering. | All users; editing restricted to admins |
| Administration | Operational management, moderation, announcements, analytics, audit visibility, and taxonomy management. | Admins only |

## XAMPP-ready folder map

```text
quetta-agrilink/
├── admin/                 # Admin-only management pages
├── ajax/                  # Protected JSON endpoints grouped by operation
├── api/                   # Read-focused public/authorized JSON resources
├── assets/
│   ├── css/               # Tailwind output/custom system CSS
│   ├── js/                # AJAX helper, client store, and page scripts
│   └── images/            # Small product-owned visual assets
├── auth/                  # Registration, login, logout, password actions
├── buyer/                 # Buyer dashboard and purchase operations
├── config/                # Configuration example and database bootstrap
├── database/              # Importable SQL schema and demo seed records
├── farmer/                # Farmer dashboard, listings, farm profile
├── includes/              # Database access, middleware, components, helpers
├── marketplace/           # Public marketplace pages and listing details
├── storage/               # Storage browsing, provider area, booking workflow
├── transport/             # Transport browsing, provider area, request workflow
├── uploads/               # Non-executable user-uploaded files
├── index.php              # Public homepage
├── README.md              # XAMPP installation and project guide
└── .htaccess              # Safe directory access and upload restrictions
```

## Core data model

The SQL schema is normalized around a single `users` identity with role-specific profile extensions. All operational records use foreign keys and auditable lifecycle history where status changes are material.

| Domain | Primary tables | Design decision |
|---|---|---|
| Identity | `roles`, `users`, `farmer_profiles`, `buyer_profiles`, `storage_providers`, `transport_providers` | A user has one platform role for a clear authorization model; specialized data remains out of the base identity table. |
| Geography | `locations` | Province, district, tehsil, area, latitude, and longitude support Quetta first and expansion across Balochistan later. |
| Produce | `produce_categories`, `produce_listings`, `produce_images` | Listings record grade, harvest/availability dates, unit, quantities, pricing, MOQ, and an explicit active/paused status. |
| Trading | `favorites`, `offers`, `offer_events`, `orders`, `order_items`, `order_status_history` | Negotiation history and every order transition are retained; order status is not inferred from informal notes. |
| Storage | `storage_facilities`, `facility_supported_products`, `storage_bookings`, `storage_booking_status_history` | Availability calculations derive from active bookings and capacity, with a denormalized availability value updated transactionally only where justified. |
| Transport | `vehicles`, `transport_service_areas`, `transport_requests`, `transport_status_history` | Vehicles belong to providers and retain refrigerated capacity and service areas independently of individual trip requests. |
| Communications | `messages`, `notifications`, `announcements` | Context IDs link messages and alerts to listings, offers, orders, bookings, or transport requests. |
| Intelligence and governance | `market_prices`, `reviews`, `payments`, `audit_logs` | Reviews are structurally supported but no fabricated review, rating, testimonial, or rating aggregate data will be seeded or displayed. |

## Workflow rules

| Workflow | Allowed progression | Integrity rule |
|---|---|---|
| Offer | Pending → Accepted, Rejected, Countered, Withdrawn, Expired | Acceptance validates listing availability and creates a traceable downstream order path. |
| Order | Pending → Confirmed → Storage Required / Transport Required → Ready for Pickup → Picked Up → In Transit → Delivered → Completed; Cancelled is explicitly recorded | Every mutation inserts `order_status_history` within the same database transaction. |
| Storage booking | Requested → Approved or Rejected → Active → Completed; Cancelled is explicit | Approval checks the facility’s compatible products, remaining capacity, dates, and provider ownership. |
| Transport request | Requested → Accepted → Driver Assigned → Pickup Scheduled → Picked Up → In Transit → Delivered; Cancelled is explicit | Each transition is restricted by provider ownership and captured in history. |
| Notifications | Created unread → Marked read | Read actions are owner-scoped and returned as JSON without a full page reload. |

## Security controls

The initial application foundation will centralize these requirements in reusable PHP helpers and middleware rather than scattering security-sensitive logic across pages.

| Control | Implementation policy |
|---|---|
| Database access | PDO with exceptions, emulation disabled, prepared statements only, and explicit transactions for coupled workflow updates. |
| Passwords | `password_hash()` on registration and `password_verify()` at login; password hashes are never returned or logged. |
| Sessions | Strict mode, regenerated identifiers at authentication, secure cookie flags when HTTPS is available, idle timeout, and server-side role checks. |
| Request validation | CSRF token required for state-changing form and JSON actions; all input is type, length, ownership, and business-rule validated server-side. |
| Output safety | Escape all dynamic HTML with a single output helper; JSON responses use canonical encoding and correct HTTP status codes. |
| File uploads | Randomized file names, MIME and extension allow-list, size limits, image validation and resizing, storage outside executable paths, and no PHP execution in uploads. |
| Authorization | Every protected page and AJAX endpoint calls role/ownership middleware before access. |
| Auditability | Admin-sensitive actions and material workflow changes insert structured audit records without storing passwords or sensitive raw payloads. |

## Browser architecture

The front end will use Tailwind CSS supplied without an ongoing build requirement, a compact shared `assets/js/app.js` utility set, and a deliberately small pub/sub `assets/js/store.js` for ephemeral interface state. The store may hold active filters, pagination, selected records, UI loading state, and notification count; it never holds the authoritative status of orders, sessions, bookings, permissions, or payments.

AJAX endpoints will accept and return JSON using a consistent envelope:

```json
{
  "success": true,
  "message": "Offer submitted successfully.",
  "data": {}
}
```

Each request includes the CSRF token where state changes and renders loading, success, error, and empty states instead of simulating success locally.

## Release sequence

The first working release is implemented in dependencies-first order. This keeps the database and authorization rules stable before role workflows rely on them.

1. Establish source structure, configuration example, reusable PHP infrastructure, Tailwind design system, public shell, and SQL schema.
2. Implement secure registration, login, logout, role middleware, profile initialization, and demo account setup.
3. Implement produce categories, farmer listings, public marketplace discovery, filters, listing detail, favourites, offers, and order creation.
4. Implement storage facility management and booking workflows, then transport vehicles, requests, and lifecycle handling.
5. Implement notifications, contextual messages, market prices, dashboards, and administrative management/analytics.
6. Validate end-to-end workflows, local XAMPP import/setup, mobile behavior, protected endpoints, error/empty states, README instructions, and demo credentials.

## Completion criteria

The application is ready to present only when a fresh XAMPP user can import `database/quetta_agrilink.sql`, set their database credentials using the provided example configuration, sign in with documented non-personal demo accounts, and complete the essential user journeys without placeholder-only actions. Each completed milestone is committed and pushed to GitHub with a focused conventional commit message.
