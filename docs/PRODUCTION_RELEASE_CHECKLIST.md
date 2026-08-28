# Quetta AgriLink Production Release Checklist

This checklist governs release of the authoritative **PHP/MySQL/Apache/XAMPP** application. Complete each gate in order, keep the completed record with the release ticket, and do not treat an unchecked item as a waiver.

> **Public-launch rule:** A public release is blocked until every applicable **Blocker** is resolved, including an owned support channel, named operational owner, approved data source, backups, security configuration, and final acceptance evidence. A local demonstration may remain intentionally unconfigured only when it has no live customer data and is not represented as production-ready.

## Release record

Create one release record before changing the deployment environment. This separates an accountable, reversible release from ordinary local development.

| Field | Record before release |
|---|---|
| Release identifier | Git commit SHA and planned release date/time in Asia/Karachi |
| Release owner | Named technical owner responsible for deployment and rollback |
| Operational owner | Named person responsible for marketplace operations and customer escalation |
| Scope | Features, migrations, data changes, and configuration changes included |
| Deployment target | Exact Apache/XAMPP path, database name, and expected public/private exposure |
| Backup evidence | Database dump filename, timestamp, storage location, and restoration-test result |
| Rollback point | Previous Git SHA, database backup reference, and named rollback operator |
| Approval | Technical owner and operational owner initials/date/time |

## Gate 1 — Scope, ownership, and data classification

Confirm that the deployment target, release objective, owner, and availability expectation are understood before copying files or changing the database. Do not use a production release to discover whether a support process or data source is appropriate.

| Check | Required evidence | Release status |
|---|---|---|
| Scope is fixed | Release record identifies the commit SHA, included migrations, and required downtime or maintenance window. | Blocker |
| Accountable ownership exists | A technical owner and operational owner are named and can be reached during the release window. | Blocker |
| Data source is approved | Each market-price, listing, facility, and account record has an approved source, owner, and refresh process. | Blocker for live data |
| Customer-data basis is defined | Consent, retention, access, and deletion responsibilities are agreed before real customer data is loaded. | Blocker for live data |
| Demo records are absent | Run the cleanup migration only for older local databases that still contain known fictional activity; confirm that no real records will be removed. | Required when upgrading older demo data |

## Gate 2 — Backup, database, and migration safety

Take a restorable backup before any schema or data migration. A backup that has not been found, opened, and restored in a non-production database is not sufficient release evidence.

| Check | Required evidence | Release status |
|---|---|---|
| Pre-release backup exists | A consistent MySQL/MariaDB dump is created before deployment, stored outside the web root, and recorded in the release record. | Blocker |
| Restoration is tested | The dump restores into a disposable database and a minimal read query succeeds. | Blocker |
| Migration order is recorded | Apply `record_attachments`, `saved_marketplace_filters`, then `default_saved_marketplace_filters`, followed by the dated 20260826 and later migrations in the documented order. | Blocker |
| Demo cleanup is scoped | `20260827_remove_fictional_demo_data.sql` is used only for the documented legacy fictional dataset and only after confirming its impact. | Required when applicable |
| Database access is least-privilege | The application uses a dedicated database account with only the privileges it needs; database administrator credentials are not stored in production files. | Blocker |

## Gate 3 — Apache, PHP, configuration, and file handling

The release environment must serve the PHP application from the configured folder and protect both configuration and upload storage.

| Check | Required evidence | Release status |
|---|---|---|
| Runtime is healthy | Apache and MySQL/MariaDB start successfully; the PHP home route returns the expected page. | Blocker |
| Route base is correct | `APP_URL` exactly matches the deployment folder, and navigation, forms, AJAX URLs, and session cookies use that base. | Blocker |
| Production configuration is private | `config/config.php` is not publicly readable, uses production database credentials, and has no disposable test database values. | Blocker |
| Upload limits and storage are safe | PHP limits remain above the 5 MB application policy, `uploads/attachments/` is writable by Apache without broad write permissions, and `uploads/.htaccess` is present. | Blocker when attachments are enabled |
| Local assets are available | `assets/fonts/` and the local font stylesheet are copied with the application; cache-busted CSS is verified after a fresh browser reload. | Required |
| HTTPS decision is explicit | Public deployment uses HTTPS and confirms secure-session behavior; localhost-only XAMPP work is documented as non-public. | Blocker for public exposure |

## Gate 4 — Identity, security, and protected records

Release only after privileged and public routes have been checked against the deployed configuration. Recovery and contact-review records are accountable administrative tools, not a mechanism for transmitting secrets.

| Check | Required evidence | Release status |
|---|---|---|
| Development credentials are replaced or disabled | All documented `*.demo@quettaagrilink.test` credentials are disabled or replaced with individually accountable accounts before a public release. | Blocker for public exposure |
| Role boundaries are tested | A lower-privilege account is denied administrator routes; each role reaches only its own workspace and records. | Blocker |
| CSRF and sessions are verified | State-changing forms reject a missing/invalid CSRF token; sessions expire according to the configured idle period. | Blocker |
| Recovery safeguards are preserved | Public recovery stays generic; printed/exported recovery records exclude passwords, links, selectors, tokens, and hashes. | Blocker |
| Contact exports are safe | Contact-review exports omit free-text evidence notes, recovery material, credentials, reset links, selectors, tokens, and hashes. | Blocker |
| Error display is safe | Browser responses do not expose database credentials, stack traces, filesystem paths, or reset data. | Blocker |

## Gate 5 — Support and operating readiness

The current repository defaults to an intentional **no-channel-yet** contact state. This is safe for a local demonstration but is not a substitute for a real customer-support process.

| Check | Required evidence | Release status |
|---|---|---|
| Support channel is owned and monitored | An approved support email or HTTPS helpdesk is configured, tested, and assigned to a named owner. | Blocker for public exposure |
| Support activation is complete | [`PRODUCTION_SUPPORT.md`](PRODUCTION_SUPPORT.md) has been followed, including the no-secret handling rule. | Blocker for public exposure |
| Incident escalation is documented | The support owner knows the path for account access, data, abuse, and service-availability incidents. | Blocker for public exposure |
| Operational limits are clear | Users are not promised email/SMS recovery, automatic contact verification, payments, pricing guarantees, or data freshness that the application does not provide. | Blocker |

## Gate 6 — Acceptance, accessibility, and release evidence

Run automated checks in an isolated development database before the release window, then repeat the relevant manual checks in the release environment. Do not use a test that submits marketplace, recovery, or administrative actions against live data without approval.

| Check | Required evidence | Release status |
|---|---|---|
| Code checks pass | PHP syntax checks, `node --check assets/js/app.js`, checks of the local `.mjs` runners, and `git diff --check` pass for the release commit. | Blocker |
| Local visual regression passes | Run `node scripts/local-visual-regression.mjs` against a same-machine reviewed baseline; investigate every intended or unexpected difference. | Required |
| Controlled acceptance passes | Run `node scripts/local-xampp-acceptance.mjs` against an isolated test database and retain its JSON evidence. | Required |
| Manual route verification passes | Fresh-cache desktop and mobile checks cover public routes, sign-in, account workspaces, marketplace controls, keyboard focus, and administrative exports. | Blocker |
| Accessibility checks pass | Keyboard focus is visible, navigation is reachable without a pointer, labels describe inputs, and destructive actions are not triggered by incidental keyboard focus. | Blocker |

## Gate 7 — Deployment, smoke test, and rollback readiness

Deploy the reviewed commit only after all blockers are closed. Pause immediately if the deployed route base, database, asset loading, or authorization behavior differs from the release evidence.

| Step | Required outcome |
|---|---|
| 1. Place reviewed files | Copy the reviewed PHP package, including `assets/fonts/`, `uploads/.htaccess`, migrations, and documentation, into the configured Apache location. |
| 2. Apply configuration | Set the correct production `APP_URL`, dedicated database account, supported upload limits, and approved support-channel configuration. |
| 3. Apply migrations | Apply the documented migrations once, in order, after the pre-release backup. Record each result. |
| 4. Run smoke tests | Open public home, sign-in, marketplace, market prices, contact, one role workspace, and one protected export route using approved test accounts. |
| 5. Refresh assets | Use **Ctrl+F5** in a fresh browser session and verify local fonts, styles, images, and navigation. |
| 6. Monitor and sign off | Review Apache/PHP and database errors, confirm the support route, then complete the release record approvals. |

If rollback is needed, stop further state changes, preserve relevant logs, restore the prior application commit, and restore the pre-release database backup only under the named rollback owner’s direction. Document the decision, time, affected records, and next investigation step.

## Gate 8 — Post-release review

Complete the review within the first agreed operating window. Keep the same record with the release ticket so later changes can be traced to an accountable baseline.

| Check | Required evidence |
|---|---|
| Application health | Apache/PHP and database logs show no recurring errors or unexpected authorization failures. |
| Data integrity | New records appear only for intended owners, references are generated correctly, and protected exports remain secret-free. |
| Support readiness | The configured channel receives the harmless test request and the owner confirms the escalation path. |
| Backup continuity | The next scheduled backup is present, stored outside the web root, and assigned to an owner. |
| Follow-up actions | Any deferred items have an owner, priority, target date, and a new entry in `remaining-work.md`. |

## Final release sign-off

Sign only when all blockers are complete or an explicit, approved non-public limitation is recorded.

| Sign-off | Name | Date/time | Notes |
|---|---|---|---|
| Technical release owner |  |  |  |
| Operational owner |  |  |  |
| Support owner |  |  |  |
| Data owner |  |  |  |
