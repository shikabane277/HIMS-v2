# HIMS v2.5.0-beta.1

**Pre-release · 2026-08-03**

Bug-fix release targeting four reported defects in the app shell and the password-reset flow, plus working outbound email and a test-suite fix. No schema changes; no breaking changes.

---

## 🐛 Bug Fixes

### Notifications button did nothing
The topbar bell icon had no event listener and no panel — clicking it was a no-op. It now opens a dropdown with a **Mark all read** action and an empty state ("You're all caught up."). The panel is UI-only for now; the `notifications` table is not yet wired as a data source.

### Help / FAQ button did nothing
Same root cause. The `?` icon now opens a dropdown with five collapsible FAQ entries covering AI gap analysis, AI unavailability, succession candidates, password reset, and support contact.

Both dropdowns share one controller: opening one closes the other, clicking outside closes both, and <kbd>Esc</kbd> closes them alongside the existing sidebar and slide-over panel.

### AI chatbot always replied in Tagalog
The shared system prompt described bilingual capability but set no default language, while the widget UI greeted with "Kamusta!" and prompted "Ask in English or Tagalog…" — the model mirrored those cues. Fixed at both layers:

- `AbstractAiProvider::systemContext()` now instructs: reply in English by default; switch to Tagalog or Taglish only when the user clearly writes in it.
- All Tagalog cues removed from the chatbot widget (header, welcome message, placeholder, launcher label).

Bilingual support is unchanged — a user who writes in Tagalog still gets Tagalog back.

### Forgot password did not work at all
Four separate issues, all fixed:

1. **No mail transport** — `MAIL_MAILER=log` silently swallowed every reset email. Now requires a real transport; a `Log::warning` fires server-side when `log`/`array` is active so the misconfiguration is visible to an operator without disclosing it to the visitor.

2. **Transport failure returned HTTP 500** — `Password::sendResetLink()` does not catch transport exceptions internally. `PasswordResetLinkController` now wraps the call and returns a friendly, actionable message on failure instead of crashing an unauthenticated page.

3. **From header was always empty** — `env('MAIL_FROM_ADDRESS', 'hello@example.com')` returns `''` (not the default) when the key is present but blank. Every send failed with *"An email must have a From or Sender header."* Fixed with `?:` chaining: falls back to `MAIL_USERNAME`, then `no-reply@hospital.ph`.

4. **App password with spaces broke the entire CLI** — Google displays app passwords in groups of four; pasted verbatim, the unquoted whitespace made dotenv fail with *"The environment file is invalid!"*, taking down every artisan command. Documented in `.env` and `.env.example`.

---

## ✨ New

### Consumer webmail presets
`MAIL_MAILER=gmail | outlook | yahoo` now selects host, port, and scheme automatically. Only `MAIL_USERNAME` and `MAIL_PASSWORD` differ between providers. Work/school Outlook tenants can override with `MAIL_OUTLOOK_HOST=smtp.office365.com`.

### `php artisan hims:mail-test {email}`
Diagnostic command that prints the resolved mailer settings, warns on a `MAIL_FROM_ADDRESS` / `MAIL_USERNAME` mismatch, fails with an explicit missing-key list before attempting a send, and on failure prints provider-specific app-password URLs. Run after every `.env` change.

---

## 🔧 Test Suite

**22 of 25 tests now pass, up from 1.**

The competency trigger migration (`DECLARE`/`SET`) and the recognition view migration (`CREATE OR REPLACE VIEW` with `CONCAT()` / `DATE_FORMAT()`) both issued MySQL-only DDL unconditionally, crashing the sqlite `:memory:` test connection before any test ran. Both are now behind `if (DB::getDriverName() === 'mysql')`.

The 3 remaining failures are stale Breeze scaffolding (self-registration route removed, root redirects to login) — not regressions.

---

## 🔒 Security

- Removed a config disclosure on the unauthenticated `/forgot-password` page that named `MAIL_MAILER=log` and listed internal remediation steps to any visitor.
- The failure log now names the mailer actually in use (not a hardcoded `smtp` block) so the correct host appears when a preset like `gmail` is active.
- No secrets are committed — `.env` is gitignored; only `.env.example` with empty placeholders is tracked.

---

## ⚠️ Known Issues

- **`APP_URL=http://localhost:8000`** is baked into emailed reset links — correct on the dev machine, dead on any other device. Set `APP_URL` to the deployment HTTPS URL for production.
- **Railway does not read `.env`** — set `MAIL_MAILER`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `APP_URL` in the Railway dashboard.
- **Consumer Gmail is not a production transport** (~500 sends/day; reset mail from a personal address is frequently spam-filed). Use Brevo, SendGrid, or Resend on the hospital domain for production.
- **Notifications dropdown has no data source** — the `notifications` table is schema-only; the bell always shows "You're all caught up."

---

## 📋 Setup (to enable real email delivery)

1. Generate a Gmail app password — enable 2-Step Verification first, then [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords). Paste the 16-character code **without spaces**.
2. Edit `hims-app/.env`:
   ```
   MAIL_MAILER=gmail
   MAIL_USERNAME=you@gmail.com
   MAIL_PASSWORD=xxxxxxxxxxxxxxxxxxxx
   MAIL_FROM_ADDRESS=you@gmail.com
   ```
3. Run `php artisan config:clear` then `php artisan hims:mail-test you@gmail.com`.
4. Restart `php artisan serve` — a running server holds the old config.

---

## Files Changed

| File | Change |
|---|---|
| `resources/views/layouts/hims.blade.php` | Notifications + Help dropdowns; chatbot English default |
| `public/css/hims.css` | Dropdown styles (~85 lines) |
| `app/Services/Ai/AbstractAiProvider.php` | English-default system prompt |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | Exception wrapper; removed config disclosure |
| `config/mail.php` | Gmail/Outlook/Yahoo presets; From-header fix |
| `app/Console/Commands/MailTest.php` | **New** — `hims:mail-test` diagnostic command |
| `database/migrations/2026_01_01_000030_create_competency_tables.php` | MySQL guard on triggers |
| `database/migrations/2026_01_01_000070_create_recognition_tables.php` | MySQL guard on view |
| `resources/views/auth/forgot-password.blade.php` | Removed config disclosure hint |
| `.env.example` | Mail setup documentation |
| `HIMS_PATCH_NOTES.md` | v2.5.0-beta.1 entry |
| `HIMS_ARCHITECTURE_AND_SECURITY.md` | Mail transport, AI language, test-suite sections |
| `HIMS_SYSTEM_DOCUMENTATION.md` | Delivery status, notifications, test-suite status |

---

*Full change history: [HIMS_PATCH_NOTES.md](HIMS_PATCH_NOTES.md)*
