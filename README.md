# HIMS v2

**Performance & Development for hospital staff** — a Laravel module that tracks competency, appraisal, training, credential compliance and succession for the clinical and non-clinical workforce of a Philippine hospital. Built for HR officers, department supervisors and quality/accreditation teams who currently run all of this on spreadsheets.

<!-- Badges are intentionally omitted until CI is configured. -->

---

## Screenshots

> **Not yet captured.** Drop images into `docs/screenshots/` and uncomment the block
> below. Suggested set: the dashboard, an employee gap analysis, a review scoring
> screen, and the accreditation report.

<!--
| Dashboard | Competency gap analysis |
|---|---|
| ![Dashboard](docs/screenshots/dashboard.png) | ![Gap analysis](docs/screenshots/gap-analysis.png) |

| Review scoring | Accreditation report |
|---|---|
| ![Review scoring](docs/screenshots/review-score.png) | ![Accreditation](docs/screenshots/accreditation.png) |
-->

---

## Features

The application provides **seven domain modules** — Performance, Competency, AI Gap
Analysis, Learning, Training, Recognition, and Succession — plus **Reports & Analytics**,
Employees / Departments / Users & Access / Audit Trail administration, Profile & GDPR
Data Portability, and the Dashboard and My Development surfaces.

- **Performance appraisal** — review cycles, a weighted KPI library, and supervisor
  reviews. Review authority follows the *reporting line* (`employees.supervisor_id`),
  not job title: there is no self-review and no peer review, and even admin/HR must
  use a logged exception path with a typed reason to review outside the chain.
  **People Manager controls who may appear in Reports To. Account role controls what
  they may do inside HIMS.** New assignments require an active People Manager with a
  linked Supervisor, HR Manager, or Admin account; the flag never promotes an account.
- **Competency management** — a seeded framework of domains, categories and
  competencies; per-employee assessments; department-level skills-gap matrix;
  credential tracking with expiry and reassessment windows; and external HR1/HR2 API
  synchronization (`system_integrations`).
- **Learning & CPD** — a course catalogue, **6 hospital clinical & compliance modules**
  (`/learning/modules`: `MOD-INF-101` through `MOD-DPA-601`), required-training
  assignment, learning pathways, renewal oversight, accreditation reporting, and CPD
  logging with a separate verification step. Course enrolment is assignment-only;
  completing a course credits verified CPD hours.
- **Training logistics** — session scheduling with a same-venue/same-start-time guard,
  self-service registration, roster attendance marking, and post-session feedback from
  anyone marked present.
- **Succession planning** — key positions, named candidates, confidential readiness
  data, vacancy risk, quarterly position reviews, evidence and development milestones.
- **Recognition** — named public or private badges/posts, audience-limited comments and
  reactions, moderation and a public-only leaderboard.
- **Reports & Analytics (`/reports`)** — executive workforce, performance, competency,
  learning, and compliance analytics with custom report generation and CSV export.
- **AI Gap Analysis** — a per-employee and per-department competency gap narrative
  built from assessments and review scores, with strict privacy defaults
  (`ai_include_comments = 0`, `ai_redact_names = 1`). Admin, HR manager and supervisor only.
- **Compliance & accreditation oversight, inside Learning** — the **Required
  Training**, **Renewals** and **Reports** tabs, all three behind the
  `view-compliance` gate. Assign multiple courses or sessions at once to an employee,
  department, role, or the whole hospital and chase completion from modal rosters.
- **AI assistant** — provider-agnostic (Gemini, OpenAI, Anthropic, or any
  OpenAI-compatible host). It answers questions about the app *and* executes actions
  on request, gated by the signed-in user's role and written to an audit trail.
  **Employee personal data is strictly off-limits (`AiAccessPolicy` blocks `employees`
  for all roles).** Destructive actions require an explicit in-chat confirmation.
- **Security, 2FA, Record Access & GDPR / RA 10173 Compliance** — mandatory Two-Factor
  Authentication on every login (Email OTP or RFC 6238 Authenticator App TOTP),
  5-minute silent session inactivity timeout, in-place **Record Access Password**
  blur/unlock gate (`RecordAccess`), HTTP security headers (`SecurityHeaders`),
  explicit GDPR privacy consent banner, self-service **Download My Data (JSON)**
  (`GET /profile/export-data`), self-service account erasure (`DELETE /profile`),
  separated employee PII anonymization (`POST /employees/{id}/anonymize`), and daily
  automated data retention pruning (`php artisan hims:prune-expired-data`).

Also included: a Facebook-style in-app notification feed with unread/read state and
deep links, a permission-aware global search that opens the relevant module/tab, and
a schedulable command that alerts employees and supervisors about expiring credentials
and due reassessments. That command always writes the in-app notifications and the
`credential_alert_log`; the **email** half is opt-in and **off by default**
(`CREDENTIAL_ALERT_EMAIL`, `config/hims.php`), so turn it on only once
`php artisan hims:mail-test <address>` actually delivers.

**Role-based access** is enforced at the route level (20 gates plus a `role:`
middleware) and, in the modules that handle personal records, at the row level — a
supervisor sees only their direct reports where that module applies the reporting-line
rule, while HR and admin see the organisation-wide records permitted to their role.

See [HIMS Access and Visibility Rules](HIMS_ACCESS_AND_VISIBILITY.md) for the
recognition, review, succession and audit boundaries.

---

## Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| PHP | **8.3+** | with the usual Laravel extensions (`pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`) |
| Composer | 2.x | |
| MySQL | **8.0+** | required for normal use — the app installs MySQL triggers and uses MySQL-only SQL (`CONCAT`, `DATE_FORMAT`, `FIELD`, `GREATEST`) |
| Node.js | **20.19+** or **22.12+** | required by Vite 8 |
| npm | 10+ | |

SQLite is used only by the test suite (in-memory) — not for running the app.

---

## Installation

```bash
git clone https://github.com/shikabane277/HIMS-v2.git
cd HIMS-v2/hims-app
```

> The Laravel application lives in **`hims-app/`**, not the repository root. Run all
> commands from there.

**1. Install dependencies and create your environment file**

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

**2. Point it at a MySQL database.** Create the database first, then edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hims_v2
DB_USERNAME=root
DB_PASSWORD=
```

**3. Build the schema and seed demo data**

```bash
php artisan migrate --seed
```

**4. Build front-end assets**

```bash
npm install
npm run build
```

There is also a one-shot `composer setup` (install → `.env` → key → migrate → npm
build). It migrates using whatever `.env` already says, so if you want MySQL, edit
`.env` **before** running it.

---

## Usage

Start everything — web server, queue worker, log tailer and Vite — with one command:

```bash
composer dev
```

Or just the app:

```bash
php artisan serve      # http://127.0.0.1:8000
```

### Demo logins

`php artisan migrate --seed` creates five accounts, all with the password
`password`. Sign in as different roles to see how the interface and permissions
change.

| Email | Role | Sees |
|---|---|---|
| `admin@hospital.ph` | `admin` | everything, including **Users & Access**, **Audit Trail**, and **Record Access Password** configuration |
| `l.garcia@hospital.ph` | `hr_manager` | everything **except** Users & Access and Audit Trail — user accounts and security settings are admin-only |
| `m.santos@hospital.ph` | `supervisor` | own record + direct reports |
| `j.reyes@hospital.ph` | `staff` | own record only |
| `r.lim@hospital.ph` | `staff` | own record only |

Two role notes the table cannot hold: **no role has blanket performance-review
access** — reviewer legitimacy is the reporting line, so admin and HR reach outside
it only through a logged exception path — and a supervisor's row-level visibility is
their reporting line, not their department.

### Maintenance commands

```bash
# Email employees and supervisors about expiring credentials / due reassessments
# (in-app notifications always; email only if CREDENTIAL_ALERT_EMAIL is on)
php artisan hims:scan-credential-expiry
php artisan hims:scan-credential-expiry --dry-run   # report only, writes and sends nothing

# Synchronize employee records from configured HR1 & HR2 external APIs
php artisan hims:sync-hr

# GDPR Art. 5(1)(e) automated data retention pruning (expired tokens, old read notifications, old AI chats)
php artisan hims:prune-expired-data

# Verify your mail transport actually works
php artisan hims:mail-test you@example.com
```

### Tests and formatting

```bash
composer test                                    # full suite (362 tests; 21 sqlite skips)
php artisan test --filter=test_profile_page_is_displayed
vendor/bin/pint                                  # code formatter
```

`composer test` runs against an in-memory SQLite database, so a handful of tests
that depend on MySQL-only SQL are skipped. To exercise those, name a **scratch**
database on the command line:

```bash
DB_CONNECTION=mysql DB_DATABASE=hims_scratch php artisan test
```

> [!WARNING]
> Never run `php artisan test --env=testing`. There is no `.env.testing`, so Laravel
> falls back to `.env` — and `RefreshDatabase` on a MySQL connection runs
> `migrate:fresh`, which would **drop your development database**. Plain
> `composer test` is safe; it is pinned to SQLite by `phpunit.xml`.

---

## Configuration

All configuration is via `hims-app/.env`. See `.env.example` for the annotated full
list; these are the settings that matter most.

### Application

| Variable | Default | Notes |
|---|---|---|
| `APP_TIMEZONE` | `Asia/Manila` | **Not cosmetic.** Cycle end dates and credential expiry are compared against this clock, so a wrong value shifts an authorization boundary — reviews stay editable, or credentials read as current, when they should not. |
| `APP_URL` | `http://localhost` | |
| `APP_DEBUG` | `true` | set `false` in production |

### Database

`DB_CONNECTION=mysql` plus `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`.

### Mail

Two-Factor Authentication Email OTPs, email verification, and opt-in credential alerts
send real emails, so a real transport is required in production. Set
`MAIL_MAILER` to `gmail`, `outlook`, `yahoo`, or `brevo` — host, port and encryption
are preconfigured for each — then supply `MAIL_USERNAME`, `MAIL_PASSWORD` (an **app
password**, not your account password) and `MAIL_FROM_ADDRESS` (must be the same
mailbox as `MAIL_USERNAME`).

`MAIL_MAILER=log` delivers nothing and is for local development only. On PaaS hosts
that block outbound SMTP, use `brevo` (sends over HTTPS) with `BREVO_API_KEY`.

After any change run `php artisan config:clear`, then verify with
`php artisan hims:mail-test you@example.com`. The address is a required argument —
the command has nowhere to send the test message without it.

### AI assistant

Optional — the app works without it, and the assistant reports itself unavailable
rather than failing.

| Variable | Notes |
|---|---|
| `AI_PROVIDER` | `gemini` (default), `openai`, `anthropic`, or `compatible` |
| `GEMINI_API_KEY` / `GEMINI_MODEL` | Google Gemini |
| `OPENAI_API_KEY` / `OPENAI_MODEL` | OpenAI Chat Completions |
| `ANTHROPIC_API_KEY` / `ANTHROPIC_MODEL` | Anthropic Messages API |
| `AI_COMPATIBLE_API_KEY` / `_MODEL` / `_BASE_URL` / `_LABEL` | any OpenAI-compatible host (Groq, DeepSeek, xAI, Mistral, Together, OpenRouter, Ollama) |

A provider only activates once its API key is set.

---

## Contributing

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for the
conventions this codebase follows — most importantly that it uses **raw Query Builder
rather than Eloquent models**, generates UUID primary keys in PHP, and sets
timestamps manually. Please run `vendor/bin/pint` and `composer test` before opening
a pull request.

---

## License

**No license has been chosen for this project yet**, so by default all rights are
reserved and the code carries no permission to use, modify or redistribute it.

If you own this repository and want it to be open source, add a `LICENSE` file and
replace this section — for example:

> Released under the [MIT License](LICENSE).

(The `"license": "MIT"` line in `hims-app/composer.json` is inherited from the stock
Laravel skeleton and is *not* a license grant for this project.)
