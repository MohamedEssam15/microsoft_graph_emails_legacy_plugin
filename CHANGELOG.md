# Changelog

All notable changes to `graph-mail/laravel-graph-mail-legacy` will be documented in this file.

## v1.0.0

Initial release — a Laravel 8 / PHP 7.3+ compatible fork of [`graph-mail/laravel-graph-mail`](https://packagist.org/packages/graph-mail/laravel-graph-mail).

- `GraphTransport` rewritten against SwiftMailer's `Swift_Transport` API (`Illuminate\Mail\Transport\Transport` base class, `Swift_Mime_SimpleMessage`), matching Laravel 8's mail internals instead of Symfony Mailer.
- Cached OAuth2 client-credentials token service (`MicrosoftGraphTokenService`), ported unchanged.
- Support for CC/BCC/Reply-To, HTML and plain-text bodies, and file attachments (excluding inline embeds) in the Graph `sendMail` payload.
- Sender always resolved from `MS_SENDER_EMAIL`, validated locally before calling Graph, ignoring any `from()` set on the message.
- `graph-mail:test` artisan command for end-to-end diagnostics (token, direct Graph call, Laravel Mail transport).
- Test suite ported from Pest to classic PHPUnit (Orchestra Testbench ^6.0) for PHP 7.3 compatibility, using `Http::fake()` — no real network calls.
- All source written in PHP 7.3-safe syntax: no constructor property promotion, no `readonly` properties, no arrow functions, no typed properties, no nullsafe operator/match/enums.
