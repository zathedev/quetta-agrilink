# Production Support Activation

Quetta AgriLink currently uses the intentionally safe **no-channel-yet** state. The public contact route does not show an invented email address, open a simulated form, or send messages to an unmonitored mailbox. It remains that way until an organization-owned support channel is available.

## Before activating a channel

- Confirm that the organization owns the support email address or helpdesk workspace and that a named person or team monitors it during the promised support window.
- Confirm the correct customer-data and privacy process for incoming requests. Support messages must never request or accept passwords, password-reset links, reset selectors, reset tokens, recovery codes, or token hashes.
- Confirm that the support channel is appropriate for the deployment and that its public URL is HTTPS when a helpdesk is used.

## Activate an owned support email

In the production copy of `config/config.php`, set the mode to `email` and provide the exact approved address. Keep the helpdesk URL empty.

```php
const SUPPORT_CHANNEL_MODE = 'email';
const SUPPORT_EMAIL = '<approved and monitored support address>';
const SUPPORT_HELPDESK_URL = '';
```

The contact page validates the address before it appears as an `mailto:` action. An empty or invalid value keeps the page in its unconfigured state.

## Activate an authenticated helpdesk

In the production copy of `config/config.php`, set the mode to `helpdesk` and provide the exact approved HTTPS destination. Keep the email value empty unless the product policy explicitly requires both channels.

```php
const SUPPORT_CHANNEL_MODE = 'helpdesk';
const SUPPORT_EMAIL = '';
const SUPPORT_HELPDESK_URL = 'https://<approved-support-host>/';
```

The contact page validates the URL and requires HTTPS before it exposes the action. Do not add API keys, OAuth credentials, or service secrets to these PHP constants. If a helpdesk integration later needs credentials, configure it through the relevant deployment secret mechanism and test it separately.

## Verify before publishing

- Load `contact.php` in a fresh browser session and confirm that the correct action appears only for the configured channel.
- Send a harmless test request through the owned channel and confirm a response path is operational.
- Confirm that no support content asks customers to provide authentication or recovery secrets.
- Record the support owner, escalation path, retention process, and review date in the production operating documentation.
