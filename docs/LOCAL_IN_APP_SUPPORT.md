# Local In-app Support Register

Quetta AgriLink’s local support workflow is an authenticated **in-app register** for the PHP/XAMPP application. It records accountable support cases, local conversations, assignment, lifecycle history, audit events, and in-app alerts. It does **not** send email, SMS, SMTP traffic, webhooks, or external-helpdesk messages.

## Prerequisite

Import `database/migrations/20260827_add_in_app_support_desk.sql` once after the preceding migrations in [`LOCAL_XAMPP.md`](LOCAL_XAMPP.md). A fresh import of `database/quetta_agrilink.sql` already contains the support tables, but it intentionally contains no support cases, messages, assignments, or alerts.

## Operating workflow

| Step | Account action | Result |
|---|---|---|
| 1 | Sign in and open **In-app support** from the workspace menu, or use **Contact** then sign in. | The account reaches its local support register. |
| 2 | Select a category, enter a short subject and operational context, then record the request. | The request receives a local `QAH` reference and routes to the relevant accountable role. |
| 3 | The routed cold-storage or transport provider claims the request, or an administrator assigns it to an active account in the routed role. | A named local handler is recorded and the requester receives only an in-app alert. |
| 4 | The requester and accountable handler exchange follow-ups inside the request record. | Each message and resulting status transition is retained in local history. |
| 5 | The handler records `in progress`, `waiting on requester`, `resolved`, or `closed`. | The requester is notified inside the application; administrators retain register oversight. |

## Routing and privacy rules

| Category | Routed local desk |
|---|---|
| Account access, marketplace, local operator, or other platform support | Administrator dashboard |
| Cold storage | Cold-storage provider dashboard |
| Transport | Transport provider dashboard |

Requesters can see only their own records. A routed provider can see unclaimed requests for its own role and the requests it has claimed. Administrators can oversee all support records and may assign only an active account that has the request’s routed role. The register never displays requester passwords, reset links, recovery codes, selectors, tokens, or password hashes.

> Do not place passwords, reset links, recovery codes, tokens, or other account secrets in a support request. The form rejects these terms. For an account-access problem, use the existing local recovery workflow instead.

## Local verification

Use a disposable development database when exercising the workflow. Verify that an authenticated requester can create a category-routed request; a wrong-role account cannot open its detail; the correct provider can claim and respond; the requester sees the response and local alert; an administrator can oversee and assign only to the routed role; and no email, SMS, SMTP, external HTTP delivery, or secret-bearing content is created. Remove all temporary support cases, messages, notifications, and audit records after the verification run.
