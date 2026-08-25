# Quetta AgriLink UI-to-PHP Workflow Contract

The managed interface mirrors the existing XAMPP application’s action names, role boundaries, request fields, and notification lifecycle. The browser record layer is a UI continuity mechanism; production writes remain the responsibility of the PHP endpoints and MySQL transaction logic.

| UI action | PHP endpoint | Required account role | Contract fields | Success state | Recipient notification |
|---|---|---|---|---|---|
| Submit a buyer offer | `ajax/offers/create.php` | Buyer | `listing_id`, `quantity`, `offered_price`, `message` | Sent to farmer | Farmer receives **New buyer offer** |
| Request cold storage | `ajax/storage/book.php` | Farmer | `facility_id`, `listing_id`, `category_id`, `quantity_kg`, `start_date`, `end_date` | Requested | Storage provider receives **New storage booking request** |
| Request transport | `ajax/transport/request.php` | Farmer | `provider_id`, `listing_id`, `pickup_location_id`, `delivery_location_id`, `produce_description`, `quantity_kg`, `requires_refrigeration`, `pickup_date` | Requested | Transport provider receives **New transport request** |

The XAMPP deployment should expose the public PHP pages and authenticated AJAX endpoints from the same origin. It must retain session cookies and CSRF protection, reject cross-role submissions, validate available quantity and capacity in the database transaction, and use `uploads/` only through the server-side file-validation rules. The managed static preview has no PHP runtime, so it displays the same role and record states using browser-local continuity data.

## Upload Handoff

Administrator forms now collect an attachment name alongside each operational record. In XAMPP deployment, wire this field to the existing protected `uploads/` directory with a server-side multipart handler that validates MIME type, file size, image dimensions, generated filename, and record ownership before persisting metadata. Do not trust the filename or MIME type supplied by the browser.
