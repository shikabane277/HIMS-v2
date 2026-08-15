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

The application provides eight primary operational modules. Learning contains the
compliance and oversight tabs rather than exposing a second Compliance module.

- **Performance appraisal** — review cycles, a weighted KPI library, and supervisor
  reviews. Review authority follows the *reporting line* (`employees.supervisor_id`),
  not job title: there is no self-review and no peer review, and even admin/HR must
  use a logged exception path with a typed reason to review outside the chain.
  **People Manager controls who may appear in Reports To. Account role controls what
  they may do inside HIMS.** New assignments require an active People Manager with a
  linked Supervisor, HR Manager, or Admin account; the flag never promotes an account.
- **Competency management** — a seeded framework of domains, categories and
  competencies; per-employee assessments; department-level skills-gap matrix; and
  credential tracking with expiry and reassessment windows.
- **Learning & CPD** — a course catalogue, required-training assignment, learning
  pathways, renewal oversight, accreditation reporting, and CPD logging with a
  separate verification step. Course enrolment is assignment-only; completing a
  course credits verified CPD hours.
- **Training administration** — sessions, venues, registration, attendance check-in
  and post-session feedback.
- **Succession planning** — key positions, named candidates, confidential readiness
  data, vacancy risk, quarterly position reviews, evidence and development milestones.
- **Recognition** — named public or private badges/posts, audience-limited comments and
  reactions, moderation and a public-only leaderboard.
- **Compliance & accreditation inside Learning** — one Learning sidebar entry and
  tab strip joins the catalogue with Required Training, Renewals, CPD, Pathways,
  and Reports. Assign multiple courses or sessions at once to an employee,
  department, role, or the whole hospital and chase completion from modal rosters.
- **AI assistant** — provider-agnostic (Gemini, OpenAI, Anthropic, or any
  OpenAI-compatible host). It answers questions about the app *and* executes actions
  on request, gated by the signed-in user's role and written to an audit trail.
  Destructive actions require an explicit in-chat confirmation. Conversations are
  saved per account, shown in the assistant history panel, and searchable by their owner.

Also included: a Facebook-style in-app notification feed with unread/read state and
deep links, a permission-aware global search that opens the relevant module/tab, and
a schedulable command that emails employees and supervisors about expiring credentials
and due reassessments.

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
| `admin@hospital.ph` | `admin` | everything |
| `l.garcia@hospital.ph` | `hr_manager` | everything, plus HR workflows |
| `m.santos@hospital.ph` | `supervisor` | own record + direct reports |
| `j.reyes@hospital.ph` | `staff` | own record only |
| `r.lim@hospital.ph` | `staff` | own record only |

### Maintenance commands

```bash
# Email employees and supervisors about expiring credentials / due reassessments
php artisan hims:scan-credential-expiry
php artisan hims:scan-credential-expiry --dry-run   # report only, sends nothing

# Verify your mail transport actually works
php artisan hims:mail-test you@example.com
```

### Tests and formatting

```bash
composer test                                    # full suite (341 tests; 21 sqlite skips)
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

"Forgot password?" sends a real email, so a real transport is required. Set
`MAIL_MAILER` to `gmail`, `outlook`, `yahoo`, or `brevo` — host, port and encryption
are preconfigured for each — then supply `MAIL_USERNAME`, `MAIL_PASSWORD` (an **app
password**, not your account password) and `MAIL_FROM_ADDRESS` (must be the same
mailbox as `MAIL_USERNAME`).

`MAIL_MAILER=log` delivers nothing and is for local development only. On PaaS hosts
that block outbound SMTP, use `brevo` (sends over HTTPS) with `BREVO_API_KEY`.

After any change run `php artisan config:clear`, then verify with
`php artisan hims:mail-test`.

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
