# Laravel Graph Mail (Legacy — Laravel 8 / PHP 7.3+)

Send Laravel mail through the **Microsoft Graph API** instead of SMTP — no app passwords, no legacy auth, works with tenants that have Basic Auth / SMTP AUTH disabled (which is now most of them).

Drop-in Laravel Mail transport built on **SwiftMailer** (Laravel 8's mail stack): keep using `Mail::to(...)->send(new YourMailable)`, Notifications, and Mailables exactly as-is — just point the `mail.default` mailer at `graph`.

This is a **Laravel 8 / PHP 7.3+ compatible fork** of [`graph-mail/laravel-graph-mail`](https://packagist.org/packages/graph-mail/laravel-graph-mail), which targets Laravel 10+ and Symfony Mailer. If you're on Laravel 10 or newer, use that package instead — this one exists specifically for projects still on Laravel 8 and/or PHP 7.3–8.0.

## Why

Microsoft is steadily disabling legacy SMTP AUTH across Exchange Online tenants, which breaks the classic `MAIL_MAILER=smtp` + app-password approach. The Graph API's `sendMail` endpoint with an app-only `Mail.Send` permission is the supported modern replacement — but the Azure AD setup has a few genuinely confusing failure modes that this package's troubleshooting section (and built-in test command) are designed to catch fast.

## Requirements

- PHP 7.3, 7.4, or 8.0+
- Laravel 8 (SwiftMailer-based mail stack)
- An Azure AD tenant with permission to register an application and grant admin consent

## Installation

```bash
composer require graph-mail/laravel-graph-mail-legacy
php artisan vendor:publish --tag=graph-mail-config
```

## Azure AD Setup

1. **Register an app** — Azure Portal → App registrations → New registration. Note the **Application (client) ID** and **Directory (tenant) ID**.
2. **Create a client secret** — Certificates & secrets → New client secret. Copy the **Value** column immediately — it's only shown once. (The Secret ID, shown permanently, will *not* work as a credential — this is the single most common setup mistake.)
3. **Add the API permission** — API permissions → Add a permission → Microsoft Graph → **Application permissions** (not Delegated) → search `Mail.Send` → Add.
4. **Grant admin consent** — click "Grant admin consent for [tenant]" at the top of the API permissions page. You need Global Administrator or Privileged Role Administrator rights to do this. Confirm the Status column shows a green checkmark afterward.
5. **Pick a sender mailbox** — a licensed user mailbox or shared mailbox. Shared mailboxes (e.g. `noreply@yourdomain.com`) are recommended since they don't consume a license and are purpose-built for this.

## Configuration

`.env`:

```env
MAIL_MAILER=graph

MS_TENANT_ID=your-tenant-id
MS_CLIENT_ID=your-client-id
MS_CLIENT_SECRET=your-client-secret-value
MS_SENDER_EMAIL=noreply@yourdomain.com
```

`config/mail.php` — add the `graph` mailer:

```php
'mailers' => [
    // ...your other mailers
    'graph' => [
        'transport' => 'graph',
    ],
],
```

On Laravel 8, if your app still uses the legacy single-mailer config format, you can instead set:

```php
'driver' => 'graph',
```

## Usage

Works with Laravel's standard Mail API — no code changes needed beyond your mailer config:

```php
Mail::to('user@example.com')->send(new InvoiceMailable($invoice));

Mail::to('user@example.com')->queue(new WelcomeMailable($user));

Mail::raw('Plain text body', function ($message) {
    $message->to('user@example.com')->subject('Quick note');
});
```

### The sender is always `MS_SENDER_EMAIL` — by design

The transport **always** sends as the mailbox configured in `MS_SENDER_EMAIL`. It deliberately ignores `->from()` on a Mailable and Laravel's global `config('mail.from')`.

This is intentional, not a limitation. Those config paths have no relationship to which mailbox your Azure AD app is actually permitted to send as via `Mail.Send` — if the transport honored them, a stray `mail.from` default (or an unedited scaffold placeholder like `user@host`) could silently override the one mailbox you've actually granted Graph access to, producing a confusing `404 ErrorInvalidUser` from Graph instead of a clear local error.

```php
Mail::raw('Billing question follow-up', function ($message) {
    $message->to('customer@example.com')->subject('Re: Invoice #1234');
});
// Sent as MS_SENDER_EMAIL, regardless of any ->from() call.
```

If `MS_SENDER_EMAIL` is missing or isn't a valid email address (including a leftover placeholder like `user@host`), the transport throws a `GraphMailException` immediately, before ever calling Graph, so you get a clear local error instead of a `404 ErrorInvalidUser` round-trip.

### Attachments

```php
Mail::raw('See attached report', function ($message) {
    $message->to('user@example.com')
        ->subject('Monthly Report')
        ->attach(storage_path('app/reports/october.pdf'));
});
```

Inline/embedded images (e.g. `$message->embed(...)`) are excluded from the attachments sent to Graph, matching the behavior of the modern package.

## Testing your setup

The package ships an artisan command that checks token acquisition, a direct Graph API call, and the full Laravel Mail transport in one pass:

```bash
php artisan graph-mail:test your-test-address@example.com
```

Each step is isolated in the output so you can immediately see which layer is failing.

## Troubleshooting

These are the real failure modes you're likely to hit, in the order they tend to occur:

### `invalid_client` / `AADSTS7000215: Invalid client secret provided`

You copied the **Secret ID** instead of the **Secret Value**. Azure only shows the Value once, at creation time — if you've navigated away, it's gone for good. Create a new client secret and copy the Value column this time.

### `403 ErrorAccessDenied` on the sendMail call, even though the token was issued fine

A valid token proves your client ID/secret/tenant are correct — it does **not** prove you have the `Mail.Send` permission granted. This is the most common and most confusing failure. Three possible causes, in order of likelihood:

**1. Admin consent didn't actually take, despite the portal showing a checkmark.**

Verify the *real* grant state via Graph API rather than trusting the UI:

```
GET https://graph.microsoft.com/v1.0/servicePrincipals?$filter=appId eq '{your-client-id}'
```

Copy the returned `id`, then:

```
GET https://graph.microsoft.com/v1.0/servicePrincipals/{id}/appRoleAssignments
```

If this returns an **empty array**, no application permissions were actually granted — redo the admin consent step as a Global Administrator, then re-run this check to confirm a non-empty result containing the `Mail.Send` role (`b633e1c5-b582-4048-a93e-9f11b44c7e96`).

**2. An Exchange Online Application Access Policy is scoping your app to different mailboxes.**

This is invisible from the Azure Portal entirely — it's an Exchange-side restriction, not an Azure AD one. Even with `Mail.Send` correctly granted, many tenants restrict *which* mailboxes an app can act on. Ask an Exchange admin to run:

```powershell
Connect-ExchangeOnline
Get-ApplicationAccessPolicy
```

If a policy exists and doesn't include your sender mailbox, either add the mailbox to the policy's scoped group or create a new policy:

```powershell
New-ApplicationAccessPolicy -AppId "your-client-id" `
    -PolicyScopeGroupId "sender@yourdomain.com" `
    -AccessRight RestrictAccess `
    -Description "Allow Laravel app to send as this mailbox"
```

**3. The sender mailbox isn't a valid, licensed mailbox.**

Confirm `MS_SENDER_EMAIL` points to an actual licensed user or shared mailbox — not a distribution list, security group, or unlicensed account.

### `404 ErrorInvalidUser: 'user@host' is invalid`

`user@host` is Laravel's unedited scaffold placeholder for `MAIL_FROM_ADDRESS`. The transport ignores both `from()` and the global `mail.from` config and always uses `MS_SENDER_EMAIL` — if you still see this, check that `MS_SENDER_EMAIL` itself isn't set to a placeholder, and run `php artisan config:clear` after fixing it.

### `405 Method Not Allowed` on the sendMail call

Almost always a malformed URL, not an actual verb issue — usually caused by `MS_SENDER_EMAIL` being empty, or containing quotes/trailing whitespace/newlines from a copy-paste into `.env`. Run:

```bash
php artisan tinker
>>> dump(config('graph-mail.default_sender'));
>>> dump(strlen(config('graph-mail.default_sender')));
```

If the string length looks longer than the visible text, there's hidden whitespace — clean up the `.env` value and run `php artisan config:clear`.

## Testing (for contributors)

```bash
composer install
composer test
```

Tests use `Http::fake()` throughout — no real Azure/Graph credentials or network access required. Tests run on classic PHPUnit (not Pest, which requires PHP 8+) to stay compatible with PHP 7.3.

## Security

If you discover a security vulnerability, please email the maintainer directly rather than opening a public issue.

## License

MIT. See [LICENSE](LICENSE).
