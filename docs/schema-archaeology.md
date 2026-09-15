# Schema archaeology

What is in the database that looks alive but is not, and what is in the docs that was never built. Read this before adding a table whose name already appears in a migration, or before wiring up something the older documentation implies already exists.

Live schema, verified: **51 tables + 1 view, 31 applied migration rows.**

---

## The ten dropped legacy placeholder tables

These were created by early migrations, never referenced by application code, and are now **dropped**. They are absent from the live schema.

| Table | Created by |
|---|---|
| `system_users` | `..._000010` |
| `course_modules` | `..._000040` |
| `quiz_questions` | `..._000040` |
| `quiz_attempts` | `..._000040` |
| `training_tests` | `..._000050` |
| `training_test_results` | `..._000050` |
| `succession_reviews` | `..._000060` |
| `permissions` | `..._000080` |
| `role_permissions` | `..._000080` |
| `credential_types` | `..._000130` |

### Why the create code is still there to read

The drop is done by exactly one migration, `2026_08_14_000002_drop_unused_legacy_tables`. **Every owning create-migration still creates them** — the `dropIfExists` calls in those files are their own `down()` methods, not the cleanup.

So a fresh `migrate` creates all ten and then drops them. That is why the schema comes out clean while the create code remains in the repo.

### Do not resurrect them from this list

**Do not resurrect them from this list** — if a feature needs modules or quizzes, that is a new migration.

The only two textual matches left in `app/` are the word "permissions" inside a search keyword string and a comment in `TrainingAssignmentService` noting `course_modules` is dead.

### Four tables that were once on this list and are not

`notifications`, `credential_alert_log`, `course_competencies` and `audit_trails` were all previously documented as unreferenced. All four are **live and queried**. Do not drop them.

---

## `system_users` is not the account table

The older documentation describes `system_users` as the account table. It is dead. Real auth uses Laravel's `users` table, extended with `role` and a nullable `employee_id` FK by migration `..._000095`.

---

## The unread `'zapier'` config block

**There is no Zapier integration.** `ZapierService` was deleted in `3e121d5`; nothing in `app/`, `routes/` or `resources/` mentions it.

All that survives is the unread `'zapier'` block at `config/services.php:102`, whose webhook URLs are blank in `.env` — config with no reader, not a wired-up feature.

**Do not resurrect it from this line.**

---

## Columns dropped by migration, with behaviour attached

- **`..._000170`** dropped `performance_reviews.self_score` and `.peer_score`, deleted the `reviews.contribute` route, and **purged every existing review row**. A review now has exactly one voice, `supervisor_score`. There is no self-review and no peer review; see `.claude/rules/authorization.md`.
- **`review_kpi_scores.weighted_score` was *not* dropped** and must not be. Its *display* was removed from the review screens, but `saveScores()` still writes it and `CompetencyGapAnalysisService` plus `competency/gap-analysis/employee` still read it.

---

## Two columns whose names are commonly guessed wrong

Both shipped as MySQL-only 500s that every sqlite test passed. Full stories in `docs/incident-history.md`.

- **`jci_standard_code` lives on `competency_categories`**, one join above the competency — *not* on `competencies`. The join is needed in the accreditation report, `ComplianceController::competencyRollup()` and `SuccessionController::readinessEvidence()`.
- **The credential display name is `credential_type`**, not `credential_name`. Agreed on by `employees/show`, `competency/credentials/index`, `competency/gap-analysis/employee` and all four dashboard partials.

---

## MySQL objects created outside the table definitions

- Migration `..._000030_create_competency_tables` installs MySQL **triggers** via `DB::unprepared`.
- The recognition restore migration creates the **`v_recognition_leaderboard` view** for reporting. (This is the "+1 view" in the count above.) The recognition wall's core query remains portable, so its feature tests run on sqlite.

Anything added on these paths must gate its tests — see `.claude/rules/testing.md`.

---

## Field-level encryption scope

Limited to exactly two columns, via Laravel `Crypt::encryptString()` / `decryptString()`:

- `employees.phone`
- `employee_credentials.credential_number`

**Every other domain field is database plaintext.** Do not describe the schema as encrypted at rest beyond these two.

---

## Documented but not implemented in app code

None of the following exist. They appear in older architecture documentation; treat any reference to them as aspirational, not as something to integrate with.

- Tamper-proof audit trail observers (hash chaining). `audit_trails` is written selectively by `App\Support\AuditTrail::record()`; coverage is deliberately partial and there is no chaining. See `.claude/rules/authorization.md`.
- TOTP MFA.
- Redis caching.
- The `notifications` queue. (The `notifications` *table* is live; the queue is not.)
- Any REST API — there is no `routes/api.php`, no Sanctum, no Passport.
