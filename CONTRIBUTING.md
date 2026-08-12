# Contributing to HIMS v2

Thanks for your interest. This codebase has a few deliberate conventions that differ
from a stock Laravel app — following them keeps changes consistent and avoids some
traps that have caused real bugs here.

## Getting set up

See [Installation](README.md#installation) in the README. Work from `hims-app/`.

## Before opening a pull request

```bash
cd hims-app
vendor/bin/pint      # format
composer test        # full suite must stay green
```

Please describe what you changed and why. If your change touches a screen, say which
one so it can be checked in a browser.

## Codebase conventions

### This app deliberately does not use Eloquent

`App\Models\User` is the only model. Every other table is accessed with raw Query
Builder (`DB::table('employees')->join(...)`) directly in controllers. Please match
this style rather than introducing Eloquent models, policies or repositories
piecemeal.

Three consequences to respect:

- **UUID primary keys are generated in PHP.** All domain primary keys are `CHAR(36)`
  populated with `Str::uuid()` at insert time. Nothing auto-generates them — an
  insert that omits the primary key will fail.
- **Timestamps must be set manually** — `'created_at' => now(), 'updated_at' => now()`
  — because Query Builder has no timestamp magic.
- **MySQL-only SQL is normal here** (`CONCAT`, `DATE_FORMAT`, `FIELD`, `GREATEST`,
  `whereMonth`), and migrations install MySQL triggers and a database view.

### Check the migration before you select a column

The test suite runs on SQLite, which will not warn you about a column that does not
exist if no test row ever reaches the query. Two production 500s have been caused
this way. If you join or select a column you have not personally read out of
`database/migrations/`, go and read it.

Likewise, **a `@foreach` whose body no test ever enters is untested code**. When you
add a table to a view, seed at least one row of it in the test.

### Tests

- Run with `composer test`. It is pinned to in-memory SQLite by `phpunit.xml`.
- **Never run `php artisan test --env=testing`.** There is no `.env.testing`, so
  Laravel falls back to `.env` and `RefreshDatabase` on MySQL runs `migrate:fresh`,
  dropping your development database.
- To test against MySQL, name a **scratch** database explicitly and drop it
  afterwards: `DB_CONNECTION=mysql DB_DATABASE=hims_scratch php artisan test`.
- If a new test reaches MySQL-only SQL, gate it: add `@group mysql` to the class
  docblock and `markTestSkipped()` in `setUp()` when the driver is not MySQL. See
  `tests/Feature/EmployeeProgressionTest.php` for the pattern. If only *some* tests
  in a class need MySQL, use a small private helper called from those tests instead
  of gating the whole class.

### Views and styling

- Domain screens extend `layouts/hims.blade.php`. (Breeze's `x-app-layout` survives
  on the profile page only.)
- Styling lives in **`public/css/hims.css`**, which is hand-authored and served
  directly. Tailwind and Vite are installed but the domain UI does not go through the
  build. Add styles to `hims.css`.
- Tables use the single `.hims-table` class. A column that is not left-aligned must
  say so on **both** its `<th>` and its `<td>`s using the shared `.text-center` /
  `.text-end` / `.text-start` classes. Inline `style="text-align:…"` on a table cell
  is not allowed — a contract test asserts this.
- Creating a record happens in a **modal on the list page**, not on a separate GET
  page. Modal markup must be `@push`ed to the layout's `@stack('modals')`, and
  `partials/modal-js.blade.php` is the only modal controller — don't write per-page
  open/close JS.
- Multi-select is a `.hims-checklist` of checkboxes, never `<select multiple>`.

### Timezone

`APP_TIMEZONE` is `Asia/Manila` and this is an authorization setting, not a display
one: review-cycle and credential-expiry checks compare against it. Don't change it,
and don't override it in `phpunit.xml`.

## Documentation

This repository keeps four long-form documents in sync with the code:
`HIMS_ARCHITECTURE_AND_SECURITY.md`, `HIMS_SYSTEM_DOCUMENTATION.md`,
`HIMS_USER_GUIDE.md` and `HIMS_PATCH_NOTES.md`. The first three describe **as-built**
behaviour only — if your change alters what the app does, update them, and put the
change-log entry in the patch notes. `CLAUDE.md` carries the working notes for
AI-assisted development.

## Reporting issues

Please include the page or command involved, what you expected, what happened, and
the relevant entry from `hims-app/storage/logs/laravel.log` if there is one.
