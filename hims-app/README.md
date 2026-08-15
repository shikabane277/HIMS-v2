# HIMS v2 Laravel Application

This directory contains the Laravel 13 application for the HIMS Performance &
Development module. The repository-level [README](../README.md) is the primary
setup and usage guide; run application commands from this directory.

Current-state policy and access boundaries are documented in
[HIMS Access and Visibility Rules](../HIMS_ACCESS_AND_VISIBILITY.md). The live
workflow includes named public/private recognition, employee review responses and
acknowledgement, and HR-controlled succession planning.

The shared shell also provides saved, owner-only AI chat history; a recent
read/unread notification feed with per-item and mark-all read actions; and a
permission-aware Global Search (`/search`) that searches only records the signed-in
user may access and links back to the owning module or tab.

## Learning module

Learning and the former Compliance area are one user-facing module:

- one **Learning** sidebar item;
- Overview, Required Training, Renewals, My CPD, Pathways, and Reports tabs;
- assignment-only course enrolment (no course self-enrol action);
- searchable multi-course/session assignment checklists;
- modal course editing and assignment-roster drill-downs;
- canonical `learning.*` routes, with `/compliance/...` aliases retained only for
  backward compatibility.

## Common commands

```bash
composer install
php artisan migrate --seed
composer test
vendor/bin/pint
npm install
npm run build
```

Never run `php artisan test --env=testing`: there is no `.env.testing`, and it can
fall back to the development MySQL database. Plain `composer test` is pinned to
SQLite by `phpunit.xml`.

See [CONTRIBUTING.md](../CONTRIBUTING.md) for the Query Builder, UUID, timestamp,
modal, checklist, and documentation conventions.
