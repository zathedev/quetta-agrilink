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
| 7 | Run `database/migrations/20260826_add_local_password_recovery.sql` once. | The administrator-issued local recovery register is available. |
| 8 | Run `database/migrations/20260826_add_recovery_verification_notes.sql` once. | Administrators must record offline identity verification before issuing a reset link. |
| 9 | Run `database/migrations/20260826_add_user_onboarding_state.sql` once. | First-use workspace guidance can be completed per account. |
| 10 | Run `database/migrations/20260826_add_account_contact_verifications.sql` once. | Administrators can record local email/phone contact review without claiming automatic email/SMS verification. |
| 11 | Run `database/migrations/20260826_add_contact_review_reason_codes.sql` once. | The administrator contact register requires a controlled local-evidence reason before saving a review. |
| 12 | Run `database/migrations/20260826_add_dashboard_activity_presets.sql` once. | Each signed-in account can save and reuse only its own dashboard activity date ranges. |
| 13 | Run `database/migrations/20260826_add_user_notification_preferences.sql` once. | Each signed-in account can set local marketplace-match and optional browser-chime preferences. |
| 14 | Copy `config/config.example.php` to `config/config.php` if the local file is absent; set `APP_URL` to `/quetta-agrilink` and use your local MySQL credentials. | PHP loads the expected database and route base path. |
| 15 | Visit `http://localhost/quetta-agrilink/`. | The PHP home page renders. |

## Local security and maintenance

The local application keeps the session cookie, CSRF checks, authentication checks, and AJAX endpoints on the same `localhost` origin. Keep `APP_URL` aligned with the exact directory under `htdocs`; changing the directory requires changing this value too.

Attachments are stored beneath `uploads/attachments/YYYY/MM/`. The server accepts only JPG, PNG, WEBP, and PDF files up to 5 MB, determines the MIME type from file content, generates an opaque random filename, blocks executable file extensions in `uploads/.htaccess`, and logs the uploader and record reference in MySQL. Ensure the Apache process can create and write to `uploads/attachments/`, but do not make the directory world-writable.

Before attaching files, open XAMPP’s `php/php.ini` and set `upload_max_filesize = 6M` and `post_max_size = 8M` or higher, then restart **Apache**. PHP rejects oversize bodies before the application can inspect them, so its limits must be larger than the application’s 5 MB policy.

> For a local XAMPP install, `http://localhost` is sufficient for development. HTTPS is needed only when placing this same PHP application behind a local certificate or a later public reverse proxy. The managed preview cannot execute the PHP runtime; local XAMPP remains the authoritative application runtime.

### Local password recovery

Password recovery deliberately stays offline for the local XAMPP deployment. A user opens **Sign in → Need to reset your password?** and receives the same confirmation message whether or not an account exists. An authorized administrator verifies the requester through the organisation’s approved local process, records a short verification note, then opens **Workspace → Password recovery** to issue a one-time link. The note must never contain a password, reset link, or token. The link expires after 60 minutes, can be revoked before use, stores only a token hash in MySQL, and must never be copied into unverified channels.

After recording an offline verification note, an administrator may open **Print handover record** beside the recovery request. This printable document captures the account, verified contact information, review context, lifecycle status, and staff accountability. It deliberately excludes passwords, reset links, selectors, tokens, token hashes, and password hashes; any active reset link must remain outside the printed record and use the approved verified local process.

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
| Local password recovery | A public request returns a generic confirmation; an administrator issues a one-time link after verification; the reset link changes the password exactly once and rejects repeat use. |
| First-use guidance | A signed-in account sees non-blocking role guidance and its common task shortcuts on the dashboard; completing the guide hides it only for that account. |
| Contact review and recovery export | An administrator records an offline email/phone review without saving credentials; changed contact details clear only their corresponding review state; the recovery CSV excludes selectors, tokens, hashes, and passwords. |
| Dashboard activity dates | A signed-in role applies an activity date range and sees only its own matching audit entries. |
| Structured reviews and recovery dates | A contact review requires a controlled local-evidence reason plus safe context; recovery request dates constrain both the protected register and its CSV export. |
| Saved activity ranges | A signed-in role saves, applies, and removes only its own dashboard activity date ranges; another account’s preset is never selectable or deletable. |
| Contact-review discovery and export | An administrator searches active accounts, filters the local contact register by outstanding state or approved review evidence, and exports the matching accountability rows. The CSV omits free-text evidence notes, recovery data, credentials, reset links, selectors, tokens, and hashes. |
| Local notification preferences | A signed-in account enables or disables default-filter marketplace alerts and the optional browser chime; no email or SMS delivery is added. |
| Printable recovery handover | An administrator prints a verified recovery record with account and lifecycle context; the output contains no password, reset link, selector, token, or hash. |
| Recovery discovery filters | An administrator filters the local recovery register and protected CSV by received date, account role, and profile district. A filter never exposes recovery selectors, tokens, hashes, reset links, or passwords. |
