# CLAUDE.md

HIMS v2 — the Performance & Development module of a Hospital Information Management System for a Philippine hospital. Laravel 13 / PHP 8.3, MySQL 8, server-rendered Blade.

**The Laravel app lives in `hims-app/`, not the repo root. Run all commands from there.**

## Commands

```bash
composer setup                     # deps, .env, key, migrate, npm build
composer dev                       # serve + queue + pail + vite
composer test                      # config:clear then artisan test
php artisan test --filter=test_name
php artisan migrate:fresh --seed   # the seeder prints the login it creates
vendor/bin/pint                    # formatter
```

Seeded login: `admin@hospital.ph` / `password`.

## Two rules that always apply

**Never run `php artisan test --env=testing`.** There is no `.env.testing`, so Laravel falls back to `.env` — `DB_DATABASE=hims_v2`, the development database — and `RefreshDatabase` runs `migrate:fresh`. It has destroyed that database once. Plain `php artisan test` is safe (`phpunit.xml` pins sqlite `:memory:`). To use MySQL deliberately, name a scratch database: `DB_CONNECTION=mysql DB_DATABASE=hims_align_check php artisan test`.

**This app barely uses Eloquent.** `App\Models\User` is the only model; everything else is raw Query Builder in controllers. PKs are PHP-generated `Str::uuid()`; `created_at`/`updated_at` are set by hand. Match this style — do not introduce Eloquent models piecemeal.

## Scoped rules

These load automatically on their globs. Read the matching one before editing.

| Editing | Read |
|---|---|
| controllers, migrations | `.claude/rules/query-builder.md` |
| routes, gates, middleware | `.claude/rules/authorization.md` |
| module boundaries, Learning/Compliance | `.claude/rules/module-layout.md` |
| Blade views, `hims.css` | `.claude/rules/blade-ui.md` |
| `app/Services`, `app/Support` | `.claude/rules/services.md` |
| tests, `phpunit.xml` | `.claude/rules/testing.md` |

## Reference

- `docs/incident-history.md` — why a rule exists. Read it before relaxing one.
- `docs/schema-archaeology.md` — dead tables, dropped columns, documented-but-unbuilt features.
- `HIMS_ARCHITECTURE_AND_SECURITY.md`, `HIMS_SYSTEM_DOCUMENTATION.md` and `HIMS_USER_GUIDE.md` are as-built — they describe only what the code does. Keep them that way; anything unbuilt belongs in the patch notes.

Shell commands are proxied through `rtk` by a hook.
