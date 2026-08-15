## v2.5.0-beta.3 — 2026-08-15 (pre-release)

Operational workflow, reporting-line governance, privacy, and AI usability release. This beta turns the existing HIMS modules into a more complete, permission-aware hospital workflow while preserving current employee and review records.

### Added — People Manager governance and safe reporting lines

Employee records now have a separate People Manager setting. It controls who may appear in Reports To; the linked HIMS account role still controls what that person may do inside the application.

New reporting assignments require an active People Manager with a linked Supervisor, HR Manager, or Admin account. Server-side validation rejects ordinary staff, inactive managers, self-reporting, forged selections, and direct or indirect reporting loops.

- Existing managers are backfilled automatically so current reporting relationships survive the migration
- Existing unavailable managers remain visible while editing, with a Setup incomplete warning
- People Manager cannot be removed while direct reports remain
- Manager employment-status changes remain allowed and name the reports needing reassignment
- HR/Admin receive a Manager Setup report for missing accounts, Staff-only access, role/manager mismatches, inactive managers, and employees with no manager
- People Manager never promotes a Staff account or grants additional permissions

### Added — Permission-aware Global Search, notifications, and persistent AI

Global Search now searches an explicit source allow-list and applies the same reporting-line, recognition, succession, account, and AI-session boundaries as the owning modules.

The notification bell is now a recent activity feed with unread counts, per-item acknowledgement, mark-all-read, and permission-safe destinations. AI conversations are saved per account, shown in a visible history panel, and can be reopened from search.

The AI assistant now supports structured, audited application actions with entity resolution, validation, confirmation for destructive changes, session ownership checks, and role-specific subject-matter controls.

### Changed — Learning, compliance, reviews, and recognition

Learning now contains the compliance and oversight workflow under one navigation structure. Required Training supports bundled course/session assignment, completion rosters, renewal cycles, accreditation reporting, and account-coverage checks.

Performance review authority follows the real Reports To relationship. Self and peer reviews are removed from the formal workflow; one named reviewer owns each review. HR/Admin exceptions require a typed reason and an audited basis, including when the recorded manager has no usable HIMS account.

Employees can respond to finished reviews and acknowledge them after the cycle closes. Review status and cycle deadlines are derived consistently, KPI weighting has one canonical implementation, and written supervisor feedback is included in gap-analysis evidence.

Recognition is restored as a named public/private workflow with comments, reactions, moderation, notifications, and audience enforcement. Succession-planning confidential fields are limited to HR/Admin while supervisors see only appropriate direct-report development information.

### Security — identity, privacy, and account protections

- Employee visibility and review authority use `employees.supervisor_id`, not department membership or role alone
- Private recognition is limited to sender, recipient, and HR/Admin moderators
- Succession ratings, readiness, mentor, status, and vacancy risk remain confidential
- AI sessions and search results are owner- and permission-scoped
- Login lockout, credential/phone encryption migrations, selective audit history, and final-admin protection are included
- Social-recognition removal/restoration and other schema changes use forward migrations rather than rewriting deployed history

### Deployment — production migration required

Railway and other production deployments must run the migrations before serving this release:

```bash
php artisan migrate --force
```

Do not run `migrate:fresh`, `db:seed`, or `migrate --seed` against an existing production database. Keep the existing `APP_KEY`; changing it makes encrypted phone and credential data unreadable.

The People Manager migration is MySQL-compatible, rerunnable after a partial DDL commit, and backfills every employee already named as a supervisor.

### Verification

- Full sqlite test suite: **351 tests, 330 passed, 21 skipped, 1,520 assertions**
- People Manager and review-authority focused suites pass
- MySQL migration applied successfully and recorded
- Blade template compilation, Pint formatting, diff checks, and Vite production build pass
- Authenticated employee-create and Manager Setup HTTP smoke checks return 200
