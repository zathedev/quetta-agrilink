# Local XAMPP Runbook

Quetta AgriLink’s deployable application is the PHP/MySQL package in this repository. The React managed preview is a design and workflow reference only; do **not** copy its `client/` bundle into the XAMPP document root as a replacement for the PHP app.

## Local installation

| Step | Action | Expected result |
|---|---|---|
| 1 | Copy this repository folder to `C:\xampp\htdocs\quetta-agrilink` on Windows, or the equivalent `htdocs/quetta-agrilink` directory on your XAMPP installation. | Apache can serve `http://localhost/quetta-agrilink/`. |
| 2 | Start **Apache** and **MySQL** from the XAMPP Control Panel. | Both services show as running. |
| 3 | Import `database/quetta_agrilink.sql` with phpMyAdmin or the MySQL command line. | The `quetta_agrilink` database and initial development records exist. |
| 4 | Run `database/migrations/20260825_add_record_attachments.sql` once. | The `record_attachments` table exists. |
| 5 | Run `database/migrations/20260825_add_saved_marketplace_filters.sql` once. | The `saved_marketplace_filters` table exists. |
| 6 | Run `database/migrations/20260825_add_default_saved_marketplace_filters.sql` once. | Each account can mark one saved filter as its default alert criteria. |
| 7 | Copy `config/config.example.php` to `config/config.php` if the local file is absent; set `APP_URL` to `/quetta-agrilink` and use your local MySQL credentials. | PHP loads the expected database and route base path. |
| 8 | Visit `http://localhost/quetta-agrilink/`. | The PHP home page renders. |

## Local security and maintenance

The local application keeps the session cookie, CSRF checks, authentication checks, and AJAX endpoints on the same `localhost` origin. Keep `APP_URL` aligned with the exact directory under `htdocs`; changing the directory requires changing this value too.

Attachments are stored beneath `uploads/attachments/YYYY/MM/`. The server accepts only JPG, PNG, WEBP, and PDF files up to 5 MB, determines the MIME type from file content, generates an opaque random filename, blocks executable file extensions in `uploads/.htaccess`, and logs the uploader and record reference in MySQL. Ensure the Apache process can create and write to `uploads/attachments/`, but do not make the directory world-writable.

Before attaching files, open XAMPP’s `php/php.ini` and set `upload_max_filesize = 6M` and `post_max_size = 8M` or higher, then restart **Apache**. PHP rejects oversize bodies before the application can inspect them, so its limits must be larger than the application’s 5 MB policy.

> For a local XAMPP install, `http://localhost` is sufficient for development. HTTPS is needed only when placing this same PHP application behind a local certificate or a later public reverse proxy. The managed preview cannot execute the PHP runtime; local XAMPP remains the authoritative application runtime.

## Validation checklist

| Check | Expected result |
|---|---|
| PHP syntax check | Every `.php` file passes `php -l`. |
| Database import | Core demo tables load without errors, followed by the attachment and saved-marketplace-filter migrations. |
| Authentication | A documented development account signs in and reaches its role-specific dashboard. |
| Offer / storage / transport | Each request returns a validated response and creates an account-scoped record. |
| Attachment | An administrator can attach and download a permitted test file; each download is integrity-checked and appears in the download audit history. |
| Saved marketplace filter | A signed-in user can save, apply, and remove only their own marketplace criteria. |
| Default listing alerts | A signed-in user can choose one default filter; a farmer publication that matches its criteria creates an in-app alert. Users can enable the optional header bell after a browser interaction. |
| Listing operations | A farmer can amend an owned record’s available quantity above its minimum order, manage its lifecycle, and export its own listing activity history as CSV. |
| Notification register | A signed-in user can filter only their own alerts by type or read state, then mark an individual alert or the full unread register as read. |
