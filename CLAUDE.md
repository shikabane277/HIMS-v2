# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

HIMS v2 — the Performance & Development module of a Hospital Information Management System for a Philippine hospital. Laravel 13 / PHP 8.3, MySQL 8, server-rendered Blade.

The Laravel app lives in `hims-app/`, not the repo root. Run all commands from `hims-app/`.

## Commands

```bash
composer setup                     # install deps, .env, key, migrate, npm build
composer dev                       # serve + queue worker + pail logs + vite (concurrently)
php artisan serve                  # app alone on :8000
npm run dev                        # vite alone

composer test                      # config:clear then artisan test
php artisan test --filter=test_profile_page_is_displayed
php artisan test tests/Feature/Auth/AuthenticationTest.php

php artisan migrate:fresh --seed   # rebuild DB (seeder prints the login it creates)
vendor/bin/pint                    # formatter
```

Seeded login: `admin@hospital.ph` / `password`.

## Database access pattern

**This app barely uses Eloquent.** `App\Models\User` is the only model; everything else is raw Query Builder (`DB::table('employees')->join(...)`) directly in controllers — ~129 call sites across 12 controllers. There are no models, policies, observers, or repositories for the 40+ domain tables. Match this style rather than introducing Eloquent models piecemeal.

Consequences to respect when editing:

- **UUIDs are generated in PHP.** All domain PKs are `CHAR(36)` populated with `Str::uuid()` at insert time. Nothing auto-generates them — an insert that omits the PK will fail.
- **`created_at`/`updated_at` must be set manually** (`'created_at' => now(), 'updated_at' => now()`), since Query Builder has no timestamp magic.
- **MySQL-only SQL is embedded in controllers**: `CONCAT()`, `DATE_FORMAT()`, `FIELD(...)` ordering, `whereMonth()`. Migration `..._000030_create_competency_tables` installs MySQL triggers via `DB::unprepared`, and `..._000070_create_recognition_tables` creates the `v_recognition_leaderboard` view that `RecognitionController` reads from.
- **`phpunit.xml` runs tests on sqlite `:memory:`.** The existing test suite is Breeze auth/profile only and passes, but any new feature test that touches domain tables will hit the MySQL-specific SQL and triggers above. Either target MySQL for such tests or keep them away from raw SQL paths.

## Module layout

Seven domain modules, each a controller + a `routes/web.php` prefix group + a `resources/views/<module>/` directory: performance, competency, learning, training, succession, recognition, plus employees/departments and users. All sit behind `['auth','verified']`, most with an additional `role:` middleware — see "Docs vs. implementation" below.

Departments are handled by **closures inline in `routes/web.php`**, not a controller. `web.php` has a `use App\Http\Controllers\DepartmentController;` import for a class that does not exist — harmless (unused imports don't autoload) but don't be misled by it.

## Views

Two layout systems coexist:

- `layouts/hims.blade.php` — the real app shell, used by ~32 views. Hand-written sidebar/panel JS inline, plus a `window.onerror` hook that POSTs to the `log-error` route.
- Breeze's `x-app-layout` / `layouts/app.blade.php` — only `profile/edit` still uses it.

Styling is **`public/css/hims.css` (742 lines, hand-authored, served directly)**. Tailwind and Vite are installed and configured but `resources/css/app.css` is 3 lines — the domain UI does not go through the build. Add styles to `hims.css` unless deliberately migrating a view.

## Services

- **AI layer** — provider-agnostic. Consumers (`AiController`, `CompetencyGapAnalysisService`) depend on the `App\Contracts\AiProvider` contract (`ask(string): string`), resolved by `App\Services\Ai\AiManager` from the `services.ai` config and bound in `AppServiceProvider::register()`. `AI_PROVIDER` (`gemini`|`openai`|`anthropic`|`compatible`) picks the active driver; **Gemini is the default** so the existing `GEMINI_API_KEY` keeps working. Drivers live in `app/Services/Ai/` (`GeminiProvider`, `OpenAiProvider` — also serves OpenAI-compatible hosts like Groq/DeepSeek/xAI via `AI_COMPATIBLE_*`, `AnthropicProvider`), all raw-HTTP over `Http::` and all extending `AbstractAiProvider`. **Failure contract:** `ask()` never throws for an API/config problem — it returns a `⚠️`-prefixed string, which `CompetencyGapAnalysisService::parseAiJson()` treats as "AI unavailable". Per-provider model + fallback list are configurable via `*_MODEL` env keys. The old concrete `GeminiService` was removed; its three unused helpers (`checkBias`/`generateQuizQuestions`/`analyzeSentiment`, with the `Str::limit` import fixed) now live on `AbstractAiProvider` and remain unreferenced.
- `ZapierService` — fully written, **never called from anywhere**. Webhook URLs are blank in `.env`.

## Docs vs. implementation

`HIMS_ARCHITECTURE_AND_SECURITY.md`, `HIMS_SYSTEM_DOCUMENTATION.md`, and `HIMS_USER_GUIDE.md` were stripped to as-built state — they now describe only what the code does. Keep them that way (see the docs rule in the user's memory); anything unbuilt belongs in the patch/release notes, not in these three.

Still **not implemented in app code**: tamper-proof audit trail observers, TOTP MFA, `Crypt` field encryption, Redis caching, the `notifications` queue, and any REST API (no `routes/api.php`, no Sanctum/Passport). Twelve migrated tables carry zero references in `app/`, `routes/`, or `resources/` — including `permissions`, `role_permissions`, `audit_trails`, `system_users`, and `notifications`.

**Authorization is real but uneven.** `AppServiceProvider::registerGates()` defines 13 Gates, and `EnsureUserHasRole` is aliased as `role` in `bootstrap/app.php` and applied per-route in `web.php` — route-level RBAC works. Record-level scoping also exists, via `Controller::scopeToVisibleEmployees()` and `authorizeEmployeeAccess()` (admin/hr_manager see all, supervisor sees own department, staff sees own record) — but **only `EmployeeController`, `PerformanceController`, and `GapAnalysisController` call them**. Competency, Learning, Training, Succession, Recognition, and Dashboard have no row-level filter. Verify against code before assuming a given control covers a given module.

`system_users` (the docs' account table) is dead; real auth uses Laravel's `users` table, extended with `role` and a nullable `employee_id` FK by migration `..._000095`.

## Tooling

Per the user's global config, shell commands are proxied through `rtk` (token-reducing CLI wrapper) — a hook rewrites them transparently.

`hims-app/check_user.php` is an ad-hoc debug script that boots the framework to look up a hardcoded user ID. Not part of the app.
