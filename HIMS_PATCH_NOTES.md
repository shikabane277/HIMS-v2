# HIMS Patch Notes

Change history for the HIMS Performance & Development Module. Newest first.

Companion documents:
*   [`HIMS_ARCHITECTURE_AND_SECURITY.md`](HIMS_ARCHITECTURE_AND_SECURITY.md) — as-built architecture, schema, security controls
*   [`HIMS_SYSTEM_DOCUMENTATION.md`](HIMS_SYSTEM_DOCUMENTATION.md) — functional spec, routes, implementation status

Conventions: **Added** · **Changed** · **Fixed** · **Removed** · **Security** · **Docs**.
Entries marked 📋 are specified but not implemented.

---

## v2.5.0-beta.1 — 2026-08-03 (pre-release)

Bug-fix release. Four reported defects in the app shell and the password-reset flow, plus working
outbound email and a test-suite fix. No schema changes.

### Fixed — Notifications button did nothing

The topbar bell was `<button class="topbar-btn" title="Notifications">` with no `id`, no event listener
and no panel markup anywhere in the DOM — nothing was broken, nothing had been built. It now opens a
dropdown with a header, a **Mark all read** action that clears the unread dot, and an empty state
("You're all caught up.").

The panel is presentation only. The `notifications` table remains dead schema — no row is ever written
or read, so the list has no server-side source yet. 📋 Wiring it to `notifications` is still open.

### Fixed — Help/FAQ button did nothing

Same root cause, same fix: the icon now opens a dropdown containing five `<details>` entries — running
an AI competency gap analysis, what to do when AI is unavailable, adding a succession candidate,
resetting a forgotten password, and how to contact support.

Both dropdowns share one open/close controller in `layouts/hims.blade.php`: opening one closes the
other, an outside click closes both, and <kbd>Esc</kbd> closes them alongside the existing sidebar and
slide-over panel. `aria-haspopup` / `aria-expanded` are maintained on both triggers. Styles are ~85 new
lines in `public/css/hims.css` using the existing `--hims-*` variables.

### Fixed — AI chatbot always replied in Tagalog

Two layers pushed the model toward Tagalog. `AbstractAiProvider::systemContext()` said only *"You
understand both English and Tagalog/Taglish"* — describing a capability, never setting a default — while
the UI greeted with "Kamusta!", labelled itself `EN / Tagalog` and prompted "Ask in English or
Tagalog…". The model reasonably mirrored those cues.

*   The shared system prompt now reads: reply in English by default, and switch to Tagalog or Taglish
    only when the user clearly writes in it, then match their language. This lives on
    `AbstractAiProvider`, so all four drivers (Gemini, OpenAI, Anthropic, compatible) inherit it.
*   The Tagalog cues are gone from the widget: header `AI Assistant` · `English`, welcome "Hello! I'm
    your HIMS AI assistant…", placeholder "Ask me anything…", launcher `Ask AI Assistant`. Rendered
    Tagalog cue count: 0.

Bilingual support is unchanged — a user who writes in Tagalog still gets Tagalog back.

### Fixed — Forgot password did not work at all

The reset views were fully built; delivery was the problem, in four separate ways.

*   **No mail transport.** `MAIL_MAILER=log` wrote the reset email to `storage/logs/laravel.log` and
    reported success to the user, so the mail silently never arrived.
*   **A transport failure returned HTTP 500.** `Password::sendResetLink()` does not catch transport
    exceptions (`PasswordBroker` calls `sendPasswordResetNotification()` unguarded), so bad SMTP
    credentials crashed an unauthenticated page. `PasswordResetLinkController::store()` now wraps the
    call and returns a friendly, actionable message instead.
*   **The From header was always empty.** `config/mail.php` had
    `env('MAIL_FROM_ADDRESS', 'hello@example.com')`, but `env()` returns `''` — not the default — for a
    key that is present but blank, and `''` is not a missing value. Every send failed with *"An email
    must have a From or Sender header."* Now `env('MAIL_FROM_ADDRESS') ?: (env('MAIL_USERNAME') ?: 'no-reply@hospital.ph')`,
    which also keeps the sender aligned with the authenticated mailbox that Gmail, Outlook and Yahoo require.
*   **An app password containing spaces broke every artisan command.** Google displays app passwords in
    groups of four; pasted verbatim into `.env`, the unquoted whitespace made dotenv fail with *"The
    environment file is invalid!"* — not just mail, the whole CLI. Documented in `.env` and `.env.example`.

Verified end-to-end against a registered account: submit → 302, "We have emailed your password reset
link.", email addressed to the requesting address (not a fixed one), link opens 200, password changes,
old password rejected, new password logs in, dashboard reachable, and **a replayed link is rejected**
(tokens are single-use and consumed on success).

### Added — Consumer webmail presets

`MAIL_MAILER=gmail | outlook | yahoo` now selects host, port and scheme from `config/mail.php`, so only
`MAIL_USERNAME` and `MAIL_PASSWORD` differ between providers. Work/school Outlook tenants override with
`MAIL_OUTLOOK_HOST=smtp.office365.com`. All three reject a normal account password over SMTP and require
an app-specific one; all three require `MAIL_FROM_ADDRESS` to equal `MAIL_USERNAME`.

### Added — `php artisan hims:mail-test {email}`

Diagnostic for the most common silent failure. Prints the resolved mailer, host, port, username,
password-set state and From address; warns when `MAIL_FROM_ADDRESS` does not match `MAIL_USERNAME` on a
consumer preset; fails with an explicit missing-key list before attempting a send (otherwise the
transport reports a misleading "missing From header"); and on failure prints the provider-specific
app-password URLs. Reads the *active* mailer's config, not a hardcoded `smtp` block, so the presets
report their real host. First file in `app/Console/Commands/`.

### Fixed — Test suite: MySQL-only DDL crashed on sqlite

`phpunit.xml` runs on sqlite `:memory:`, but two migrations issued MySQL-only SQL at migrate time and
took the whole suite down before any test ran: the `competency_assessments` gap triggers
(`DECLARE`/`SET` procedural syntax) and `v_recognition_leaderboard` (`CREATE OR REPLACE VIEW` with
`CONCAT()` and `DATE_FORMAT()`). Both are now behind `if (DB::getDriverName() === 'mysql')`.

**22 of 25 tests pass, up from 1.** MySQL behaviour is unchanged — the triggers and the view are still
created there, and `RecognitionController` still reads the view. The 3 remaining failures are stale
Breeze scaffolding, not regressions: two `RegistrationTest` cases expect the self-registration route
that was deliberately removed for an internal hospital system, and `ExampleTest` expects HTTP 200 at
`/` where the app redirects to login.

### Security

*   **Removed a config disclosure on an unauthenticated page.** An earlier development build rendered a
    hint on `/forgot-password` that named `MAIL_MAILER=log` and listed internal remediation steps to
    anyone who could type an email address. The visitor-facing message is now generic; the diagnostic
    goes to `Log::warning` for an operator.
*   **The reset-failure log names the mailer actually in use.** The first version of the catch block read
    `config('mail.mailers.smtp.host')` and logged `127.0.0.1` while the `gmail` preset was active —
    actively misleading whoever debugs it. It now reads `config("mail.mailers.{$mailer}.host")`.
*   **No secret is committed.** `.env` is gitignored (`hims-app/.gitignore:3`); only `.env.example`, with
    empty placeholders, is tracked.

### Docs

Architecture, system documentation and these patch notes updated together — per project rule, the docs
are updated with every system change so they describe the as-built state.

### Known issues

*   **`APP_URL=http://localhost:8000` is baked into the emailed reset link.** Correct on the dev
    machine, dead on any other device. Production must set `APP_URL` to the deployment HTTPS URL.
*   **Railway does not read `.env`.** `MAIL_MAILER`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
    `MAIL_FROM_ADDRESS` and `APP_URL` must be set in the dashboard.
*   **Consumer Gmail is not a production transport** — roughly 500 sends/day, and reset mail from a
    personal address is frequently spam-filed. Use Brevo, SendGrid or Resend on the hospital domain.
*   The notifications dropdown has no data source (see above).

---

## Unreleased — working tree

### Fixed — `'timeout' => null` crashed the reset page on hosts that firewall SMTP

Reported from the Railway deployment: clicking **Forgot password?** returned
`Symfony\Component\ErrorHandler\Error\FatalError` at `SocketStream.php:154`.

`MailManager` forwards the mailer's `timeout` to the socket only `if (isset($config['timeout']))`, and
`isset(null)` is **false** — so `'timeout' => null` was silently dropped and the connection inherited PHP's
`default_socket_timeout` of 60s. Where that exceeds `max_execution_time`, PHP hits its own limit while still
blocked in `stream_socket_client()` and dies with a `FatalError` **before** Symfony's `set_error_handler()` can
raise the `TransportException` — so the try/catch added to `PasswordResetLinkController` in v2.5.0-beta.1, which
exists to prevent exactly this crash page, never ran.

All four mailers now use `'timeout' => (int) (env('MAIL_TIMEOUT') ?: 15)`. Note the `?:` — the same
blank-env-key rule that caused the From-header bug applies here too. Verified against `203.0.113.1`
(RFC 5737 blackhole, so the connect hangs exactly as a firewalled port does): the send now aborts at the
configured timeout as a caught `TransportException`, exit 0, no fatal.

This converts the crash into the intended friendly message. **It does not make email work on Railway below
Pro** — see below.

### Docs — Railway blocks outbound SMTP below the Pro plan

Not a defect in this codebase, but the reason mail fails on the deployed instance, so it is now documented in
`HIMS_ARCHITECTURE_AND_SECURITY.md` §4.8. Railway firewalls ports 25/465/587/2525 on Free, Trial and Hobby
plans, so all four presets here (every one of them port 587) fail identically regardless of credentials. Either
upgrade to Pro **and redeploy** (new egress rules do not apply to a running deployment), or move to an HTTPS API
transport such as Resend — which requires a Composer bridge package and is therefore a code change, not a
configuration change.

---

## Unreleased — earlier working-tree changes

Not yet committed: **31 modified, 26 new, 1 deleted** (per `git status`; new directories such as
`app/Services/Ai/` count once). Covers the AI provider refactor, the RBAC layer, competency gap analysis, auth
hardening, role-aware dashboards, the succession pipeline build-out, two data-integrity migrations, and the
documentation overhaul.

### Added — Succession candidate pipeline (functional)

The pipeline table previously rendered data but almost nothing could be changed: the position filter was inert,
Dev Progress always showed 0%, and there was no way to edit a nomination or manage a development plan.

**Development milestones — full CRUD.** `leadership_development_paths` was read for display but had no write
path, so it was permanently empty.
*   `storeMilestone()` — add a milestone (title, type, target date, description). Types: course, assignment, mentoring, rotation, certification, project.
*   `updateMilestone()` — advance `not_started` → `in_progress` → `completed` via an inline select on the candidate page.
*   `destroyMilestone()` — remove a milestone.
*   `completed_date` is stamped on completion and **cleared** if the status moves back, so progress only ever counts genuinely finished work.
*   Routes sit at the group access level (`admin,hr_manager,supervisor`) — supervisors maintain the plans of people they nominated.

**Edit and withdraw a nomination.**
*   `editCandidate()` / `updateCandidate()` — revise scores, readiness, and mentor. Stamps `reviewed_at`.
*   `withdrawCandidate()` — removes the nomination and its milestones in a transaction. A hard delete: the table has no soft-delete column, and the `(position_id, employee_id)` unique key would otherwise block re-nominating the same person.
*   New view `resources/views/succession/candidates/edit.blade.php`.
*   Both restricted to `admin,hr_manager`; verified a supervisor gets 403.

**Working position filter.** `/succession?position_id={uuid}` narrows the pipeline. Submitted via GET so the view
is shareable and survives a refresh, with a clear-filter button. The value is validated against the loaded
position list, so an unrecognised id is discarded rather than reaching the query.

### Fixed — 9-Box label could contradict the scores beside it

The nomination form had a **manual 9-Box dropdown**, and `storeCandidate()` preferred the submitted value over
the computed one (`$request->nine_box_label ?: $this->nineBoxLabel(...)`). A candidate could therefore be saved
with performance 5 / potential 5 and a label of `under` — and the badge would display the contradiction.

This was more than a UI wart. Both MD files described `nine_box_label` as a MySQL `GENERATED ALWAYS AS ... STORED`
column whose value "cannot be falsified from application code." `SHOW COLUMNS` shows a plain `varchar(30)` with an
empty `Extra` — the generated-column DDL was **never applied**, so nothing enforced the invariant the docs
promised.

*   The manual dropdown is gone. `nineBoxLabel()` now runs on **every** insert and update, and the submitted value is never read.
*   The form shows a **live preview** of the placement instead, updating as scores are typed, so the rater still sees the outcome without being able to override it.
*   Existing rows were checked and backfilled (0 needed correction).
*   Verified by posting `nine_box_label=star` with scores of 1/1 over HTTP: the stored label was `under`.

Also corrected: scores are **1–5**, not the 1–3 the docs specified, and there is no `CHECK` constraint — the range
is enforced by request validation and the form inputs.

### Fixed — Dev Progress percentage was wrong

The pipeline computed progress as `ROUND(AVG(CASE WHEN ldp.status = 'completed' THEN 100 ELSE 0 END))`. Averaging
0-or-100 across joined rows does not give the fraction of completed milestones, and with the `LEFT JOIN`
producing a NULL row for candidates with no milestones the result was unreliable.

Replaced with an explicit ratio:

```sql
COALESCE(ROUND(100.0 * SUM(CASE WHEN ldp.status = 'completed' THEN 1 ELSE 0 END)
               / NULLIF(COUNT(ldp.path_id), 0)), 0)
```

`COUNT(ldp.path_id)` ignores the NULL join row and `NULLIF` guards division by zero. Verified: 2 of 3 milestones
complete → 67%; 0 milestones → 0% with no error.

### Fixed — `orWhere` broke the high-risk stat

`->where('vacancy_risk','high')->orWhere('vacancy_risk','critical')` on the `high_risk` count sat alongside no
other conditions here, but the `orWhere` pattern is fragile — it escapes any surrounding `where` group as soon as
one is added. Replaced with `whereIn(['high','critical'])`.

### Fixed — Bad `AI_PROVIDER` value returned a 500 instead of degrading

`AiManager::make()` threw `InvalidArgumentException` for an unrecognised provider name. Because `AI_PROVIDER` is
hand-written in `.env`, a typo took down **every page that touches AI** — the competency gap analysis returned
`AI provider [agentrouter] is not configured in services.ai.providers.` rather than showing an unavailable notice.
That contradicted the layer's own contract: `ask()` reports problems, it never throws.

*   New `App\Services\Ai\NullAiProvider` — honours the contract by returning a `⚠️` string that names the valid options and the `config:clear` follow-up.
*   `AiManager::provider()` now catches the bad-default case and falls back to it, logging the invalid value and the available names. Requesting a bad provider **by name in code** still throws, since that is a genuine bug rather than user input.

### Fixed — Provider names were case-sensitive

`AI_PROVIDER=Gemini` fell through to "not a known provider" because the lookup was an exact array-key match.
Names are now lowercased and trimmed, and common vendor spellings are mapped:

| Written in `.env` | Resolves to |
|---|---|
| `Gemini` · `GEMINI` · `  gemini  ` · `google` · `googleai` | `GeminiProvider` |
| `Claude` · `claude-ai` | `AnthropicProvider` |
| `ChatGPT` · `gpt` · `open-ai` | `OpenAiProvider` |
| `openai-compatible` · `custom` | `OpenAiProvider` (compatible slot) |

### Changed — Anthropic defaults

Default model is now `claude-opus-5` (was `claude-sonnet-4-6`), with a fallback chain of
`claude-opus-4-8` → `claude-sonnet-5` → `claude-sonnet-4-6` so an unavailable model ID advances instead of
failing. `.env.example` and the driver docblock updated to match.

### Security — Claude Code's proxy credentials leaked into app config

Worth recording as a configuration hazard rather than a code defect.

Laravel's `env()` reads OS environment variables, so a shell that exports `ANTHROPIC_BASE_URL`,
`ANTHROPIC_MODEL`, or `ANTHROPIC_AUTH_TOKEN` silently overrides `config/services.php` defaults for any server
started from it. In this case the inherited `ANTHROPIC_BASE_URL` pointed at a third-party proxy
(`agentrouter.org`), and a token from that environment was pasted into `.env` as `ANTHROPIC_API_KEY`.
Fingerprint comparison confirmed it was byte-identical to the inherited `ANTHROPIC_AUTH_TOKEN`, and it carried no
`sk-ant-` prefix — it was never an Anthropic key.

Had the proxy accepted it, the app would have shipped employee performance data to an unaffiliated third party
while appearing to work normally. It returned `401 unauthorized_client_error` instead, because the service
fingerprints the calling client rather than validating the credential.

*   `.env` reset to `AI_PROVIDER=gemini`, `ANTHROPIC_API_KEY` cleared, and `ANTHROPIC_MODEL` / `ANTHROPIC_BASE_URL` commented to their correct defaults. Timestamped backup written alongside.
*   **Action required:** rotate that proxy token — it reached a file on disk.
*   **Guidance:** always set `ANTHROPIC_BASE_URL` explicitly in `.env` when using the Anthropic driver. An `.env` entry wins over the inherited variable; omitting it does not.

### Added — Provider-agnostic AI layer

Replaces the single-vendor `GeminiService` with an interface-driven layer, making the AI vendor a
configuration choice rather than a code dependency.

*   `App\Contracts\AiProvider` — the contract consumers depend on. One method: `ask(string): string`.
*   `App\Services\Ai\AbstractAiProvider` — shared base: HIMS system prompt, model/fallback resolution,
    temperature, max tokens, timeout, plus `checkBias()`, `generateQuizQuestions()`, and
    `analyzeSentiment()` helpers and a fence-stripping `decodeJson()`.
*   `App\Services\Ai\GeminiProvider` — `POST {base}/models/{model}:generateContent`.
*   `App\Services\Ai\OpenAiProvider` — `POST {base}/chat/completions`. Also serves the `compatible`
    slot under a custom label.
*   `App\Services\Ai\AnthropicProvider` — `POST {base}/v1/messages` with `x-api-key` and
    `anthropic-version` headers.
*   **`App\Services\Ai\AiManager`** — resolves and caches the driver named by `AI_PROVIDER`; normalises the name and degrades to `NullAiProvider` on an unrecognised value.
*   **`App\Services\Ai\NullAiProvider`** — contract-honouring fallback for a misconfigured `AI_PROVIDER`.

**Provider selection** — `AI_PROVIDER` = `gemini` (default) | `openai` | `anthropic` | `compatible`.
The `compatible` slot covers any OpenAI-compatible host (Groq, DeepSeek, xAI, Mistral, Together,
OpenRouter, Ollama) via `AI_COMPATIBLE_LABEL` / `_API_KEY` / `_MODEL` / `_BASE_URL`.

**Failure contract** — `ask()` never throws on an API or config error; it returns a `⚠️`-prefixed
string. `CompetencyGapAnalysisService::parseAiJson()` detects that prefix and degrades to
"AI unavailable" instead of surfacing an exception. Callers must not assume the return value is model
output.

**Model fallback** — each provider takes a primary `*_MODEL` plus a `fallback_models` list; a
404 / model-not-found response advances to the next candidate automatically.

### Added — Role-based access control (RBAC)

Authorisation was previously "logged in and verified" only — any authenticated account could reach every
module. Access is now enforced per route.

*   `App\Http\Middleware\EnsureUserHasRole`, aliased `role` in `bootstrap/app.php`, applied as
    `role:admin,hr_manager,...` throughout `routes/web.php`.
*   **13 Gates** defined in `AppServiceProvider::registerGates()`: `manage-users`,
    `manage-departments`, `manage-employees`, `view-employees`, `manage-performance`,
    `manage-review-cycles`, `manage-competency`, `manage-learning`, `manage-training`,
    `manage-succession`, `view-succession`, `view-org-analytics`, `run-gap-analysis`.
*   Roles are read from `users.role`: **`admin` | `hr_manager` | `supervisor` | `staff`**.
    `App\Models\User` gained `hasRole(...$roles)`, `isAdmin()`, `isHrManager()`, `isSupervisor()`,
    `isStaff()`.
*   The same Gates drive both the `@can` checks that show/hide sidebar navigation and the middleware
    that enforces access, so the menu and the guard cannot drift apart.

**Note on approach:** this uses Gates + middleware, not the Policies-plus-`permissions`-tables design
in the original specification. The `permissions` and `role_permissions` tables remain schema-only.

### Added — Row-level data scoping

`App\Http\Controllers\Controller` (the base class) gained four helpers so every module applies the same
visibility rules rather than each controller inventing its own:

*   `currentEmployeeId()` — the caller's linked `employee_id`, or `null`. Use instead of
    `auth()->user()->employee_id` to avoid crashing `NOT NULL` FK inserts.
*   `canAccessEmployee()` / `authorizeEmployeeAccess()` — admin and HR see everyone; supervisors are
    limited to their own department; everyone else reaches only their own record.
*   `scopeToVisibleEmployees()` — constrains a query to the rows the caller may see.

### Added — Competency gap analysis (Objective 6)

*   `App\Services\CompetencyGapAnalysisService` — computes proficiency gaps and requests AI narrative
    summaries and development recommendations.
*   `App\Http\Controllers\GapAnalysisController` with four routes under `competency.gap.*`:
    organisation overview, department heatmap, employee profile, and a JSON variant.
*   Views: `resources/views/competency/gap-analysis/{index,department,employee}.blade.php`.
*   Gated `role:admin,hr_manager,supervisor`.

### Added — Role-aware dashboards

*   `resources/views/dashboard/partials/{organisation,supervisor,staff}.blade.php` — the dashboard now
    renders content matched to the caller's role instead of one shared view.

### Added — Competency domain management

*   `competency.domains.*` routes plus `resources/views/competency/domains/{create,show}.blade.php`,
    gated `role:admin,hr_manager`.
*   The `domains/{id}` wildcard is registered **last** so it cannot swallow `domains/create`.

### Added — New screens

`performance/reviews/{create,score}`, `performance/cycles/{show,edit}`, `succession/positions/{index,show}`,
`succession/candidates/show`, `learning/courses/show`, `employees/edit`.

### Added — Competency framework seeder

*   `database/seeders/CompetencyFrameworkSeeder.php` — seeds domains, categories, and competencies
    (including JCI standard codes) so gap analysis has a framework to compare against.

### Fixed — Missing `estimated_vacancy_date` column

Migration `2026_08_02_000100_add_estimated_vacancy_date_to_critical_positions.php`.

The succession "new critical position" form posted `estimated_vacancy_date` and
`SuccessionController::storePosition()` wrote it, but the column was never created — every submission
failed. Added as a nullable `date` after `vacancy_risk`.

### Fixed — Login accounts with no linked employee record

Migration `2026_08_02_000110_backfill_user_employee_links_and_roles.php`.

Roughly a dozen write paths route `auth()->user()->employee_id` into `NOT NULL CHAR(36)` FK columns
(`competency_assessments.assessed_by`, `recognition_posts.author_id`, `course_enrollments.employee_id`,
and others). Accounts predating the link had `employee_id = NULL`, so those inserts failed.

The backfill runs three passes and is **additive** — it never deletes or reassigns an existing link:
1.  Link by matching login email to employee email (cheapest and most accurate).
2.  Create employee profiles for any users still unlinked.
3.  Ensure at least one admin exists.

On a fresh install the `users` table is still empty when migrations run, so `DatabaseSeeder` performs the
equivalent linking itself.

### Security — Public self-registration disabled

`routes/auth.php` — the `register` GET/POST routes were removed.

This is an internal hospital HR system. A self-registered account would arrive with no `employee_id` and
the `users.role` database default of `staff` — a stranger holding a login to workforce data. Accounts are
now provisioned by an admin through `/users` (`UserController`, gated `role:admin`), which assigns both
the role and the linked employee profile explicitly.

Breeze's `RegisteredUserController` and `auth/register.blade.php` are deliberately left in place but
unrouted, so the scaffolding stays intact if an invitation flow is ever needed.

### Changed

*   **`config/services.php`** — the standalone `gemini` block became an `ai` block with `default`,
    shared `temperature` / `max_tokens` / `timeout`, and a `providers` map for all four drivers.
*   **`.env.example`** — documented AI provider block: `AI_PROVIDER`, per-provider keys and models, and
    a commented Groq example for the `compatible` slot.
*   **`AppServiceProvider`** — `register()` binds `AiManager` as a singleton and resolves the
    `AiProvider` contract to the configured driver; `boot()` calls `registerGates()`.
*   **`AiController`**, **`CompetencyGapAnalysisService`** — now depend on the `AiProvider` contract
    rather than a concrete Gemini class.
*   **`bootstrap/app.php`** — registers the `role` middleware alias.
*   **Controllers** — `Competency`, `Dashboard`, `Employee`, `Learning`, `Performance`, `Recognition`,
    `Succession`, `Training`, and `User` updated for role scoping and the new screens.
*   **`DatabaseSeeder`** — seeds linked user/employee pairs and roles.
*   **Views** — `layouts/hims` (sidebar `@can` gating), plus updates across dashboard, employees,
    learning, training, competency, and succession templates.

### Removed

*   **`app/Services/GeminiService.php`** — superseded by the provider layer. Verified no remaining
    references before deletion.

### Docs

*   **`HIMS_ARCHITECTURE_AND_SECURITY.md`** rewritten as an as-built reference (+268 lines):
    *   Stack Components table restructured as *Specified → Implemented → Status*.
    *   Architecture diagram redrawn (Gates + `role` middleware, `AiProvider` contract; no Redis/Crypt).
    *   §2 flags the 13 schema-only tables and the PHP-side UUID/timestamp constraints.
    *   §3 separates enforced controls from absent ones. **Corrected:** the doc claimed a 15-minute
        account lock; the code performs 60-second request rate-limiting, which is a different control.
    *   New §4 roadmap, §5 AI provider layer, §6 routing/module map.
*   **`HIMS_SYSTEM_DOCUMENTATION.md`** reconciled against the source (+577 lines):
    *   §3 replaced the six aspirational roles with the four implemented ones; the six-role model kept
        as §3.2 Planned.
    *   §5 rewritten for the multi-provider AI layer; Zapier marked dormant.
    *   §7 security corrected; new §7.5 compliance summary.
    *   **§12 fully replaced** — ~110 lines documented `/api/v1/...` endpoints that do not exist. Now
        lists the real web routes with names and role gating, verified against `php artisan route:list`.
    *   §13 Gantt chart replaced with a delivered-vs-remaining breakdown.
    *   §14 tech stack corrected (Railway, not Vercel); §15 schema summary marks dead tables with 💀.
*   **`CLAUDE.md`** added — repository guidance covering the app layout, the Query-Builder-not-Eloquent
    convention, and the AI layer.

### Known issues

*   **24 of 25 tests fail.** 23 error during `RefreshDatabase` setup: the competency trigger migration
    calls `DB::unprepared('CREATE TRIGGER … DECLARE …')` with no SQLite guard, which is invalid on the
    `:memory:` test connection (`near "DECLARE": syntax error`). The 24th is a scaffold `ExampleTest`
    asserting `/` returns 200 when it redirects (302). Pre-existing, not introduced by these changes.
    Fix by guarding the migration on `DB::getDriverName() === 'mysql'` or pointing `phpunit.xml` at MySQL.
*   **`routes/web.php` imports `App\Http\Controllers\DepartmentController`, which does not exist.**
    Departments are handled by two inline closures. The unused import is harmless but misleading.
*   **13 schema-only tables** — see [`HIMS_SYSTEM_DOCUMENTATION.md`](HIMS_SYSTEM_DOCUMENTATION.md) §15.
*   **Mixed Tailwind versions** — `tailwindcss@3` (core) and `@tailwindcss/vite@4` (plugin) are both
    declared in `package.json`.

### 📋 Not implemented

Field-level encryption (`Crypt`, AES-256-CBC) · audit logging to `audit_trails` · TOTP MFA ·
Redis caching · Zapier dispatch (service written, never called, URLs blank) · LMS quiz engine ·
training pre/post-tests · certificate issuance · performance review approval workflow ·
**succession candidate approval workflow** · in-app notifications · REST API.
See [`HIMS_SYSTEM_DOCUMENTATION.md`](HIMS_SYSTEM_DOCUMENTATION.md) §13.2 for the prioritised list.

### Docs corrections issued this round

Both reference documents claimed `succession_candidates.nine_box_label` was a MySQL `GENERATED` column whose
value could not be falsified from application code. It is a plain `VARCHAR(30)`; the DDL was never applied.
`HIMS_ARCHITECTURE_AND_SECURITY.md` §2.6 and §3.3 and `HIMS_SYSTEM_DOCUMENTATION.md` §6.7, §12.6, and §15 now
describe the PHP-enforced guarantee instead, with the unapplied DDL retained for reference. The score range was
likewise corrected from 1–3 to the 1–5 actually in use.

---

## 2026-07-31

### Added
*   **Full mobile responsive support** (`5f33d2a`) — viewport meta, 7 media queries in
    `public/css/hims.css` (breakpoints at 1024 / 768 / 480px), a hamburger toggle, a sidebar backdrop,
    and JS that auto-closes the drawer after a nav tap below 768px.
*   **Persistent AI assistant and Core HR module expansion** (`28996a6`) — Gemini-backed chat with
    per-user history in `ai_chat_messages`, plus the User, Employee, Competency, Training, Learning,
    Succession, and Performance modules.

### Fixed
*   **HTTPS asset URLs behind the Railway proxy** (`0ab99f9`) — `bootstrap/app.php` now calls
    `trustProxies(at: '*')`, so Laravel honours `X-Forwarded-Proto` and generates `https://` URLs
    instead of mixed-content `http://` assets.

### Removed
*   Legacy static prototype files: `index.html` (`57417d9`), `app.js` (`14eeced`),
    `styles.css` (`7e0d283`) — superseded by the Laravel application.

---

## 2026-07-27

### Added
*   **Initial HIMS Performance & Development Module** (`08143ef`) — the Laravel 13 / PHP 8.3
    application: Breeze session auth, the migration set (54 tables, `v_recognition_leaderboard`, the two
    competency gap triggers, and `GENERATED ALWAYS AS` columns for credential status and the 9-box
    label), the `layouts/hims` shell with `public/css/hims.css`, and the module controllers and views.

---

## 2026-07-06

### Added
*   **Initial commits** (`509720a`, `ec0bea7`) — HIMS Performance & Development subsystem prototype with
    a credentials database.

### Fixed
*   **Prototype migration guard** (`2195e64`) — reinitialise `db.users` when missing from cached
    `localStorage`. Applies to the pre-Laravel static prototype.

---

## Maintenance notes

*   **Rotate `GEMINI_API_KEY`.** Its value entered shell history during development. Rotate it in the
    Google AI Studio console and update `.env`. Never echo the value into a terminal or commit it.
*   **Change the `admin@jj.ph` password.** It was set to `password` on localhost for RBAC testing.
*   **`.env` is not tracked.** Keep `.env.example` in sync when adding configuration keys.

