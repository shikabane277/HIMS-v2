# HIMS v2.3.0

Multi-provider AI, role-based access control, competency gap analysis, and a functional succession pipeline.

This release makes the AI layer vendor-agnostic, adds real authorisation (previously any logged-in account could reach every module), and reconciles the reference documentation with what is actually built.

---

## ✨ Highlights

### Multi-provider AI

The AI integration no longer depends on a single vendor. Application code depends on the `App\Contracts\AiProvider` interface; `AiManager` resolves the driver named by `AI_PROVIDER`.

| Provider | `AI_PROVIDER` | Default model |
|---|---|---|
| Google Gemini *(default)* | `gemini` | `gemini-2.5-flash` |
| OpenAI | `openai` | `gpt-4o-mini` |
| Anthropic | `anthropic` | `claude-opus-5` |
| OpenAI-compatible | `compatible` | *(you set it)* |

The `compatible` slot covers Groq, DeepSeek, xAI, Mistral, Together, OpenRouter, and Ollama — anything speaking the OpenAI chat-completions format. Switching providers is a one-line change in `.env`.

- Raw `Http::` calls throughout, no vendor SDKs
- Per-provider fallback model chains: an unavailable model ID advances to the next candidate
- `ask()` **never throws** for an API or config error — it returns a `⚠️`-prefixed string that callers detect and degrade on, so an AI outage never takes down a page

### Role-based access control

Authorisation was previously *"logged in and verified"* only. It is now enforced per route.

- **13 Gates** over `users.role` — `admin` | `hr_manager` | `supervisor` | `staff`
- `EnsureUserHasRole` middleware (alias `role`) applied across `routes/web.php`
- The same Gates drive both the `@can` checks that show/hide sidebar navigation and the middleware that enforces access, so the menu and the guard cannot drift apart
- Row-level scoping helpers on the base controller: admin/HR see the organisation, supervisors their department, everyone else only their own record
- **Public self-registration disabled** — accounts are provisioned by an admin via `/users`, which assigns the role and the linked employee profile explicitly

### Competency gap analysis

New module comparing assessed proficiency against role requirements, with organisation, department, and per-employee views plus a JSON endpoint. AI-generated narratives degrade gracefully when the provider is unavailable.

### Succession candidate pipeline — now functional

The pipeline rendered data but almost nothing could be changed.

- **Development milestones** — full CRUD. The table was readable but had no write path, so it was permanently empty and Dev Progress always showed 0%. Milestones advance `not started → in progress → completed`; the completion date is stamped automatically and cleared if the status moves back.
- **Edit and withdraw** nominations. Withdraw removes the candidate and their milestones in a transaction.
- **Working position filter** — submitted via GET so the view is shareable and survives a refresh.

---

## 🐛 Fixes

### 9-Box label could contradict its own scores

The nomination form offered a manual 9-Box dropdown, and the controller *preferred* the submitted value over the computed one. A candidate could be saved with performance 5 / potential 5 labelled `under`, and the badge would display the contradiction.

Both reference documents described `nine_box_label` as a MySQL `GENERATED ALWAYS AS ... STORED` column whose value *"cannot be falsified from application code."* `SHOW COLUMNS` returns a plain `varchar(30)` — **the generated-column DDL was never applied**, so nothing enforced the invariant the docs promised.

The label is now derived server-side on every insert and update, and submitted values are ignored. The form shows a live preview instead, so the rater still sees the resulting placement without being able to override it. Verified by posting `nine_box_label=star` alongside scores of 1/1 — the stored label was `under`.

Scores are also **1–5**, not the 1–3 the docs specified, and there is no `CHECK` constraint; the range is enforced by request validation.

### Dev Progress percentage was wrong

Computed as `ROUND(AVG(CASE WHEN status = 'completed' THEN 100 ELSE 0 END))`, which is not a completion fraction. Replaced with an explicit ratio guarded by `NULLIF(COUNT(...), 0)` for the zero-milestone case.

### Missing `estimated_vacancy_date` column

The succession position form posted this field and the controller wrote it, but the column was never created — every submission failed. Added via migration.

### Login accounts with no linked employee record

Around a dozen write paths route `auth()->user()->employee_id` into `NOT NULL CHAR(36)` FK columns. Accounts predating the link had `employee_id = NULL`, so those inserts failed. A backfill migration links by email, creates profiles for the remainder, and ensures an admin exists. It is additive and never reassigns an existing link.

### Bad `AI_PROVIDER` value returned a 500

An unrecognised provider name threw `InvalidArgumentException`, taking down every page that touches AI. Now falls back to a `NullAiProvider` that returns a `⚠️` message naming the valid options. Provider names are also normalised, so `Gemini`, `GEMINI`, and `google` all resolve correctly.

---

## 🔒 Security

- **Public self-registration removed.** A self-registered account would arrive with no `employee_id` and the `staff` role default — a stranger holding a login to workforce data.
- **Widened the `.env` backup gitignore pattern** to `.env.backup-*` so timestamped backups cannot be committed.

---

## 📚 Documentation

`HIMS_ARCHITECTURE_AND_SECURITY.md` and `HIMS_SYSTEM_DOCUMENTATION.md` described an intended system rather than the built one. Both are now reconciled against the source, with unimplemented features marked 📋 rather than described as present.

Corrected: hand-authored `public/css/hims.css` (not Bootstrap 5 — only Bootstrap *Icons*) · raw Query Builder (not Eloquent/Observers) · database cache and file sessions (not Redis) · four roles (not six) · Gates + middleware (not Policies) · real web routes replacing ~110 lines of `/api/v1/...` endpoints **that were never built**.

New: `HIMS_PATCH_NOTES.md` (full change history) and `CLAUDE.md` (repository guidance).

---

## ⚠️ Known issues

**24 of 25 tests fail** — pre-existing, not introduced here. The competency trigger migration calls `DB::unprepared('CREATE TRIGGER … DECLARE …')` with no SQLite guard, which is invalid on the `:memory:` test connection used by `phpunit.xml`. Fix by guarding on `DB::getDriverName() === 'mysql'` or pointing the test suite at MySQL.

**Not implemented** (specified, mostly with schema already migrated): field-level encryption · audit logging to `audit_trails` · TOTP MFA · Redis caching · Zapier dispatch (service written, never called) · LMS quiz engine · training pre/post-tests · certificate issuance · review and succession approval workflows · in-app notifications · REST API.

The system should be treated as **pre-compliance** for HIPAA / RA 10173 until encryption at rest, MFA, and audit logging are built. See `HIMS_SYSTEM_DOCUMENTATION.md` §7.5.

---

## ⬆️ Upgrading

```bash
cd hims-app
composer install
php artisan migrate        # adds estimated_vacancy_date + backfills user/employee links
php artisan config:clear
```

No `.env` changes are required — `AI_PROVIDER` defaults to `gemini`, so an existing `GEMINI_API_KEY` keeps working unchanged. To switch providers, see the AI block in `.env.example`.

**Full changelog:** https://github.com/shikabane277/HIMS-v2/compare/5f33d2a...v2.3.0
