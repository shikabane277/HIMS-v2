# HIMS Patch Notes

Change history for the HIMS Performance & Development Module. Newest first.

Companion documents:
*   [`HIMS_ARCHITECTURE_AND_SECURITY.md`](HIMS_ARCHITECTURE_AND_SECURITY.md) — as-built architecture, schema, security controls
*   [`HIMS_SYSTEM_DOCUMENTATION.md`](HIMS_SYSTEM_DOCUMENTATION.md) — functional spec, routes, implementation status

Conventions: **Added** · **Changed** · **Fixed** · **Removed** · **Security** · **Docs**.
Entries marked 📋 are specified but not implemented.

---

## v2.19.0 - 2026-08-15

This release separates organisation-chart responsibility from HIMS permissions and makes new reporting-line assignments safe and auditable.

### Added

*   Added `employees.is_people_manager` with a migration backfill for everyone who already has direct reports.
*   Added eligible-manager filtering and server validation for active status, People Manager status, linked Supervisor/HR Manager/Admin access, self-reporting, and direct or indirect loops.
*   Added People Manager controls to employee create/edit, full manager labels, legacy setup warnings, no-manager warnings, and reassignment warnings after manager status changes.
*   Added the HR/Admin **Manager Setup** report for missing accounts, Staff-only managers, Supervisor accounts not marked as managers, inactive managers with active reports, and active employees with no manager.
*   Added `supervisor_account_unavailable` as a typed, audited HR/Admin review-exception basis.

### Security

*   People Manager never changes `users.role`; permission assignment remains an Admin-only account decision.
*   People Manager cannot be removed while direct reports remain, and forged manager selections are rejected even when the dropdown is bypassed.
*   Existing unavailable managers remain attached only when the assignment is unchanged; they cannot be selected for a new reporting relationship.

### Verification and docs

*   Added `PeopleManagerTest` coverage for filtering, forged requests, loops, backfill preservation, direct-report protection, permission separation, status warnings, setup reporting, legacy assignments, and review exceptions.
*   Updated `README.md`, `HIMS_ACCESS_AND_VISIBILITY.md`, `HIMS_USER_GUIDE.md`, `HIMS_SYSTEM_DOCUMENTATION.md`, `HIMS_ARCHITECTURE_AND_SECURITY.md`, and these patch notes.

---

## v2.18.0 - 2026-08-15

This release restores visible AI conversation history, turns the topbar bell into a recent activity feed, and
adds permission-aware Global Search across the authenticated HIMS shell.

### Added

*   **Permission-aware Global Search.** `GET /search` searches an explicit allow-list of pages and records,
    groups results in the topbar panel, supports keyboard navigation, and redirects to the owning module, row,
    post, goal, or saved AI conversation. Search applies the same staff, reporting-line, recognition privacy,
    succession confidentiality, account-administration, and AI ownership boundaries as the source module.
*   **Per-notification acknowledgement.** `POST /notifications/{notification}/read` marks one notification read
    without touching any other recipient or feed row.

### Changed

*   **AI conversation history is visible by default.** Opening the assistant loads the current user's session
    list and latest transcript. The history pane may be collapsed, but that browser preference is optional and
    never deletes sessions. A Global Search result can reopen one owned session through `?ai_session=<uuid>`.
*   **The notification bell now behaves as a recent activity feed.** It keeps recent read and unread rows,
    shows a numeric unread badge, uses circular type icons and Facebook-style unread emphasis, supports
    per-item and mark-all read actions, and follows server-resolved destinations.
*   **Mobile AI controls remain reachable.** In overlay mode the rail starts below the topbar, so New chat,
    History, and Close are no longer hidden behind the navigation layer.

### Security

*   Notification read operations are scoped by both `notification_id` and the caller's linked `employee_id`.
*   Credential notification deep links focus a row only when the recipient can access that employee record;
    out-of-scope escalation recipients fall back to the credentials module.
*   Search never scans arbitrary tables or confidential columns. Private recognition is participant/moderator
    only, supervisor succession terms are redacted, user accounts are Admin-only, and AI history is owner-only.

### Verification and docs

*   Blade cache passes.
*   Latest verified sqlite baseline: **341 tests, 320 passed, 21 skipped, 1467 assertions**.
*   Authenticated desktop/mobile browser checks returned live search results, kept all panels inside the
    viewport, and reported no runtime errors.
*   Updated `README.md`, `hims-app/README.md`, `HIMS_ACCESS_AND_VISIBILITY.md`, `HIMS_USER_GUIDE.md`,
    `HIMS_SYSTEM_DOCUMENTATION.md`, `HIMS_ARCHITECTURE_AND_SECURITY.md`, and `CLAUDE.md`.
*   No migration is required for this release.

---

## v2.17.0 - 2026-08-15

The current release closes the recognition privacy boundary, gives employees a controlled response to
finished performance reviews, and makes succession planning usable without exposing HR-only talent decisions
to supervisors.

### Added

*   **Named Public/Private recognition.** Recognition remains attributable to sender and recipient in both
    modes. Public posts are eligible for the approved wall; private posts are restricted to the sender,
    recipient, and HR/Admin moderators. Comments and reactions inherit the same audience. Self-recognition
    remains refused, and moderation, notifications, and audit events continue to apply.
*   **Employee review response and acknowledgement.** After a reviewer marks a review finished, the employee
    may submit `employee_response` without changing any score. Once the cycle closes, the employee may
    acknowledge the review; the acknowledgement timestamp and response timestamp are separate, and a
    completed acknowledgement locks the response.
*   **Succession governance details.** Supervisors are limited to direct-report candidates and milestones;
    performance/potential ratings, 9-box labels, readiness, mentor, status, and vacancy risk are confidential
    to HR/Admin. Critical-position quarterly review date/status are stored, and the dashboard computes alerts
    for high/critical-risk roles with no `ready_now` successor.

### Security and audit

*   Private-recognition visibility is enforced again when comments and reactions are written, not only when
    the wall is rendered.
*   Review responses, acknowledgements, succession position/candidate/milestone changes, recognition
    moderation, and other implemented mutation paths write selective `audit_trails` entries. The
    `/audit/history` endpoint is restricted to Admin and HR Manager; audit
    history is not a comprehensive read log and has no hash chain.

### Migrations

*   `2026_08_15_000010_add_employee_response_to_performance_reviews.php`
*   `2026_08_15_000020_add_quarterly_review_tracking_to_critical_positions.php`

### Verification and docs

*   Latest verified sqlite baseline: **330 tests, 309 passed, 21 skipped, 1404 assertions**.
*   Updated `README.md`, `hims-app/README.md`, `CLAUDE.md`, `HIMS_ACCESS_AND_VISIBILITY.md`,
    `HIMS_USER_GUIDE.md`, `HIMS_SYSTEM_DOCUMENTATION.md`, and `HIMS_ARCHITECTURE_AND_SECURITY.md`.
*   Historical release notes remain unchanged; they describe the behavior of the releases in which they
    were written.

---

## v2.16.0 - 2026-08-15

Social Recognition has been restored as a bounded first version after the August 14 removal migration. The
restore is forward-only: a new migration recreates the subsystem for databases that already ran the removal,
rather than rewriting migration history.

### Added

*   **Recognition wall, Sent, and Received views** on one modal-first page. Approved comments render in each
    thread; reactions toggle; linked employees can recognize any other active employee.
*   **Four seeded hospital-value badges**: Compassion (Kalinga), Teamwork (Bayanihan), Innovation (Diskarte),
    and Clinical Excellence. HR/Admin can add more from the wall.
*   **In-app notifications** for recognition received, reactions, comments, and moderation outcomes.
*   **HR/Admin moderation** for posts and comments (`approved`, `flagged`, `removed`). Moderation and badge
    creation write `audit_trails` rows.

### Security

*   **Self-recognition is refused** in the controller and the current employee is excluded from the picker.
*   **Supervisor recognition is identity-derived** from `employees.supervisor_id`; an author cannot self-label
    a post as management recognition.
*   **Formal reviews stay separate.** Recognition writes always leave `link_to_review_id` null and never update
    `performance_reviews` or `review_kpi_scores`.

### Verification

*   `tests/Feature/RecognitionTest.php`: **11 passed, 54 assertions**.
*   Full sqlite suite: **315 tests, 294 passed, 21 skipped, 1,327 assertions**.
*   Recognition route list: **7 routes**. Blade cache, targeted Pint, and Vite production build all pass.

---

## v2.15.0 — 2026-08-13

The separate Compliance navigation has been folded into **Learning**, and the unfinished course/assignment
workflow has been completed around that merged information architecture. This is a navigation and workflow
change rather than a schema change: the existing Learning and compliance services/tables remain, while their
canonical routes and views now present one coherent module.

### Changed

*   **One Learning sidebar entry and one tab strip.** Overview, Required Training, Renewals, My CPD, Pathways
    and Reports now share the `learning.*` route family and `resources/views/learning/`. `ComplianceController`
    remains the handler for institutional questions, but there is no separate Compliance sidebar item.
*   **Course self-enrolment was removed.** The catalogue has no Enrol button and the AI action catalogue no
    longer exposes `learning.enroll`. New course enrolments are created through Required Training and carry an
    `assignment_id`; null remains possible only for legacy rows. Session self-registration is unchanged.
*   **The Course Catalogue action is Edit.** Admins/HR open one shared course edit modal that updates course
    fields and competency tags. The course title remains the unobtrusive link to course details; the former
    View/Enroll action pair is gone.
*   **Assign Training accepts a bundle.** Separate searchable checkbox lists allow any number of active courses
    and upcoming sessions to be selected together. The form posts type-prefixed `subjects[]` values and
    `TrainingAssignmentService::assignMany()` creates one separately measured assignment per subject inside one
    transaction.
*   **Outstanding work stays in context.** The outstanding/overdue badge opens an assignment roster modal
    already filtered to unfinished people; the Roster button opens everyone. Mark complete/reopen and session
    Attendance actions are available from the modal. The full roster route remains for deep links.

### Fixed

*   **“Assignments Still Outstanding” now counts assignments that actually have unfinished people**, rather
    than counting historical assignment rows forever.
*   **Old Compliance links do not break.** A compatibility route group retains the former `compliance.*` names
    and `/compliance/...` URLs, pointing them at the merged Learning handlers. New links use `learning.*`.
*   **Old single-subject assignment requests remain accepted.** `storeAssignment()` converts the former
    `subject_type` + `subject_id` payload into the new `subjects[]` shape before validation.
*   **Assignment creation is audited.** `TrainingAssignmentService::assign()` writes one `assign_training`
    audit row per selected subject, so audit coverage now has five caller locations rather than four.

### Access note

Required Training is route-gated to Admin, HR Manager and Supervisor. A Supervisor's single-employee target is
checked against their own record/direct reports and roster results are filtered the same way. Department, role
and all-active targets currently expand across the full selected active population; this broader behavior is
documented explicitly rather than being described as direct-report-only.

### Verification

*   `tests/Feature/ComplianceTest.php`: **27 passed, 99 assertions**.
*   Related route/layout contracts: **11 passed, 218 assertions**.
*   `php artisan route:list` confirms 22 canonical `/learning` routes and 9 backward-compatible
    `/compliance` aliases.

### Docs

Updated `README.md`, `CLAUDE.md`, `HIMS_ARCHITECTURE_AND_SECURITY.md`,
`HIMS_SYSTEM_DOCUMENTATION.md`, and `HIMS_USER_GUIDE.md` to the as-built merged workflow. Historical release
notes remain unchanged because they describe earlier releases at the time they shipped.

---

## v2.14.0 — 2026-08-12

**The AI gap analysis was reading the performance reviews and throwing away the only part of them that
explains anything.** Asked why the analysis "doesn't read the performance review", the answer turned
out to be that it does — `performanceSignal()` selects three cycles of reviews including
`strengths_text`, `improvements_text` and every per-KPI `comments` note — and then drops all of it
before the prompt heredoc. The model was being handed three numbers and a cycle name while being asked
to report `evidence`, `root_causes` and `strengths_to_leverage`. A score says *that* something is
wrong; the sentence the supervisor typed beside it is the only thing on record that says *why*.

The employee report now sends every written comment across those cycles, summarises them, and prints
them verbatim underneath so the summary can be checked. No schema change and no change to any stored
value — the columns were always there and always populated.

### Added

*   **`App\Support\ReviewFeedback`** ([ReviewFeedback.php](hims-app/app/Support/ReviewFeedback.php)) —
    the one definition of "what has been written about this employee", in the same shape as
    `KpiWeighting` / `CycleStatus` / `ReviewStatus` / `CredentialStatus`: `final class`, static
    methods, no database access and no Carbon. `group()` folds review rows and per-KPI comment rows
    into one entry per cycle; `count()` counts each piece of typed text as one; `promptLines()` renders
    the same set for the prompt.
*   **Every per-KPI comment across the three cycles reaches the prompt**, via a new query in
    `performanceSignal()` — not just the comments on weak KPIs. Praise on a strong KPI is evidence too,
    and the analysis is asked to report strengths as well as gaps. It selects **`supervisor_score`**,
    not `weighted_score`: identical values in the data today, but only one of them means what the
    prompt line claims.
*   **A `feedback_summary` key in the requested JSON** — `overview`, `recurring_themes` (each tagged
    `improving|persistent|new|resolved`, so a point raised last year that was never acted on is
    visible as such), `praised`, `concerns`. Rendered as **Summary of Supervisor Feedback** inside the
    AI Analysis card. The model is instructed to build it *only* from the written-feedback section and
    to return `null` when nothing is recorded, rather than restating a rating as though somebody had
    written it. The response cap rose 500 → 700 words to make room.
*   **A `Written Feedback on Record` card** on `competency/gap-analysis/employee.blade.php`,
    reproducing the comments word for word, grouped by cycle, with the strengths / areas-to-improve
    boxes and each KPI note. Deterministic — it is on the page whether or not the AI answered, which is
    what keeps the service's "still tells the truth with no API key" promise true of the new feature
    too. Built from divs rather than a table, so it adds no `.hims-table` surface for
    `Unit\TableAlignmentContractTest` to police.
*   **`summary.written_feedback`** — the comment count, which also reaches
    `GapAnalysisController::employeeJson()` for free, since that endpoint returns `summary` whole.
*   **`ReviewFeedback::formatScore()` is public**, and the page calls it. `number_format($x, 2).'/5'`
    had four copies across the helper, the view and the prompt builder, already spelling the null case
    two different ways (`—` and `not scored`) — so the page could drift from the prompt in exactly the
    way the class exists to prevent. One definition now serves both.
*   **`Unit\ReviewFeedbackTest`** — 14 tests, no database: grouping by review, caller-order
    preservation, blank/whitespace text dropped, counting, an unscored KPI reading *not scored* rather
    than `0.00/5`, both empty-input sentences, truncation at `MAX_COMMENT_CHARS`, and newline collapse.
*   **`Feature\GapAnalysisFeedbackTest`** — 9 tests, `@group mysql` with a `setUp()` skip, asserting
    against the prompt string itself. A column that is fetched and dropped is invisible to any test
    that only checks the query, which is precisely why the original defect survived a green suite.

### Changed

*   **A review cycle with no written comment is kept, not filtered out**, in both the prompt and the
    card, and says so explicitly. A cycle where somebody was scored but nobody wrote anything down is
    itself a finding; dropping it would let the page imply feedback was given every time. For the same
    reason `promptLines()` returns a stated absence rather than an empty section — an empty block in a
    prompt reads as an omission the model is free to fill in.
*   **Comments are truncated to 300 characters in the prompt only.** `review_kpi_scores.comments` is a
    `TEXT` column validated at 1000 characters and a review can carry a dozen KPIs, so three cycles
    unbounded is a five-figure paste on every page load. The screen shows the whole sentence — it has
    room, and the reader is entitled to it.

### Removed

A cleanup pass over the new code removed three things it did not need. All were found by review, not
by a failure; the suite is byte-identical before and after (**299 passed / 1361 assertions** on MySQL).

*   **Three dead keys from `performanceSignal()`'s return** — `reviews`, `strengths_text` and
    `improvements_text`, verified to have zero readers anywhere in `app/`, `resources/`, `routes/` or
    `tests/`. `reviews` was a live three-row collection carrying two `TEXT` columns, handed to every
    render and read by nobody. Worse, `strengths_text`/`improvements_text` were a **second,
    latest-cycle-only definition** of text that `feedback[0]` already carries per cycle — the precise
    drift `ReviewFeedback` exists to prevent. The two columns stay in the `select()`, because
    `ReviewFeedback::group()` reads them off the rows; the docblock now says so, since dropping them
    there would empty the narrative silently.
*   **The duplicate comment render, and the column behind it.** The comment had been printed under each
    KPI in the **Weakest KPIs** card as well as in *Written Feedback on Record* — but weak KPIs come
    from the latest review and the feedback card covers all three cycles including it, so the weak-KPI
    comment is a strict subset and the same sentence printed twice on one page under two different
    cleaning rules. The card is a score list; the feedback card is the text of record. `rks.comments`
    was dropped from the weak-KPI `select()` at the same time, because a selected column nothing reads
    is how the last two stale-column 500s started.
*   **A hand-rolled `AiProvider` double** in `GapAnalysisFeedbackTest`, replaced by the production
    `NullAiProvider` — which already returns the `⚠️`-prefixed string `parseAiJson()` keys on.

Also collapsed: `count()` from a ten-line loop to `array_sum(array_column(...))`; the duplicated
`$feedbackCount` local, now read from `$summary`; the byte-identical *Praised For* / *Concerns Raised*
markup, now one `@foreach` over a label map.

### Security

*   **Supervisors' verbatim written comments about a named employee are now sent to the configured
    third-party AI provider** on every employee gap-analysis page load. The scores already were; free
    text is a materially different disclosure, because it can name patients, incidents or colleagues.
    It is bounded by the existing `admin,hr_manager,supervisor` gate on `competency.gap.*`, by
    `Controller::authorizeEmployeeAccess()`, by the three-cycle window and by the 300-character cap —
    but it is **not eliminated, and there is no consent or redaction step**. Documented in
    `HIMS_ARCHITECTURE_AND_SECURITY.md` §2.2.3 with the mitigation: leaving the active provider's API
    key blank degrades the narrative to the `⚠️` path and leaves both the deterministic analysis and
    the on-page feedback list fully intact.

### Docs

*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — new §2.2.3 "The written half of a review is the half that
    states a cause": the three free-text fields, all three `ReviewFeedback` methods, the two
    load-bearing properties, and the privacy consequence. The AI layer's **Consumers** row now says the
    employee prompt carries verbatim feedback.
*   `HIMS_SYSTEM_DOCUMENTATION.md` §12.3 — what the employee report reads, the two cards, and why
    comments are scored off `supervisor_score`. **Corrected a false claim** in §5: "The AI does not
    touch performance reviews" was true when written and is not any more; it now states that the AI
    layer is read-only over performance data — it quotes review comments but writes no review, score or
    comment.
*   `HIMS_USER_GUIDE.md` §6 — a new subsection on what the individual report reads, the difference
    between the AI summary and the verbatim card, a note that a silent cycle is still listed, and a
    caution to keep patient-identifying detail out of review comments because they are sent to the AI
    provider.
*   `CLAUDE.md` — `ReviewFeedback` in the services list with its three rules;
    `Feature\GapAnalysisFeedbackTest` added as the **second** MySQL-gated class (`roleRequirements()`
    calls `GREATEST()`); test baseline updated to **299 tests; sqlite 278 passed / 21 skipped / 1293
    assertions, MySQL 299 passed / 1361 assertions**.

### Not done

Four defects found in the same diagnosis were left alone deliberately, because each changes what a
number *means* rather than fixing a drop, and none was part of what was asked for:

*   **`performanceSignal()` has no status or cycle filter, so a `draft` review can win `$latest`.** Its
    `overall_score` is null, and the prompt then prints the hardcoded string `'no completed review'` —
    doubly misleading, since `performance_reviews.status` never holds `completed` at all (it is derived
    from `review_cycles.end_date`). The new feedback block does label a draft cycle `[still a draft]`,
    so the model is no longer wholly unaware; the score line is still wrong.
*   **`latest_cycle` renders as `(cycle: )`** when there is no review — an empty parenthetical rather
    than a statement.
*   **The weak-KPI filter still thresholds `rks.weighted_score`** at 3.5. That column is a verbatim
    copy of `supervisor_score`, so the filter works by accident; the new comment query deliberately
    does not depend on it.
*   **The department-level analysis reads no performance data at all** — no reviews, no scores, no
    comments. Only the employee report was in scope here.

Also unbuilt: **neither card appears on `employees/progression.blade.php`**, the employee's own view of
their development. Whether a person should read their own supervisor's verbatim comments there is a
hospital-policy question, not a UI one.

---

## v2.13.0 — 2026-08-11

**Every table header in the app sat centred above left-aligned data.** Reported from the dashboard's
*Largest Skill Gaps* card, but that card was not the bug — `.hims-table th` declared no `text-align`,
so all 59 tables across 33 views (302 header cells) inherited the user-agent default, which is
`center` for `th` and `left` for `td`. Bootstrap's reboot papers over that mismatch with
`th { text-align: inherit }`; HIMS loads the bootstrap-icons **font** and no Bootstrap CSS, so nothing
ever supplied the declaration. One line of CSS fixes all 59.

Presentation only — no schema change, no query change, no route change, no behaviour change.

### Fixed

*   **`.hims-table th` now declares `text-align: left`** ([hims.css](hims-app/public/css/hims.css)),
    with a comment recording that no reboot layer exists to supply it. A header labels the column
    under it, so it starts where that column starts.
*   **Eleven views had inline `style="text-align:…"` on table cells** — the same intent in a second
    vocabulary, which is why the columns that *were* paired correctly were indistinguishable from the
    ones that were not. All converted to the shared `.text-center` / `.text-end` utilities:
    `competency/credentials/index`, `competency/gap-analysis/index`, `compliance/assignments/show`,
    `employees/index`, `employees/show`, `employees/progression`, `learning/courses/show`,
    `learning/cpd/index`, `performance/reviews/index`, `performance/show`,
    `training/sessions/show`. **Zero inline `text-align` remains on any `<th>` or `<td>` in the app.**
*   **Two empty trailing action headers** (`compliance/assignments/show`, `learning/courses/show`) now
    carry `class="text-end"` to match the right-aligned buttons beneath them.
*   `employees/progression.blade.php` keeps its three centred proficiency columns — Required, Current,
    Gap hold single digits in fixed-width columns — but the headers are now centred *with* them.

### Added

*   **`Unit\TableAlignmentContractTest`** — 5 tests, 76 assertions, no database and no rendering. It
    pins the CSS declaration, asserts no later rule (the responsive blocks already restyle
    `.hims-table th` padding) re-centres it, bans inline `text-align` on table cells, and checks that
    the *set* of alignment classes used on headers in a file matches the set used on its data cells.
    Two floors — ≥30 views scanned, ≥40 tables and ≥250 `<th>` found — so a regex that stops matching
    fails loudly instead of passing with nothing to check.
*   **A headed-Chrome verification harness** (`scratchpad/pw/verify-table-alignment.mjs`, outside the
    repo) doing what the static test deliberately will not: per-**column** comparison of computed
    `text-align` on each `thead` cell against its `tbody` cells, by `cellIndex`. 34 pages, 46 rendered
    tables, 242 header cells — **152 paired, 90 empty columns, 0 mismatches, 0 HTTP 500s, 0 JS
    errors.** The only three centred headers are progression's, as designed.

### Docs

*   `CLAUDE.md` — new **Views → Tables** section: the two contract rules, why the pairing check is
    per-file rather than per-column-index, the `start`/`end` normalisation trap in the browser check,
    and why numerics were deliberately *not* right-aligned app-wide.
*   `HIMS_ARCHITECTURE_AND_SECURITY.md` §1 and the Frontend row — loading no Bootstrap CSS means no
    reboot layer, so **nothing in `hims.css` may rely on a normalise layer being present**.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — the Styling row and §14 Technology Stack carry the same
    consequence and the corrected line count (1243 → **1251**).
*   `HIMS_USER_GUIDE.md` §1 — a short **Reading a Table** note: a heading sits over the column it
    names, and My Development's proficiency digits are the one centred exception.
*   Test baseline: **276 tests; sqlite 264 passed / 12 skipped / 1254 assertions, MySQL 276 passed /
    1297 assertions.** Ten tests now touch no database.

### Process

*   **`php artisan test --env=testing` destroyed the `hims_v2` development database** during this
    change and it was recovered in full. There is no `.env.testing`, so Laravel fell back to `.env`
    (`DB_DATABASE=hims_v2`) and `RefreshDatabase` — which runs `migrate:fresh` on any non-sqlite
    connection — dropped and recreated all 61 tables. Recovery was a point-in-time binlog replay
    (`mysqlbinlog --database=hims_v2 --stop-datetime` over binlog.000004–000011, replayed through
    `mysql --binary-mode`): **351 rows across 19 tables, 1 view, 2 triggers, all five logins**, with
    nothing lost — the app performed no writes between the last event and the wipe. The prohibition
    and the correct scratch-database invocation are now written into `CLAUDE.md` beside the test
    baseline.

### Not done

*   **`.table-responsive` exists in `hims.css` and is referenced by no view.** A table wider than its
    card does not scroll; `white-space: nowrap` on `th` means it can overflow. Out of scope for an
    alignment fix, and left alone rather than half-applied.
*   **The 1–5 scale is still unanchored and still `step="0.01"`** — carried forward from v2.12.0 for
    the same reason: it is a statement about hospital rating policy, not UI.

---

## v2.12.0 — 2026-08-11

**The review screens now say what a KPI's weight actually buys, and stop printing a number that
claimed to be something it wasn't.** A supervisor reading a review saw four things it could not act
on: a **Weighted** column that printed the plain rating under a heading claiming a weighting it never
applied, a bare `weight 0.60` that means nothing outside the formula, two roll-ups named
"Supervisor Rating" and "Final Score" that read as synonyms for one figure, and no indication that a
blank box removes a KPI from the score rather than scoring it zero. All four are addressed, and the
arithmetic behind the new percentage now has exactly one implementation, shared with the write that
stores the score.

Presentation only — no schema change, no change to any stored value, and
`review_kpi_scores.weighted_score` is still written by `saveScores()` and still read by
`CompetencyGapAnalysisService` and the gap-analysis employee view.

### Added

*   **`App\Support\KpiWeighting`** ([KpiWeighting.php](hims-app/app/Support/KpiWeighting.php)) — the
    one definition of how a weight becomes influence over a review's score, in the same shape as
    `CycleStatus` / `ReviewStatus` / `CredentialStatus`: `final class`, static methods, no database
    access and no Carbon. `effectiveWeight()` owns the `weight ?: 1` rule, `shares()` returns each
    rated KPI's exact share of the final score, `displayShares()` returns the same as whole
    percentages totalling exactly 100, `isRated()` and `ratedCount()` answer what the reviewer has
    filled in. `PerformanceController::recalculateReviewTotals()` takes its coefficient from
    `effectiveWeight()`, so **a KPI cannot be shown as deciding 13% of a score that was calculated as
    though it decided something else.**
*   **Each KPI states its share of the final score** on both the review page ("9%") and the scoring
    form ("counts 9% of the final score"), replacing the raw weight. A KPI with no rating reads
    *"not counted until you rate it"* rather than 0% — a zero would read as "counted, and worthless".
*   **A rated count on both screens** ("5 of 7 rated"), and on the scoring form an amber bar whenever
    anything is unrated, stating that an unrated KPI is left out of the final score altogether rather
    than counted as a zero, and that the rated KPIs share out its influence.
*   **`Unit\KpiWeightingTest`** — 12 tests, no database, including one asserting the shares reproduce
    a stored `overall_score` exactly, and one pinning the 100% total. Three MySQL-gated tests in
    `Feature\ReviewAuthorityTest` assert what the two screens render.

### Changed

*   **The two roll-ups are named by what each averages.** "Supervisor Rating" → **"Average of KPI
    ratings"**; "Final Score" gains **"weighted by KPI importance"**; and a sentence on the page says
    why they differ and that the Final Score is the figure the rest of HIMS quotes.
*   `PerformanceController::show()` no longer selects `rks.weighted_score` — nothing read it once the
    column left the table, and a selected column nothing reads is how the last two stale-column 500s
    started.

### Fixed

*   **The displayed shares added up to 102%.** Found in the browser, not in a test: rounding each
    share independently gave `16 + 14 + 16 + 13 + 13 + 16 + 14` on a seven-KPI review, and both
    seeded reviews overshot. A column of percentages is read down and added up, so the integers are
    now apportioned by **largest remainder** — floor everything, then hand the leftover points to the
    largest fractional parts, ties breaking on the order the screen lists the rows so the same review
    always shows the same figures. This is the defect the class exists to prevent, reproduced by the
    class itself on its first pass; `displayShares()` is what a screen must call, never `shares()`.

### Removed

*   **The `Weighted` column** from `performance/reviews/score.blade.php` and `performance/show.blade.php`.
    It printed `supervisor_score` verbatim — identical on all 24 live KPI rows — under a heading
    claiming a weighting it never applied. Both empty-state rows dropped from `colspan="4"` to `"3"`.
    The 3.5 threshold chip went with it; it lived inside the deleted cell.
*   **The bare `weight 0.60` label** under each KPI on the scoring form, replaced by the share.

### Docs

*   `HIMS_ARCHITECTURE_AND_SECURITY.md` §2.2.2 — the weight-as-share rule, all five methods, and the
    three load-bearing properties; §2.2's `weighted_score` note records that the column stays but its
    display went.
*   `HIMS_SYSTEM_DOCUMENTATION.md` §4.A — "A KPI's weight, shown as what it actually buys"; the
    performance feature list and the sample workflow's scoring step.
*   `HIMS_USER_GUIDE.md` — the two-scores table, what a percentage under a KPI means, and a callout
    that a blank score is an exclusion rather than a zero.
*   `CLAUDE.md` — `KpiWeighting` in the services list, the roll-up labels and the `weighted_score`
    split under performance authority, and the test baseline (**271 tests; sqlite 259 passed / 12
    skipped / 1178 assertions, MySQL 271 passed / 1221 assertions**).

### Not done

*   **The 1–5 scale is still unanchored and still `step="0.01"`.** Word labels against each point,
    and a coarser step, are statements about hospital rating policy rather than UI, so they wait on
    the hospital's own wording.

---

## v2.11.0 — 2026-08-11

**A review cycle now closes itself when its end date passes, and the app clock is the hospital's
clock.** Two defects were reported together and turned out to share one root: a review whose cycle
had ended could still be scored, and the cycle itself still displayed **Active** days after it
finished. The first was a timezone bug; the second was a status column nothing ever updated. Both
are closed, and the fix for each is the same shape — *the date is the rule, and there is only one
copy of it*.

This release also records the review-model collapse (self and peer scoring removed) and four
smaller fixes that shipped alongside it and had not yet been written down.

### Fixed

*   **A review stayed editable for the first eight hours of the day after its cycle ended.**
    `ReviewStatus`, `CycleStatus` and `CredentialStatus` all decide by asking whether a date has
    passed, against `now()->toDateString()`. Laravel ships `'timezone' => 'UTC'`, the hospital is
    in **Asia/Manila (UTC+8)**, so between midnight and 8am Philippine time `now()` was still
    yesterday — and a cycle that ended yesterday read as open. `config/app.php` now defaults to
    `env('APP_TIMEZONE', 'Asia/Manila')`
    ([config/app.php:75](hims-app/config/app.php#L75)), with `APP_TIMEZONE=Asia/Manila` added to
    `.env` and `.env.example`. **This is an authorization setting, not a display one** — the
    comment block above it says so, because the next person to see a timezone line will assume it
    is cosmetic. The same change fixes credential expiry, which had the identical eight-hour
    window.
*   **A review cycle displayed `Active` forever.** `review_cycles.status` is a hand-set varchar and
    nothing in the app ever transitions it, so a cycle sat on whatever it was created with. The
    badge and the review lock therefore disagreed — reviews were frozen while the screen said the
    cycle was running, **and the screen is what people believed**. Cycle status is now derived from
    `end_date` by the new `App\Support\CycleStatus`, everywhere a cycle is displayed or counted:
    `performance/index`, `performance/cycles/show`, the Performance active-cycle KPI, and the
    dashboard's review-progress chart.
*   **A review could be opened inside a cycle that had already ended** — creating a review born
    frozen, editable by nobody. `storeReview()` now refuses it
    ([PerformanceController.php:453](hims-app/app/Http/Controllers/PerformanceController.php#L453)),
    the **New Review** cycle dropdown lists only cycles that have not ended, and the cycle page
    hides **Add Review** once it closes.
*   **Alerts that explained a refusal auto-dismissed while the user was still reading them.** The
    layout's dismiss timer removed every `.hims-alert` on the page. Auto-dismiss is now **opt-in**
    via a `data-auto-dismiss` marker: flash confirmations carry it, explanatory banners
    (a completed review, an exception review, a validation summary) do not.
*   **A long alert stretched into a single unwrapped line** because `.hims-alert` laid its contents
    out as a flex row. It flows as prose now, so a full sentence wraps inside the banner.
*   **The review detail page showed a blank Weight column and an empty Peer Feedback card.** It
    read `weight_pct` and `self_score` off `review_kpi_scores`, where neither column exists —
    the column is `weight`, and the self/peer columns were dropped by `..._000170`. The KPI table
    is now KPI · Weight · Rating · Weighted, and the peer card is gone. *(The Weight and Weighted
    columns were themselves removed in v2.12.0 — the table is KPI · Rating · Share of final score.)*
*   **MySQL refused the `..._000170` migration** on the first attempt: it had been using the old
    four-column unique index as the backing index for the `employee_id` foreign key, and will not
    drop an index a constraint depends on. The replacement index is now created *before* the old
    one is dropped, in both `up()` and `down()`.
*   **The dashboard's Pending Reviews tile counted every review in every closed cycle, forever.**
    It filtered `whereNotIn('status', ['completed'])` — correct only while something wrote that
    value. Nothing does any more: `completed` is derived from the cycle's end date
    (`App\Support\ReviewStatus`), so the filter excluded nothing, and every frozen review in every
    ended cycle sat on the tile as outstanding work while the review screen showed it as Completed
    and refused edits. Both the admin/HR tile and the supervisor tile now join `review_cycles` and
    ask the date through `CycleStatus::whereNotEnded()`
    ([DashboardController.php:112](hims-app/app/Http/Controllers/DashboardController.php#L112),
    [:203](hims-app/app/Http/Controllers/DashboardController.php#L203)). Regression-tested by
    `Feature\ReviewAuthorityTest::test_the_dashboard_does_not_count_frozen_reviews_as_pending`,
    which is MySQL-gated the same way as the other review read screens because the admin tile is
    built by the CONCAT-carrying blocks.
*   **"My Development" 500'd for anyone who had a credential on file.**
    `employees/progression.blade.php` printed `$cr->credential_name`; the column on
    `employee_credentials` is **`credential_type`**, which is what every other credential view
    already reads ([employees/show.blade.php:124](hims-app/resources/views/employees/show.blade.php#L124),
    [competency/credentials/index.blade.php:33](hims-app/resources/views/competency/credentials/index.blade.php#L33),
    and all four dashboard partials). The page only reaches that line when the credentials
    collection is non-empty, so it worked perfectly for an employee with none — and the four
    existing `EmployeeProgressionTest` cases never inserted one, so the loop had never run in a
    test. Fixed at
    [employees/progression.blade.php:133](hims-app/resources/views/employees/progression.blade.php#L133)
    and covered by the new
    `Feature\EmployeeProgressionTest::test_progression_view_renders_credentials`, which seeds a PRC
    licence and asserts a 200. **This is the same failure mode as the `jci_standard_code` defect
    below** — a column name that no test ever put a row in front of — and the third one this
    release. Reverting the one-word fix was confirmed to reproduce the reported
    `Undefined property: stdClass::$credential_name` before the fix was restored.

### Added

*   **`App\Support\CycleStatus`** ([CycleStatus.php](hims-app/app/Support/CycleStatus.php)) — the
    one definition of a review cycle having ended. Four states (`planned`, `active`, `closed`,
    `archived`); `of($stored, $endDate)` returns `archived` if that is what the column says,
    otherwise `closed` if the end date has passed, otherwise the stored value. **Archived outranks
    the date; the date outranks the stored column otherwise.** `hasEnded()` compares `Y-m-d`
    strings with a strict `<`, so **a cycle ending today is open all day**. `whereNotEnded($query)`
    is the query-builder form, binding the date as a parameter rather than baking in `CURDATE()`,
    which keeps it running on the sqlite connection phpunit uses.
*   **`ReviewStatus::cycleHasEnded()` now forwards to `CycleStatus::hasEnded()`**
    ([ReviewStatus.php:80](hims-app/app/Support/ReviewStatus.php#L80)). The cycle badge and the
    review freeze must turn over on the same day, and two copies of one date comparison is exactly
    how they would stop doing that. `Unit\CycleStatusTest` asserts the two classes agree on every
    boundary offset, so a drift fails the suite rather than the hospital.
*   **`PerformanceController::markCycleStatus()`**
    ([PerformanceController.php:139](hims-app/app/Http/Controllers/PerformanceController.php#L139))
    — stamps `effective_status` onto a paginator or a collection, mirroring the existing
    `markScoreable()` for reviews. It uses `through()` on a paginator and `map()` otherwise, so a
    paginated cycle list keeps its page metadata.
*   **`Unit\CycleStatusTest`** — 16 tests: the boundary provider (yesterday/today/tomorrow),
    archived surviving the end date, a null end date never closing a cycle, the SQL and PHP forms
    agreeing, cross-class agreement with `ReviewStatus`, and
    `test_the_app_clock_is_the_hospital_clock`, which pins `config('app.timezone')` to
    `Asia/Manila`. **`phpunit.xml` deliberately does not override the timezone**, so that
    assertion is live rather than decorative.
*   **Two tests in `Feature\ReviewAuthorityTest`** — one closing a real gap. The pre-existing
    `test_a_review_freezes_when_its_cycle_ends` set its fixture's cycle status column to `'closed'`
    by hand, which nothing in the app ever does, so it never exercised the date path. The new
    `test_the_end_date_freezes_the_review_even_while_the_cycle_still_says_active` leaves the column
    on `active` and lets the date do the work; `test_a_review_cannot_be_opened_in_a_cycle_that_has_ended`
    covers the new `storeReview()` guard.

### Changed

*   **`performance/index` and `performance/cycles/show` read their badge from `CycleStatus`**
    instead of hand-rolled ternaries over the raw column. The cycle page hides **Add Review**
    unless the cycle is in `CycleStatus::OPEN_FOR_REVIEWS`.
*   **`DashboardController`'s review-progress chart is scoped to cycles that have not ended**, so
    it no longer draws bars for work nobody can move.
*   **`rc.status as cycle_status` removed from `scoreReview()`** — it was selected and consumed
    nowhere, a raw status read waiting to be picked up by the next person who needed one.

### Removed

*   **Self-assessment and peer review, entirely** (migration
    `2026_08_10_000170_remove_self_and_peer_review`). A review used to gather three voices blended
    30/20/50; it is now one reviewer's assessment of one employee. Dropped:
    `review_kpi_scores.self_score` / `.peer_score`, `performance_reviews.self_rating` /
    `.peer_rating`, the whole `peer_reviews` table, the `performance.reviews.contribute` route and
    `PerformanceController::contributeScores()`, the Gate `self-assess-review` (**17 Gates back
    down to 16**), the `360` review type, and the Peer Feedback card on the review detail page.
    `weighted_score` now carries the reviewer's own number straight through, so
    `CompetencyGapAnalysisService` and the gap-analysis employee view keep reading a column that
    still means "the score for this KPI".
*   **Every existing `performance_reviews` row was deleted by that migration**, because the old
    shapes are not translatable — a row sitting in `self_assessment` has a self score and no
    reviewer score, and migrating it forward would either invent an assessment nobody made or
    leave a review that reads as complete with nothing in it.
*   ⚠️ **All `performance_improvement_plans` rows were deleted as collateral.**
    `triggered_by_review` is `NOT NULL` with a plain foreign key — no cascade, no null-on-delete —
    so a PIP pins its review in place and the purge could not start until the PIPs were gone. This
    is real data loss, stated here rather than glossed over. Any PIP that mattered has to be
    re-entered against a new review.
*   **The unique index lost `review_type`** — `pr_employee_cycle_type_reviewer_unique` becomes
    **`pr_employee_cycle_reviewer_unique`** over `(employee_id, cycle_id, reviewer_id)`. It used to
    let one reviewer file a standard review *and* a promotion review on the same employee in the
    same cycle; the rule is now one review per reviewer per employee per cycle, whatever it is
    labelled. Still a backstop and not the rule: `reviewer_id` is nullable and MySQL counts NULLs
    as distinct, so the PHP check in `storeReview()` is what actually redirects a repeat.

### Security

*   **The freeze is an authorization boundary, and it is now enforced on the correct day.** Before
    the timezone fix, an eight-hour window opened every morning in which a supervisor could still
    edit scores on a cycle that had closed the night before. Both `scoreReview()` (the GET screen)
    and `saveScores()` (the write) refuse independently
    ([PerformanceController.php:558](hims-app/app/Http/Controllers/PerformanceController.php#L558),
    [:599](hims-app/app/Http/Controllers/PerformanceController.php#L599)) — hiding the button is
    not the control, and a replayed PUT carrying a valid CSRF token was verified to be refused with
    the stored scores unchanged.
*   **The freeze is derived, not scheduled.** `completed` is never written to the database and no
    cron transitions it. This is deliberate: an authorization rule that depends on `schedule:run`
    being wired is an authorization rule that fails open on any box where it is not. The cost,
    stated plainly — `performance_reviews.status` only ever holds `draft` or `finished`, so a raw
    query that reads the column alone cannot see that a review is completed. It has to join
    `review_cycles` and go through `ReviewStatus::of()` or `caseSql()`. Every read path in
    `PerformanceController` does.

### Docs

*   `CLAUDE.md` — test baseline **256 tests, 247 passed, 9 skipped, 1146 assertions**; **16** Gates
    (was 17); the corrected unique index; the deleted two-write-routes/`self_score`/`peer_score`
    section replaced with the derived-freeze rule; `ReviewStatus` and `CycleStatus` documented
    beside `CredentialStatus` as one date-boundary family; the `Asia/Manila` timezone recorded as
    an authorization setting; a new **Alerts** section for the `data-auto-dismiss` opt-in and the
    prose-flow rule; `hims.css` 1236 → **1243** lines; and the portable-SQL warning extended with
    the `credential_name` defect, which is the same trap reached through a Blade property rather
    than a `select()`.
*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — `review_cycles.status` documented as **advisory rather
    than authoritative** with the archived-outranks-the-date precedence; `review_type` recorded as a
    label rather than a workflow stage.
*   **Four stale "own department" claims corrected** across
    `HIMS_SYSTEM_DOCUMENTATION.md` (the progression route's barrier, the assignment roster, the
    course-completion rule, the compliance at-risk list) and `HIMS_ARCHITECTURE_AND_SECURITY.md`
    (the `view-compliance` gate). All four name `authorizeEmployeeAccess()` /
    `canAccessEmployee()` / `scopeToVisibleEmployees()`, which became **reporting-line** filters when
    the scope rewrite landed — a supervisor is held to `employees.supervisor_id`, not to a shared
    department. The docs had not followed. Checked rather than assumed: `TrainingController`'s
    check-in panel ([:209](hims-app/app/Http/Controllers/TrainingController.php#L209)) and
    `AiEntityResolver::scopeEmployees()`
    ([:220](hims-app/app/Services/Ai/AiEntityResolver.php#L220)) really do still match on
    `department_id`, so the doc lines describing *those* two were left as they were.
    `HIMS_USER_GUIDE.md` already said "the people who report to you" and needed no change.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — new **§4.A.1 Review and cycle status are derived, not stored**;
    the `peer_reviews` DDL block, the `self_score`/`peer_score` columns and the two ER edges
    deleted; the routes table's score rows corrected (the doc had claimed the GET score screen
    carried no `role:` middleware — it sits in the same role group as the write); table totals
    reconciled to **59 tables + 1 view** across 21 migrations, 49 live.
*   `HIMS_USER_GUIDE.md` — the three-sources table and the 50/30/20 blend removed; the real entry
    points named (**Start a Review**, **Add Review** — there is no "Create Review" button);
    Draft/Finished/Completed explained with what a mid-edit freeze looks like; closed cycles
    refusing new reviews; dates are Philippine time; one review per reviewer per person per cycle;
    the linked-employee requirement; and both empty states on the review form.

### Verification

`vendor/bin/pint` clean. Full suite green: **256 tests, 247 passed, 9 skipped, 1146 assertions** on
sqlite `:memory:`; the 31 MySQL-gated tests were then run against a scratch MySQL database and all
31 passed, which is what actually exercises the new dashboard and credentials assertions. The
scratch database was dropped afterwards and `hims_v2` confirmed untouched (9 employees, 12
credentials).
All five date guards were mutation-tested — each deliberately broken, the intended test confirmed
to catch it, then restored byte-exact. Verified end to end in headed Chrome against a cycle whose
`end_date` was 2026-08-10: cycle badge reads **Closed**, review badge reads **Completed**, no
**Add Review**, no **Score** button, a direct `GET /score` redirects away with *"This review closed
when its cycle ended and can no longer be edited"*, and a replayed `PUT` with a genuine CSRF token
lands back on the read-only page with all seven KPI scores unchanged.

---

## v2.10.0 — 2026-08-10

**Performance review authority is now identity- and chain-of-command-based, not role-based.** Before
this release, holding the `supervisor` role let you review anyone in your department, and `admin` or
`hr_manager` let you review anyone at all; one account could sit down and fill in the self, peer *and*
supervisor ratings on the same review; and clicking **New Review** twice for the same person and cycle
created a second row rather than reopening the first. All three are closed.

This is a **behaviour-narrowing release**. Accounts that could previously open a review can now be
refused, by design — including Admins. Read the Security section before deploying.

### Added

*   **Migration `2026_08_10_000160_add_review_authority_to_performance_reviews`** — three columns on
    `performance_reviews` (`is_exception_review` boolean default false, `exception_basis` varchar(30)
    nullable, `exception_reason` text nullable) plus a unique index over
    `(employee_id, cycle_id, review_type, reviewer_id)`. The index is named explicitly,
    **`pr_employee_cycle_type_reviewer_unique`**, because the name MySQL generates from four columns
    overruns the 64-character identifier limit. Plain column adds, no table rebuild.
*   **`Controller::isLegitimateReviewerFor()`** ([Controller.php:119](hims-app/app/Http/Controllers/Controller.php#L119))
    — the single answer to "may this account review that employee": true when the employee's
    `supervisor_id` equals the acting account's linked `employee_id`. It takes an `employee_id`, not a
    role, and it is the same call for every role.
*   **`Controller::reviewExceptionBasis()`** ([Controller.php:142](hims-app/app/Http/Controllers/Controller.php#L142))
    — returns which of the three sanctioned out-of-chain grounds applies, or `null` if none does:
    `no_supervisor` (the employee has no `supervisor_id`), `supervisor_unavailable` (the assigned
    supervisor's `employment_status` is `on_leave`, `suspended` or `resigned`), `supervisor_is_subject`
    (the supervisor is themselves under review in that cycle). **`null` means refused** — there is no
    override flag, no force parameter, and no way to reach the write path without one of the three.
*   **`performance.reviews.contribute`** (`PUT /performance/reviews/{id}/contribute`) — where self and
    peer ratings post. It sits **outside** the `role:admin,hr_manager,supervisor` group, because the
    person entering their own self-rating is usually Staff.
*   **Gate `self-assess-review`** — 16 Gates become **17**. It asks only whether the account has a
    linked employee profile, which is why it can be a Gate at all: every other rule in this release
    needs the review row, and a Gate closure only receives the user.
*   **`PerformanceController::contributeScores()`** ([PerformanceController.php:573](hims-app/app/Http/Controllers/PerformanceController.php#L573))
    and **`reviewableEmployees()`** ([PerformanceController.php:318](hims-app/app/Http/Controllers/PerformanceController.php#L318)),
    the latter being what the **New Review** employee dropdown is built from — so the list of people
    you can pick is itself the authorization statement, not a convenience filter over a wider set.

### Changed

*   **`Controller::scopeToVisibleEmployees()` now scopes supervisors by the reporting line, not the
    department** ([Controller.php:83](hims-app/app/Http/Controllers/Controller.php#L83)). Admin and
    HR still see every row here; a supervisor sees the employees whose `supervisor_id` is their own
    `employee_id`, plus their own record. **This helper is shared**, so the narrowing landed in five
    controllers at once — Employees, Performance, Gap Analysis, Learning and Compliance — which is why
    a supervisor's CPD verification queue, course enrollee lists, at-risk list and accreditation report
    all got shorter in the same release. That breadth is intended: they are all statements about a
    person, and the reporting line is the right test for all of them.
*   **Two supervisor rules now coexist, deliberately.** `TrainingController::checkIn()` still compares
    `employees.department_id` and was **not** migrated. Marking a room full of attendees present is a
    logistical act about who was physically there — a ward supervisor running the fire drill should not
    have to be the line manager of everyone who attended. `AiEntityResolver::scopeEmployees()` likewise
    keeps its department match; it resolves names and grants nothing, since `AiActionExecutor` invokes
    the real controller and the real check still runs.
*   **Each rating column is owned by an identity, checked per-request**
    (`scoreColumnPermissions()`, [PerformanceController.php:75](hims-app/app/Http/Controllers/PerformanceController.php#L75)).
    `self_rating` is writable only by the account whose linked `employee_id` matches the review's
    `employee_id`; a `peer_reviews` row only by the account matching its `peer_employee_id`;
    `supervisor_rating` only by a legitimate reviewer. Columns the account does not own are **not
    rendered and not accepted** — a hand-crafted POST is dropped at the controller, not just hidden in
    the Blade. The `review_kpi_scores` `self_score` / `peer_score` / `supervisor_score` columns were
    left structurally alone and simply inherit the same three checks.
*   **Repeat "create" edits instead of duplicating.** `storeReview()`
    ([PerformanceController.php:361](hims-app/app/Http/Controllers/PerformanceController.php#L361))
    looks for an existing row on `(employee_id, cycle_id, review_type, reviewer_id)` and redirects to
    its scoring screen with a notice rather than inserting a second. Different `review_type` values
    still coexist — a probationary and a standard review of the same person in the same cycle are two
    legitimate rows.
*   **`performance.show` stays reachable by any signed-in account, but returns a scoped result.**
    `canViewReview()` ([PerformanceController.php:47](hims-app/app/Http/Controllers/PerformanceController.php#L47))
    allows the subject, the author, and a legitimate reviewer of the subject; everyone else gets a 403.
    The route middleware did not change — what changed is that the URL alone no longer decides.
*   **The scoring screen split across two routes.** The **GET** screen (`reviews.score`) moved outside
    the `role:` group so Staff can reach their own self-assessment, while the **PUT** for supervisor
    scoring (`reviews.score.save`) deliberately stayed inside it. That is not redundancy:
    `AiActionRegistry` derives the assistant's permissions from route middleware, so opening
    `reviews.score.save` would have silently granted every signed-in account the
    `performance.review.status` AI action. Self and peer writes therefore go to the separate
    `contribute` route instead.

### Security

*   **No self-review, at any level.** `reviewer_id` may never equal `employee_id`. Enforced at creation
    and at scoring, with **no role exempt** — an Admin cannot create one for themselves either. Verified
    against the seeded database after the change:
    `DB::table('performance_reviews')->whereColumn('employee_id','reviewer_id')->count()` returns **0**.
*   **`admin` and `hr_manager` lose blanket review access.** They are now held to the same
    chain-of-command test as a supervisor. An HR Manager who is not somebody's line manager gets a 403
    on their review, which is a real change in behaviour for those two roles and the one most likely to
    generate support questions after deployment.
*   **The exception path is logged, flagged and bounded.** An admin or HR manager acting outside the
    chain must supply a written reason; the review is stamped `is_exception_review = 1` with its
    `exception_basis`, badged **Exception** in the UI wherever it appears, and written to
    `audit_trails` as `review_exception`. The audit row is not redundant with the columns: the columns
    say the review *is* an exception, the row says **who declared it one, when, and from where** — and
    since the basis is evaluated against organisational state that moves, a review stamped
    `supervisor_unavailable` still reads that way after the supervisor returns from leave. Only the
    timestamped row establishes that the basis held when it was claimed.
*   **Logged through the existing audit trail, not a second one.** `App\Support\AuditTrail::record()`
    gains a fourth caller; `before_state_hash` / `after_state_hash` / `chain_hash` stay **null**, as
    hash chaining remains unimplemented. Audit coverage is still deliberately partial — ordinary UI
    edits are not tracked — so the presence of a `review_exception` row is itself the finding.
*   **The unique index is a backstop, not the rule.** `reviewer_id` is nullable (its FK is
    `nullOnDelete`) and MySQL treats NULLs as distinct in a unique index, so two reviewer-less rows
    would both pass it. The PHP pre-check in `storeReview()` is what actually deduplicates.

### Removed

*   **The department-based reviewer rule.** A supervisor can no longer review someone merely because
    they share a department; co-membership now grants nothing on its own.
*   **Any single-account path to all three rating sources on one review.** Previously reachable by any
    `admin` or `hr_manager`.

### Migration notes

*   **Run `php artisan migrate`.** The unique index is created over live data — if the table already
    holds duplicate `(employee_id, cycle_id, review_type, reviewer_id)` rows, the migration will fail
    on the index rather than silently discard one. Resolve the duplicates first.
*   **`employees.supervisor_id` is now load-bearing.** An employee with a blank `supervisor_id` is
    invisible to every supervisor and reviewable only through the `no_supervisor` exception path. The
    Employee form's **Reports To** field and the seeder's reporting line both exist for this reason.
    Populate it before deploying, or expect the exception path to become the norm.
*   **Deferred, not forgotten:** dual/matrix supervision (a second reporting line) was explicitly held
    back pending confirmation, and `peer_reviews` still has **no production write path** — the peer
    column's identity check is implemented and tested, but rows reach the table only via seeding.

### Verification

`vendor/bin/pint` clean; `php artisan test` at **213 tests, 206 passed, 7 skipped, 932 assertions**
(up from 194/190/4/883). New tests cover the chain-of-command check, the self-review refusal at both
creation and scoring, each of the three exception bases, the dedupe-to-edit redirect, and per-column
identity ownership. Review *read* screens use `CONCAT()` and are `@group mysql`-gated in the manner of
`Feature\EmployeeProgressionTest`; the write paths stay portable and run on sqlite.

Checked end-to-end in **headed Chrome** across all four seeded roles: the supervisor's **New Review**
dropdown listed only her two direct reports; a repeat create redirected to the existing review instead
of adding one; a single review was written by two different accounts with each restricted to its own
column; HR received a 403 on an out-of-chain review; and the exception path was driven through to a
badge in the UI, the three stamped columns in the database, and the matching `audit_trails` row.

### Docs

*   `CLAUDE.md` — the authority rules, the rewritten `scopeToVisibleEmployees`, and updated test counts.
*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — §2.2 schema entry for the three columns and the named index;
    §3.2.2 review authority; §3.4 goes from three write-tracking paths to **four**; §6 routing table.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — §3 scoping, §3.1 role matrix (17 Gates), §4.A Performance from 4
    bullets to 9, §12.1 routes. **§6.3's `CREATE TABLE performance_reviews` had fictional CHECK
    constraints removed** — `pip_followup`, `ai_audit`, `pending_approval`, `approved`, `archived` and
    `returned` appear in no migration and no validator; they were design-era text that never shipped.
*   `HIMS_USER_GUIDE.md` — §4 rewritten around the reporting line, the three exception bases, and the
    column-ownership table; §1, §3, §7 and §8 corrected from "department" to "direct reports" where the
    shared helper governs. §2 gains a note that the **dashboard still counts the department** while the
    review pages count direct reports, so a supervisor seeing five pending reviews but able to open two
    is looking at correct behaviour, not a bug.

Not pushed to GitHub.

---

## v2.9.4 — 2026-08-10

Two bug fixes, both reported from the Competency page. Modals opened far below the fold on long
pages, and the Department Skills Gap Matrix's department dropdown did nothing at all.

### Fixed

*   **Modals now centre on the screen, not on the page.** All six backdrops are `position: fixed`
    with `inset: 0`, which should mean "the viewport" — but they were being rendered inside the
    content wrapper in `<main>`, which carries `.animate-in`. Its `fadeInUp` keyframes end on
    `transform: translateY(0)` and `animation-fill-mode: forwards` holds that transform in place
    permanently, and **any transform other than `none` makes an element the containing block for its
    `position: fixed` descendants**. So `inset: 0` resolved against the full page content box and the
    panel centred on the middle of the *page*. On Competency — the longest page in the app — that put
    it well below the fold. Each partial now `@push`es only its backdrop to a new `@stack('modals')`
    in `layouts/hims.blade.php`, placed outside `<main>`, so the markup renders as a direct child of
    `<body>`. **No CSS changed**: `margin: auto` on `.hims-modal` already centred both axes, and
    `align-items: flex-start` + `overflow-y: auto` on the backdrop already let an over-tall modal
    scroll rather than clip. The `@include('partials.modal-js')` and the auto-open `@push('scripts')`
    blocks deliberately stay outside the push.
*   **The Department Skills Gap Matrix dropdown now filters.** It was decorative — no `name`, no
    enclosing form, no `value`s on the options, no handler, and `CompetencyController::index()` took
    no `Request`. It is now a `<form method="GET">` around a `<select name="department_id"
    onchange="this.form.submit()">` with `@selected(...)` reading the choice back, the same shape as
    the position filter on `/succession`. Applies on pick, no Apply button, and the resulting
    `?department_id=` URL is shareable and survives a refresh.

### Changed

*   **The gap matrix no longer lists competencies nobody has been assessed on.** The query joined
    `competency_assessments` with a `leftJoin`, so an untested competency came back with
    `AVG(...) = NULL`, which the view's `?? 0` read as a gap of zero and badged green **"Met"** — a
    skill nobody had ever been tested on displayed as satisfied. It is now an inner join. This was
    already wrong hospital-wide; filtering to a single department would have made it the norm rather
    than the exception, since most departments are assessed on a fraction of the catalogue.
*   `CompetencyController::index(Request $request)` additionally selects `$departments` (for the
    select) and passes `$filterDepartmentId` so the view can echo the current pick and vary the empty
    state — "No assessments recorded for this department yet." when filtered, "No assessment data
    yet." when not.

### Verification

`vendor/bin/pint` clean; `php artisan test` unchanged at **194 tests, 190 passed, 4 skipped, 883
assertions**. No test covers the gap matrix, so none needed amending.

Measured in headed Chrome on `/competency` (viewport 1920×945, page 1983px tall): the backdrop's
parent is `BODY`, and at both scroll top and scroll bottom it reports `top: 0`, `height: 945`, with
the panel showing equal gaps above and below and `fullyVisible: true` — the bottom-of-page case being
the exact reported failure. Filtering to **Pharmacy** cut the matrix from 20 rows to 10 and the select
read back "Pharmacy"; **Finance & Accounting** returned the filtered empty state.

### Docs

*   `CLAUDE.md` — the Modals contract goes from four rules to **five**, the new one being the
    `@stack('modals')` requirement and why the transform makes it necessary. New **List filters**
    subsection covering the GET + onchange-submit convention and the matrix's two-hop department join.
*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — frontend stack row notes where modals render; §6.1 gains a
    note that the push moves markup, not the `@can` control.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — shared UI conventions gains the stack behaviour; §12.2 documents
    the department filter and the inner-join semantics.
*   `HIMS_USER_GUIDE.md` — how to use the department dropdown, and why a filtered matrix is shorter.

Not pushed to GitHub.

---

## v2.9.3 — 2026-08-10

UI release. **Creating a record no longer takes you off the page either.** v2.9.2 did this for
*editing* a review cycle; this does it for the five remaining "create" buttons — **New Cycle**
(Performance), **New Assessment** and **Add Credential** (Competency), **New Course** and
**New Pathway** (Learning). Each opened a standalone page whose only content was a short form.
All five are now modals on the list page they belong to. Separately, the two places that asked
users to **Ctrl/Cmd-click a `<select multiple>`** are now tick-box lists.

### Added

*   **`partials/modal-js.blade.php`** — the single modal controller, `@once`-guarded and pushed to
    `@stack('scripts')`, so a page can `@include` it once per modal and still ship one copy. Defines
    `window.himsModal.open(id)` / `.close(id)`, then listens on `document` for
    `[data-modal-open="<id>"]` and `[data-modal-dismiss]`. **Delegated deliberately**: that is what
    lets two modals share one page and lets markup rendered after load still work. Opening locks body
    scroll and remembers the trigger so focus returns to it on close. Escape and a backdrop click
    dismiss. Markup now declares intent with `data-modal-open` — no page writes its own open/close JS.
*   **Five create modals**, all built from the existing `.hims-modal-backdrop` / `.hims-modal` pair:
    `performance/cycles/_create-modal` (`cycleCreateModal`), `competency/assessments/_create-modal`
    (`assessmentCreateModal`), `competency/credentials/_create-modal` (`credentialCreateModal`),
    `learning/courses/_create-modal` (`courseCreateModal`), `learning/pathways/_create-modal`
    (`pathwayCreateModal`). Three are hosted on two pages each — Add Credential is on the Competency
    index *and* on the credentials register, New Pathway on the Learning index *and* the pathways
    list — so you can add one without leaving the list you are reading.
*   **`?new=<thing>` deep links** — `?new=cycle`, `?new=assessment`, `?new=credential`, `?new=course`,
    `?new=pathway` open the matching modal on page load. This is what kept the dashboard quick actions
    and the cross-module prompts ("Create a Cycle" on the new-review page) **one click** after their
    GET pages were deleted, rather than silently 404ing.
*   **A hidden `_modal` field on every modal form**, naming its own id. `store()` redirects back on a
    validation failure, so the modal cannot render its own errors; the flashed `old('_modal')` tells the
    page which of its modals to reopen, with the user's input intact. Without it a page hosting two
    modals could not tell them apart.
*   **`.hims-checklist` in `public/css/hims.css`** — a scrolling box of `<label><input type="checkbox">`
    rows, with `.hims-checklist-filter` / `.hims-checklist-empty` and a `.checklist-meta` for the code
    beside each name. Plus **`.hims-link-button`**, a `<button>` styled to read as the inline link it
    replaced, for empty-state sentences that now open a modal instead of navigating. 1186 → **1236 lines**.
*   **`partials/checklist-js.blade.php`** — same `@once` + `@push` shape. An
    `<input data-checklist-filter="<list id>">` above a list hides non-matching rows and reveals
    `[data-checklist-empty]` when nothing matches. It toggles `display` on the `label` only and **never
    touches `checked`**, so a box ticked before filtering is still ticked — and still submitted — after.

### Changed

*   **Course competency tagging and pathway target roles are tick boxes**, on the course page, in the
    course create modal and in the pathway create modal. "Hold Ctrl (Windows) or Cmd (Mac) to select
    multiple" was the specific instruction users could not discover, and a stray click silently cleared
    every prior selection. Nothing about the payload changed — still `competencies[]` / `target_roles[]`,
    still "ticking nothing clears every tag". The course list (28 rows) gets the filter box; target
    roles (9 rows) does not need one and omits the include.
*   **The cycle *edit* modal moved onto the shared controller.** It was introduced in v2.9.2 with its own
    self-contained IIFE; that has been retired in favour of `window.himsModal`, which is what lets the
    create and edit modals coexist on `performance/index` and `performance/cycles/show` without two
    competing scroll locks. Its behaviour is unchanged — one form re-pointed per row from `data-*`
    attributes, and picking a type still refills the dates.
*   **The dropdown data the deleted pages loaded moved into the index methods.** `CompetencyController::index()`
    now selects `$employees` and `$competencies`, `LearningController::index()` selects `$competencies`
    and `$roles`, and `pathwaysIndex()` selects `$roles` — none of which the page body itself uses; they
    exist to fill the modals. `LearningController::taggableCompetencies()` is the shared query behind
    both the course modal and the course page's tagging panel; a third caller should call the helper,
    not write the join again.

### Removed

*   **Five GET create routes, their controller methods and their Blade pages** — `performance.cycles.create`
    / `PerformanceController::createCycle()` / `performance/cycles/create.blade.php` (53 lines);
    `competency.assessments.create` / `createAssessment()` / `assessments/create.blade.php` (70);
    `competency.credentials.create` / `createCredential()` / `credentials/create.blade.php` (68);
    `learning.courses.create` / `LearningController::createCourse()` / `courses/create.blade.php` (81);
    `learning.pathways.create` / `createPathway()` / `pathways/create.blade.php` (58). 330 lines of Blade.
    Views extending `layouts/hims` 57 → **52**.
*   **Every `<select multiple>` and every "Hold Ctrl…" hint** in the application.

### Security

Nothing was relaxed. The `role:` middleware on each `store` route is unchanged and is still the
enforcement point — removing the GET route removed a *duplicate* list of who may create, not a control.
The `@can` wrapper around each button is presentation. The data that moved into the index methods is
reference lists (employees, competencies, roles) already visible on those pages, not row-scoped records.
`AiActionRegistry` is unaffected because its keys always pointed at `.store`, never `.create` — which is
precisely what made deleting the pages safe, and is worth keeping that way.

### Tests

No new tests. The routes, the POST verbs, the validation and the Gates behind all five forms are
unchanged — only the surface that reaches them moved. Suite unchanged at **194 tests, 190 passed,
4 skipped, 883 assertions**; `vendor/bin/pint` passes.

Verified in headed Chrome as `admin@hospital.ph`: `?new=cycle` opened the create modal with the create
and edit modals correctly independent on one page; picking *quarterly* in each filled `2026-07-01` /
`2026-09-30`, *semi_annual* `2026-07-01` / `2026-12-31`, *annual* `2026-01-01` / `2026-12-31`; the edit
modal re-pointed its action to the clicked row's UUID and prefilled all five fields with `_method=PUT`;
**Add Credential** opened from the credentials register with all 10 employees loaded; the course page
reported no `<select multiple>` and no "Hold Ctrl" text, 28 checkboxes named `competencies[]`, and
filtering on "life" left 2 rows visible without disturbing what was ticked. Tagging a course end-to-end
saved and rendered the badge; the test data was then reverted.

### Docs

*   `CLAUDE.md` — new **Modals** section (the six-modal table with host pages and required view data,
    plus the four contract rules) and **Checkbox lists** section; the note that five create screens have
    no GET route; view and CSS line counts.
*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — frontend stack row; new **§6.1 Creation has no GET route**
    covering the three security consequences above.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — five rows dropped from the §12.1 / §12.2 / §12.4 route tables, with
    a note on each replacing them; new **Shared UI conventions** subsection under §1.1; §8 and §10 prose.
*   `HIMS_USER_GUIDE.md` — §4, §5 and §7 rewritten around panels; new **Adding Something New** subsection
    in §1 explaining panels-over-pages and tick boxes once, for all modules.
*   `HIMS_PATCH_NOTES.md` — this entry.

---

## v2.9.2 — 2026-08-10

UI release. **Editing a review cycle no longer takes you off the page.** The Edit button on the
Performance index and on a cycle's own page opened `/performance/cycles/{id}/edit`, a full page whose
only content was five fields already visible in the row behind it. It is now a modal.

### Added

*   **A modal component in `public/css/hims.css`** — `.hims-modal-backdrop` / `.hims-modal`, the first in
    the stylesheet. `z-index: 1200` clears the topbar (99) and its dropdowns (1100), so a modal is never
    half-covered by the shell. Two-phase animation: JS adds `.open` to display it, then `.shown` on the
    next frame, because a transition needs a painted start state to animate from.
*   **The backdrop is transparent**, not a scrim. It exists only as a full-viewport click target for
    dismissal — the page stays fully legible behind the panel, which instead carries its own separation
    through a `--hims-primary` border and a heavier shadow. 1111 → **1186 lines**.
*   **`performance/cycles/_edit-modal.blade.php`** — one form per page, re-pointed per row. Clicking any
    button carrying `data-cycle-edit` rewrites the form's action and fills its fields from that button's
    `data-*` attributes, so a table of twenty cycles ships one `<form>` rather than twenty. Dismissable by
    **Cancel**, **×**, Escape, or a click outside the panel; focus returns to the button that opened it.
*   **`performance/cycles/_date-range-js.blade.php`** — the create page's cycle-type → date-range logic,
    extracted behind `window.himsCycleRange(type)` and wrapped in `@once` so both hosts share one
    definition of the leap-year and probation rules rather than each keeping a copy.

### Changed

*   **Picking a cycle type in the modal refills the dates**, exactly as it already did when creating one.
    Only a *user's* pick does: prefilling the fields when the modal opens is a programmatic `.value`
    assignment, which raises no `change` event, so an existing cycle's stored dates survive being opened
    and cancelled.
*   **`updateCycle()` redirects to `performance.index`**, not to the cycle it just saved. The edit is
    launched from wherever the user already was, so landing them on a different page reads as a redirect
    they did not ask for.
*   Both hint lines in the modal gained bottom margin. The panel is narrower than the create page, so
    each wraps to two lines and would otherwise touch the date labels in the row beneath.

### Removed

*   **`GET /performance/cycles/{id}/edit`**, its route name `performance.cycles.edit`,
    `PerformanceController::editCycle()`, and `performance/cycles/edit.blade.php` (80 lines). Two editors
    for one record is one too many. `AiActionRegistry` already pointed at `performance.cycles.update`, so
    nothing else referenced the removed name.

### Tests

No new tests. The route, the PUT verb, the validation and the `manage-review-cycles` Gate are unchanged —
only the surface that reaches them moved — so the existing coverage still applies. Suite unchanged at
**194 tests, 190 passed, 4 skipped, 883 assertions**; `vendor/bin/pint` passes.

Verified in the browser: the modal opens prefilled from both hosts, the backdrop computes to
`rgba(0,0,0,0)`, switching to *quarterly* refilled the dates to the current quarter, cancelling and
reopening restored the row's stored values, and saving landed on `/performance` with the flash message.
A staff account sees neither the button nor the modal markup.

### Docs

*   `HIMS_SYSTEM_DOCUMENTATION.md` — the §12.1 route table drops the edit row, gains a note on how
    editing now works, and `cycles.show` corrected from `admin,hr_manager` to *all authenticated* (it
    carries no `role:` middleware and never did).
*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — the modal component in the frontend stack row; line count.
*   `HIMS_USER_GUIDE.md` — §4 now describes editing a cycle and the type-fills-the-dates behaviour.
*   `CLAUDE.md` — the modal convention and its single consumer; line count.
*   `HIMS_PATCH_NOTES.md` — this entry.

---

## v2.9.1 — 2026-08-10

Fix release, and the one that makes v2.9.0 mean anything. **Course completion had no writer.** Nothing
in the application ever set `course_enrollments.status` to `completed`: the two inserts that create an
enrolment — self-enrolment and assignment expansion — both wrote `'enrolled'`, and no route, controller,
or service moved it on from there.

> **Every course-assignment compliance rate could therefore only ever read 0%.** The arithmetic behind
> it was correct and the drill-down listed the right people; the numerator was simply unreachable. An
> assignment showing "0 of 3" was not a department ignoring its mandatory training — it was a status
> column with nothing to change it. `cpd_hours_earned` was dead for the same reason, so a completed
> course credited nothing towards a renewal cycle either. Sessions were unaffected: check-in already
> wrote `attended`, which is why session assignments moved and course assignments did not.

### Added — recording a completion

*   **`POST /learning/enrollments/{id}/complete`** (`LearningController::completeEnrollment()`) — stamps
    `completed_at`, sets `status = completed` and `progress_pct = 100`, and writes the course's
    `cpd_hours` to `cpd_hours_earned`.
*   **CPD is credited, already verified.** Where the course carries hours, the same transaction inserts
    a `cpd_records` row with `source_type = 'course'` and `verified = true` — matching the existing rule
    that in-house, system-witnessed activity does not queue for approval. This is what makes a completion
    move a renewal cycle, since `RenewalCycleService::attainedHours()` counts verified CPD only.
*   **The employee is notified** through `NotificationService`, so the credit is not silent.
*   **`POST /learning/enrollments/{id}/reopen`** — reverts the enrolment and **deletes the CPD row it
    created**, so withdrawing a completion withdraws its evidence rather than leaving orphaned hours.
*   **Controls on both hosts.** The course page's *Enrolled Employees* table and the compliance
    drill-down's roster both gained the action; the drill-down sends session rows to the attendance sheet
    instead, keeping one completion mechanism per subject type.

### Security

*   **New Gate `record-completion`** (admin, hr_manager, supervisor) — 15 → **16**. Completion is a
    statement *about* a person, so **nobody self-certifies**: staff do not see the control and the route
    refuses them, mirroring the rule already in force for session check-in. Supervisors are additionally
    held to their own department by `authorizeEmployeeAccess()`.
*   **Reopen is deliberately narrower** than complete — `manage-learning`, so admin and HR only.
    Recording evidence and destroying it are not the same privilege.
*   **Idempotent.** A second completion is refused rather than applied, and `courseCpdExists()` stops a
    complete → reopen → complete cycle from stacking credit.
*   **Audited.** `AuditTrail::record()` gains `complete_enrollment` and `reopen_enrollment`, the latter
    with a `beforeState` snapshot. There is no `completed_by` column — the audit row is the record of who
    did it, rather than a second place to keep the same fact.

### Fixed

*   **The compliance drill-down rendered its flash message twice.** `layouts.hims` already prints
    session `success`/`error` above `@yield('content')` for every page; the page was printing it again.

### Tests

`tests/Feature/ComplianceTest.php` — 20 → **27 tests**, adding: the rate actually moving on completion;
verified CPD landing such that `attainedHours()` counts it; staff refused; a supervisor confined to their
own department; a second completion refused with exactly one CPD row surviving; reopen withdrawing both
the completion and the credit; a supervisor refused the reopen. Full suite: **194 tests, 190 passed,
4 skipped, 883 assertions**.

Verified end-to-end in the browser as well: an assignment moved 0% → 33.3% → 100% on completions and
back to 66.7% on a reopen, with the roster badges, completion dates, and `audit_trails` rows following.

### Docs

*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — the two routes in the Learning route map, `record-completion`
    in the Gate table, 15 → **16** Gates, `AuditTrail`'s third caller, and the compliance-rate note
    corrected — it previously described a numerator nothing could increment.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — §12.3 route entries, the CPD ledger's two intake paths, Gate and
    audit-action counts. Also corrected three claims that had drifted before this release: §3 and §7.2
    still counted **15** and **13** Gates respectively, and §7.2 plus the §7.3 compliance table both
    stated audit logging did not exist *at all* — it has since v2.9.0, partially. Both now read
    ⚠️ partial, naming the three callers and the absent hash chaining rather than denying the feature.
*   `HIMS_USER_GUIDE.md` — *Marking Somebody Complete* in §8 and *Marking Enrolments Complete* in §7,
    the CPD page's automatic-credit path, three new FAQ entries. **Removed the claim that completing a
    course does not credit CPD**, and the claim that no employee picker exists anywhere in HIMS.
*   `CLAUDE.md` — the completion writer, the 16th Gate, `AuditTrail`'s third caller, refreshed counts.
*   `HIMS_PATCH_NOTES.md` — this entry.

---

## v2.9.0 — 2026-08-10

Feature release. A **Compliance & Oversight** module, the institutional half of Learning: training the
hospital *requires* rather than offers, renewal windows with a run-rate warning, escalation that reaches
people without a login, and the rollup an accreditation survey asks for.

> **The theme is the difference between a personal record and an institutional one.** Every Learning
> page written so far answers an employee's question — what am I enrolled in, what have I logged, how
> many hours do I have. None of them answer the hospital's: *is this department compliant, who lapses
> next month, show me every ICU nurse's standing against JCI*. The lifetime CPD total was the clearest
> case. A nurse with 200 lifetime hours and 3 in the current three-year window is a compliance failure
> that the old number reported as a success, because it counted hours that had already been spent
> renewing a previous licence.

> **Nothing in this module requires the subject to have a HIMS login.** Assignment, cycles, alerting,
> and reporting all key on `employee_id`. Account coverage is the one page that mentions `users`, and
> there the account is the thing being reported on, not a precondition. Being proxy-tracked is a
> supported state.

### Added — assigned (mandatory) training

Admins and HR Managers can require a course or session of **one employee, a department, everyone in a
role, or all active employees**, with an optional required-by date and a reason. `TrainingAssignmentService`
expands the target into individual enrolment rows, reports how many were created and how many were
skipped, and refuses to double-enrol anyone already on the course.

**Assignment does not replace self-enrolment; it runs beside it.** A single nullable
`course_enrollments.assignment_id` is the whole distinction — set means the hospital required it, null
means the employee chose it. Both paths write the same row and are read by the same pages, so no
existing enrolment behaviour changed and no second enrolment table was created.

*   **Compliance rate and the drill-down.** The overview lists each assignment with its completion
    rate; opening one shows the roster and, importantly, **who has not finished** — the question a
    percentage cannot answer. Overdue is derived from `required_by`, not stored.
*   **Session capacity is respected.** Assigning a session to a department larger than the room reports
    the overflow as skipped rather than overbooking it.
*   **Row-scoped.** A supervisor can only assign within their own department and only sees their own
    department in a roster; the target survives the same `canAccessEmployee()` check the rest of the
    app uses.

### Added — renewal cycles

`renewal_rules` declares what a credential type or CPD requirement demands: hours, cycle length in
months, grace days. `employee_renewal_cycles` is one open window per employee per rule, and
`RenewalCycleService` decides who is falling short.

*   **Credential rules key their window to the credential's own expiry** — the cycle ends when the
    licence does. A CPD rule with no credential runs from the hire date, rolled forward until the
    window covers today, so a five-year employee on a 36-month cycle lands in their second window
    rather than one that closed two years ago.
*   **Attained hours are summed from `cpd_records` on every read, never stored.** CPD is verified days
    or weeks after it is logged, so a stored total is stale the moment a verification lands, and a
    recount job is one more thing to run and get wrong.
*   **Only verified hours count.** An employee cannot clear a compliance requirement by typing a number
    into a form.
*   **The risk band is a run-rate, not a countdown.** `at_risk` means finishing from here needs a pace
    1.5× the pace so far (`PACE_FACTOR`), or the window closes inside the 30-day credential warning
    band with hours still owed. Someone with zero hours and over half the window gone is flagged too —
    their pace-so-far is 0 and the ratio would otherwise be undefined. `shortfall` is the state after
    the window has closed unmet; `settleExpiredCycles()` stamps it nightly.
*   **`hours_required_snapshot` freezes the requirement at open,** so raising a rule from 15 to 20 hours
    does not retroactively fail everyone mid-cycle.
*   **Opening cycles is a deliberate manual step.** Saving a rule does not fan out across every active
    employee; **Open cycles** on the rules page does. A bulk write triggered by a typo is expensive to
    unpick. New hires are covered anyway — `myCycles()` syncs on read, so nobody sees a blank page.
*   **My Renewal Cycles is open to every role, including Staff.** The oversight pages answer the
    hospital's question; this one answers the individual's, and hiding it would mean the only people
    who can see a deficit are the ones who cannot fix it.

### Added — escalation that reaches people without an account

`hims:scan-credential-expiry` gained a third sweep: renewal cycles at risk or already short. It reuses
`credential_alert_log` with `subject_type = 'renewal_cycle'` for dedupe, so a warning is raised once per
cycle per band rather than every morning.

**The gap this closes:** an in-app notification needs a `users` row to land anywhere, and roughly half
the workforce has no login. Cycle and credential alerts now also email any recipient with no account,
using the address on their `employees` record — the only channel that reaches someone HIMS cannot
notify. The employee, their supervisor, and their department head are all told, because a lapsed licence
is a rostering problem, not a private one.

### Added — accreditation report

One row per employee: competency proficiency, credential standing, training completion, filterable by
department, role, and **JCI standard**. `competency_categories.jci_standard_code` has been captured
since the competency module shipped and read by nothing until now.

Reassessments mean an employee can hold several rows for one competency, so rows are reduced to the
newest per competency in PHP the same way `CompetencyGapAnalysisService` does it — a SQL `AVG` over
every row would quietly weight anyone reassessed more often. Credential window boundaries come from
`App\Support\CredentialStatus`, bound as parameters, so this report and the dashboard cannot disagree
about what "expiring" means.

### Added — succession readiness evidence

The candidate view gained two read-only panels: **Learning Evidence** (courses completed, CPD hours,
per-pathway completion bars, unfinished *required* courses called out, renewal standing) and
**Competency Proficiency** (how many competencies are at target, then each one with its JCI standard and
levels). Readiness was a judgement typed into a form while the figures behind it lived one module away
and had to be looked up by hand. A promotion decision is now reviewable against evidence rather than
against somebody's recollection.

### Added — account coverage

A read-only split of who has a linked login, who is proxy-tracked, who has neither an account nor an
email on file (**unreachable** — the only genuine problem the page reports), and which accounts point at
no employee at all. It exists so "nobody told them" can be answered with a list rather than a guess.

### Changed

*   **`credential_alert_log` was widened, not duplicated.** Two nullable columns — `subject_type`
    (`credential` | `renewal_cycle`) and `subject_id` — let one table dedupe both alert families.
    `credential_id` stays nullable for cycle rows. A parallel `cycle_alert_log` would have needed the
    same dedupe logic written twice.
*   **`credential_types` was not used for renewal rules.** It is one of the tables migrated with zero
    references, and reviving it would have meant migrating the credential-type strings already in use.
    `renewal_rules.subject_key` matches `employee_credentials.credential_type` directly.
*   **Two Gates added** — `view-compliance` (admin/hr_manager/supervisor) and `manage-compliance`
    (admin/hr_manager), bringing the total to **15**. `learning.cycles.mine` is deliberately outside
    both.
*   **`AuditTrail::record()` widened** with optional action, method and path arguments so non-AI callers
    can log. `ComplianceController` is now its second caller, writing `create_renewal_rule` and
    `sync_renewal_cycles`. Coverage is still **deliberately partial** — UI-driven edits elsewhere remain
    untracked, and hash-chaining columns stay null.
*   **One route crosses modules on purpose.** `learning.cycles.mine` (`GET /learning/my-cycles`) is
    declared in the Learning group and handled by `ComplianceController::myCycles()`. It reads the
    renewal machinery, so the code belongs with compliance; it answers "what do *I* owe", so the URL and
    the navigation belong with Learning. Duplicating the query into `LearningController` would have
    created two definitions of an employee's standing that could disagree.

### Fixed

*   **`jci_standard_code` was read from the wrong table.** The JCI tag lives on `competency_categories`,
    one join above the competency itself. Both `ComplianceController::competencyRollup()` and
    `SuccessionController::readinessEvidence()` now join through the category rather than selecting a
    column that does not exist on `competencies`.
*   **`CredentialStatus::today()` returns a string, not a Carbon instance.** `RenewalCycleService` was
    treating it as a date object; the elapsed-days calculation now parses it explicitly.
*   **`hours_remaining` and `pct_complete` were sometimes int and sometimes float.** `min()`/`max()`
    return the int literal when they clamp and a float when they do not, so the same field changed type
    depending on the data. Both are now cast.
*   **`dev_progress` divided by zero** for a candidate with no milestones. `COUNT(ldp.path_id)` with
    `NULLIF` now reads 0 milestones as 0% rather than erroring.

### Removed

*   The flat lifetime CPD total as the *only* CPD figure on the Learning tab. The number itself remains
    on the CPD page — it is a fair record of a career — but it no longer stands in for compliance, which
    is a per-cycle question it cannot answer.

### Schema

One migration, `2026_08_10_000150_create_compliance_tables.php`:

*   **`renewal_rules`** — `rule_id`, `subject_type`, `subject_key`, `label`, `required_hours`,
    `cycle_months`, `grace_days`, `is_active`, `created_by`. Unique on `(subject_type, subject_key)`.
*   **`employee_renewal_cycles`** — `cycle_id`, `employee_id`, `rule_id`, `cycle_start`, `cycle_end`,
    `hours_required_snapshot`, `credential_id`, `status` (`open`|`met`|`shortfall`|`superseded`). Unique
    on `(employee_id, rule_id, cycle_start)`.
*   **`training_assignments`** — `assignment_id`, `subject_type` (`course`|`session`), `subject_id`,
    `target_type` (`employee`|`department`|`role`|`all`), `target_id`, `required_by`, `assigned_by`,
    `reason`.
*   **Three `ALTER TABLE`s** — `course_enrollments.assignment_id`, `training_registrations.assignment_id`
    + `required_by`, and `credential_alert_log.subject_type` + `subject_id`.

Total **60 tables + 1 view** across 19 migrations; live surface **50 tables + 1 view** (42 domain + 8
framework). Ten migrated tables still carry zero references.

### Tests

`tests/Feature/ComplianceTest.php` — 20 tests covering assignment expansion for all four target types,
double-enrolment refusal, session capacity, the compliance rate and non-completer drill-down, cycle
opening and rollover, the run-rate risk bands, verified-only hour counting, row scoping on every
oversight page, the accreditation filters, and account coverage. Portable SQL, so it stays on sqlite.
Full suite: **187 tests, 183 passed, 4 skipped, 853 assertions**.

### Docs

*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — compliance module added to the module map and route map; the
    deliberate cross-module route documented; 13 → **15** Gates; scoping now names **five** controllers.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — **§G Compliance & Oversight** and **§12.5** route table added
    (Training → §12.6, Succession → §12.7, Recognition → §12.8, Employees/Depts/Users/AI → §12.9);
    **§6.9 Compliance & Oversight Tables** with full DDL and four rationale notes; ERD gains five
    relations; §3.1 role table gains a Compliance column; §7's "no audit logging" corrected to "no
    tamper-proof (hash-chained) audit trail"; counts refreshed throughout.
*   `HIMS_USER_GUIDE.md` — new **§8 Compliance & Oversight** (Training → §9, Succession → §10,
    Recognition → §11, AI → §12, Administration → §13, Account → §14, FAQ → §15); My Renewal Cycles
    added to §7; readiness evidence added to §10; five FAQ entries; the notification-bell note now
    covers cycle alerts and the no-account email fallback.
*   `HIMS_PATCH_NOTES.md` — this entry.
*   `CLAUDE.md` — compliance module, the two new services, `AuditTrail`'s second caller, refreshed
    counts.

---

## v2.8.0 — 2026-08-07

Feature release. The AI assistant can now perform write operations — create, update, and delete — bounded by the signed-in person's role.

### Added — AI action execution

The assistant in the right-hand rail can now perform any action the signed-in person could perform through the UI: creating review cycles, updating employee records, enrolling in courses, registering for training, deleting users. Destructive actions (deletions) confirm first; everything else executes immediately. Permission is read from the real route's `role:` middleware, so an action the role cannot perform on screen is refused in chat too.

**Why this is safe:** Nothing is re-implemented. `AiActionExecutor` resolves the *existing* controller from the container and calls the *existing* method with a synthesized `Request`:

```php
app(PerformanceController::class)->storeCycle($request);
```

That reuses `$request->validate([...])` verbatim, the in-method safety rails (`UserController::destroy()` refusing self-deletion and last-admin deletion), `authorizeEmployeeAccess()`, the UUID/timestamp conventions, and the MySQL triggers. A second implementation would have to restate all of it and would be wrong the first time any of it changed.

**Request flow:**
1. **Pending confirmation?** If `pending_action` is set and under five minutes old, `confirm`/`yes`/`proceed` executes it; anything else clears it and replies "Cancelled — nothing was changed."
2. **Verb pre-filter.** Only messages containing an action verb reach the classifier, so a question costs one AI call as it always did and a command costs two.
3. **`AiAccessPolicy::deniedTopic()`** — unchanged, and still first. A topic the role cannot discuss is one it certainly cannot act on.
4. **Plan.** `AiActionPlanner` classifies the message and returns `{"action","params","missing","summary"}`. An unmappable instruction or a key outside `availableTo()` falls through to normal conversation.
5. **Resolve.** `AiEntityResolver` turns names, codes and emails into UUIDs. Ambiguous or missing targets are reported as a question; nothing executes.
6. **Destructive?** The target is resolved and named back ("⚠️ Delete an employee record: **Maria Santos (EMP-0001)**"), stored in `pending_action`, and the turn ends. Otherwise execute now.
7. **Execute, audit, reply** with the controller's own flash message — so the chat says exactly what the web form would have said.

**Partial updates are filled from the current row.** Update controllers are written against a web form that posts the whole record: every field `required`, every column overwritten. A chat instruction names one thing, so `AiActionExecutor::prefill()` fills the rest from the row as it stands before calling. Without it, "set Maria Santos to probationary" was rejected for a first name, email and hire date nobody had mentioned — and had it not been rejected, it would have blanked them.

**`password_confirmation` is mirrored, not guessed.** Laravel's `confirmed` rule exists to catch a human mistyping into two password boxes. Chat has one value and no second box, so the check cannot do its job there; the registry's `mirror` key names the companion field and the executor fills it from the value already given. The rule still runs — it simply cannot fail this way.

*   **New services** — `App\Services\Ai\AiActionRegistry` (35-action catalogue, roles derived from route middleware), `AiActionPlanner` (classification prompt from `availableTo()`), `AiEntityResolver` (name/code/email → UUID), `AiActionExecutor` (synthesized `Request`, invoke, audit), `App\Support\AuditTrail` (audit row helper).
*   **One new migration** — `2026_08_07_000140_add_pending_action_to_ai_chat_sessions.php` adds nullable JSON `pending_action` and `pending_action_at` columns to `ai_chat_sessions`. The pending action is cleared on read (confirm, cancel, or 5-minute expiry).
*   **Audit.** Every successful action writes an `audit_trails` row via `App\Support\AuditTrail::record()`, holding the actor, the action (`ai_create` / `ai_update` / `ai_delete`), before/after state, the IP, and a metadata blob with the verbatim prompt and session id. Failed and rejected actions write nothing. This is the **only** source of audit rows in the system — UI-driven edits are not tracked. Hash-chaining columns stay null.
*   **Coverage.** 35 write routes (succession 7, learning 6, performance 4, training 4, recognition 4, competency 3, employees 3, users 3, departments 1), 4 of them flagged destructive. Everything one role can reach through the forms is exposed; nothing they cannot is.
*   **Test coverage.** `tests/Unit/AiActionRegistryTest.php` (15 tests — role derivation, two-layer middleware, destructive flagging), `tests/Unit/AiActionPlannerTest.php` (27 tests — malformed JSON, unpermitted keys), `tests/Feature/AiActionTest.php` (18 tests — end-to-end create, the confirm gate, stale pending actions, partial updates, whitelist enforcement). The full suite stays green: **167 tests, 163 passed, 4 skipped, 783 assertions**.

### Docs

*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — `audit_trails` removed from the dead-schema note; **§2.9 Audit** added (`audit_trails` table, single writer, three deliberate gaps). §2.8 `ai_chat_sessions` columns gains `pending_action` / `pending_action_at` (JSON, nullable). §3's "Controls that do not exist" note corrected: the general-purpose audit trail is still absent, but UI edits, credential alerts, and **AI-executed writes** *are* logged — coverage is **asymmetric**. **§3.4 Write Tracking** gained a new subsection describing AI audit rows. §5 mermaid diagram replaced to show the pipeline (planner → registry → executor → controllers). New **Action pipeline** / **Action services** rows added to the §5 table.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — unreferenced-table note drops `audit_trails`; `Eleven` → `Ten`. **§5.1 AI Action Execution** added (safety via controller reuse, role derivation from route middleware, the seven-step pipeline, partial-update prefill, `password_confirmation` mirroring, audit). `ai_chat_sessions` column list gains `pending_action` / `pending_action_at`. AI inventory row split into two: `ai_chat_sessions` + `ai_chat_messages` (chat), and `audit_trails` (AI action audit only). Total live surface now **47 tables + 1 view** (39 domain + 8 framework); 18th migration adds columns, not a table, so table count unchanged at 57.
*   `HIMS_USER_GUIDE.md` — **§11 AI Assistant** rewritten with a **Commanding the Assistant** subsection (immediate vs. confirm, 5-min expiry, disambig). FAQ gains "Can the assistant perform actions for me?" and "What happens if I tell it to delete something important?" "Need More Help?" pointer expanded to cover commanding.
*   `HIMS_PATCH_NOTES.md` — this entry.
*   `CLAUDE.md` — `audit_trails` live, AI action services, unreferenced-table count drops to **ten** with `audit_trails` removed.

---

## v2.7.0 — 2026-08-07

Feature release. Six gaps closed across Competency, Learning and Training — the ones where the module
held the data but nothing acted on it. Credential expiry now raises alerts instead of only being
displayed; CPD can be logged from the app instead of only read; attendance and feedback have forms;
courses can be tagged to the competencies they remediate; and one page now gathers everything HIMS
knows about a person's development. One new migration, two dead tables brought to life.

> **The theme is dormant data.** None of these six were missing schema. `notifications` had existed
> since the first migration with no reader and no writer; `next_assessment_due` was written on every
> competency assessment since the module shipped and read by nothing; `cpd_records.verified` had no
> flow that could set it. The module recorded what it needed and then relied on somebody remembering
> to look. That is the specific failure mode a credential register cannot have — an expired licence
> is a rostering problem whether or not anyone opened the page.

### Added — credential and reassessment alerts

`hims:scan-credential-expiry` sweeps for credentials entering the 30-day window, credentials that have
already lapsed, and competency reassessments falling due, then raises an in-app notification for each.
Registered on the scheduler at **06:30 daily** (`CREDENTIAL_SCAN_TIME`), `withoutOverlapping()` and
`onOneServer()`. `--dry-run` prints what it would raise and writes nothing.

*   **Alerts escalate to three people**, not one. `NotificationService::escalationChain()` resolves
    each alert to the employee, their `supervisor_id`, and the head of their department
    (`departments.head_employee_id`), deduplicated with missing links dropped. A lapsed licence is a
    staffing decision, so telling only its holder is the wrong shape.
*   **`notifications` and `credential_alert_log` are no longer dead schema.** `NotificationService`
    writes and reads the first; the second is a dedupe ledger keyed on `(credential_id, alert_type)`,
    so a credential sitting inside the window for a month raises one alert rather than thirty — and
    still raises a second, distinct one when it crosses into `expired`.
*   The topbar bell is fed by a **view composer** (`AppServiceProvider::composeNotifications()`), so it
    populates on every page without any of the 12 controllers knowing it exists.
*   Email is attempted only when `CREDENTIAL_ALERT_EMAIL` is on, and a failed send is swallowed. The
    in-app notification and the log row are committed first, so a dead SMTP host costs nothing.

> **Reassessments dedupe differently from credentials.** They have no alert log of their own, so the
> sweep dedupes against `notifications` rows already carrying that `assessment_id` in `reference_id`.
> Worth knowing before anyone prunes old notifications: pruning would un-suppress the alert.

### Fixed — nine queries disagreed about what "expiring" means

Credential status was recomputed inline wherever it was needed — five times in `CompetencyController`,
four more in `DashboardController` — and the copies did not agree. Two distinct bugs:

```php
// 1. A DATE column compared against a DATETIME.
->whereBetween('expiry_date', [now(), now()->addDays(30)])
// A credential expiring TODAY has an implied 00:00, so it sorted as already
// past and silently left the "expiring soon" list partway through the morning —
// on the one day it most needed to be on it.

// 2. "Valid" swallowed the expiring window.
->where('expiry_date', '>=', now())
// The same credential was counted as both valid and expiring, so the tiles on
// one page added up to more than the number of credentials that existed.
```

**Fix.** `App\Support\CredentialStatus` is now the single definition. `of()` decides in PHP;
`caseSql()` emits the same decision as SQL, parameterised with `caseBindings()` (today, and the window
end) rather than baking in `CURDATE()` — so it runs on the sqlite connection phpunit uses, and so a
scan's "today" is fixed at the moment it started instead of drifting across midnight. The four states
are mutually exclusive and sum to the row total.

> **Cosmetic before, not cosmetic now.** While the status was only ever printed, two tiles disagreeing
> was an annoyance. Once alerts fire off the same predicates, a divergence warns a nurse about a
> licence the screen calls fine — or stays silent about one it calls expiring. That is why the
> definition had to collapse to one place before the scan was written, not after.

### Added — CPD entry, with approval where it belongs

`Learning → CPD → Log CPD Activity`. Staff log their own hours; admin/HR may file against anyone,
which is why the employee picker only renders for them — and the server pins the record to
`currentEmployeeId()` regardless of what was posted.

*   **System-sourced activity auto-verifies; external activity waits.** Hours from a HIMS course or
    training session are already evidenced by the completion record, so they post `verified`. External
    activity is a claim until HR has seen the certificate, and `POST /learning/cpd/{id}/verify`
    (`manage-learning`) is what clears it — notifying the owner, and only the owner.
*   **CPD lists and hours totals are now row-scoped.** `cpdIndex()` and the totals beside it both run
    through `scopeToVisibleEmployees()`, so a member of staff sees their own hours rather than the
    hospital's. Previously the page listed everyone's.

### Added — attendance check-in and session feedback

*   `POST /training/sessions/{id}/checkin` — the roster marks `attended` / `no_show`, writing
    `check_in_time` and `check_in_method`. Gated on `manage-training`, then narrowed further: the
    session's own instructor may check in anyone on their roster, admin/HR may check in anyone, and a
    supervisor who is *not* running the session is limited to their own department. There is no QR code
    and no self-check-in — attendance is asserted by whoever ran the room.
*   `GET|POST /training/sessions/{id}/feedback` — four optional ratings plus a comment.
    **Requires an `attended` registration**, and refuses a second submission. Feedback on a session you
    did not attend is not feedback.

### Added — courses tagged to the competencies they remediate

`course_competencies` links a course to the gaps it addresses, set when authoring a course and
re-taggable from its detail page (`manage-learning`). The gap-analysis screens read the linkage back,
so a competency gap now carries suggested courses instead of naming a deficiency and stopping there.
No timestamps on the table, so re-tagging is a plain delete-then-insert.

Course pages also stopped leaking: `showCourse()` now runs its enrolment list through
`scopeToVisibleEmployees()`, where it previously showed every enrollee in the hospital to anyone who
opened a course.

### Added — My Development

One page per employee (`/my-progression`, or `/employees/{id}/progression` for supervisors and above)
holding open competency gaps, credentials with derived status, reassessments falling due inside 90
days, course activity, training sessions, and verified CPD hours over the last twelve months. Scoped
by `authorizeEmployeeAccess()`: staff see themselves, supervisors their department, admin/HR anyone.

*   **Reassessment cadence is two-tier.** A per-assessment `next_assessment_due` overrides the
    type-level `competencies.reassessment_months`, and the page says which applied — *set by assessor*
    or *every N mo* — because "due in March" is not actionable without knowing whether somebody chose
    that date or an interval produced it.
*   Only the latest sitting of each competency is shown, so an old score does not sit next to a newer
    one and read as two open gaps.

### Added — migration `..._000130_add_reassessment_and_course_linkage`

*   `credential_types` — reference table with `reassessment_months`. **Created but not yet referenced
    by application code**; it is listed in the architecture doc's dead-schema note so a schema dump
    does not look like it contradicts the docs. 📋
*   `competencies.reassessment_months` — nullable, the type-level cadence above.
*   `course_competencies` — the course↔competency linkage, unique on the pair, `course_id` cascading
    on delete.

### Fixed

*   **`route('notifications.read-all')` did not exist.** The bell's "Mark all read" button was wired to
    it in `layouts/hims.blade.php` while no such route was ever registered — a `RouteNotFoundException`
    on every one of the 50 pages extending the shell. Route added; see the new guard test below.
*   **The AI grounding block told the model a falsehood about its own new feature.** `HimsKnowledge`
    was updated for the bell when the scan landed, but described only two alert kinds and stated flatly
    that notifications are *"not raised in response to anything a user does, so nothing appears the
    instant somebody acts."* `LearningController::verifyCpd()` notifies immediately on approval, so an
    employee asking *"will I know when my CPD is approved?"* would have been told, confidently, no.
    The block now carries all three kinds and marks CPD verification as the one that fires on an action
    and goes to its owner alone rather than up the escalation chain.

### Added — tests

107 tests, 331 assertions. All 107 pass on MySQL; 103 pass and 4 skip on the sqlite connection
`phpunit.xml` selects — the skips are the four `EmployeeProgressionTest` cases, whose reassessment
query needs `DATE_ADD`, `CURDATE()`, `SUBSTRING_INDEX` and `GROUP_CONCAT`.

*   `tests/Unit/CredentialStatusTest.php` — asserts the PHP and SQL paths agree on **every boundary
    date**, that a credential expiring today is expiring rather than expired, and that the four scopes
    partition the table rather than overlapping. Two implementations of one rule is the shape that
    drifts, and this is the class whose drift produced the double-count above.
*   `tests/Unit/LayoutRouteReferencesTest.php` — scans the shell views for `route('...')` and asserts
    each name is registered. This is the guard for the bug above.

> **Why that test exists at all.** Nothing caught the missing route. The shell renders only when a
> domain view does, every domain view needs MySQL, and phpunit runs on sqlite — so the layout every
> page inherits was entirely untested. A missing route name is a fatal error found by opening any page,
> which makes it worth asserting statically rather than hoping a feature test wanders past it.

*   `tests/Feature/NotificationBellTest.php` — the bell is scoped to the caller's own `employee_id`,
    and `read-all` cannot clear another employee's alerts.
*   `tests/Feature/EmployeeProgressionTest.php` — the row-level scoping above, per role.
*   `tests/Unit/HimsKnowledgeTest.php` — extended to pin the notification boundary in the AI grounding
    block: that the bell exists, that credential and reassessment alerts are scan-driven and escalate to
    supervisor and department head, and that CPD verification is the one alert raised on a user action
    and sent to its owner alone. The block previously told the model no alert ever follows a user
    action, which stopped being true the moment `verifyCpd()` began notifying — and a wrong *negative*
    in that block is the failure mode the whole file exists to prevent, since it sends someone hunting
    the UI for a control they were told is not there.

### Removed

*   `app/Http/Controllers/NotificationController.php` — written during this work, then made redundant
    by the view-composer approach: the bell needs no index route, only somewhere for "Mark all read" to
    POST to, which `routes/web.php` handles inline. It was never routed and never referenced. Deleted
    rather than left to read as a live endpoint.

### Docs

*   `HIMS_ARCHITECTURE_AND_SECURITY.md` — new **§2.9 Notification Subsystem** documenting both tables,
    the escalation chain, and the read path (§2.10/§2.11 renumbered). §2.3 gains the `CredentialStatus`
    derivation. The module access table updated for CPD logging/verification and attendance/feedback.
    `notifications` and `credential_alert_log` removed from the dead-schema note; `credential_types`
    added to it.
*   **§3 and §3.4 reworded so "no audit trail" stops contradicting the new alert ledger.** The claim
    was written when nothing was logged at all. `credential_alert_log` and `notifications` now retain a
    durable record of every warning issued and whether it was read — which is *not* an audit trail, and
    the doc now says exactly where that line falls rather than overstating in either direction.
*   `HIMS_SYSTEM_DOCUMENTATION.md` — §12.4 CPD, §12.5 attendance/feedback, §12.8 progression routes,
    §13 implementation-status table. **§6 and §15 re-counted against the migrations**, which had drifted
    two releases back: the totals said *55 tables + 1 view across 16 migrations* where the migrations
    create **57 + 1 across 17**, and the dead-schema list still counted `notifications` and
    `credential_alert_log` as dead while both are now written by the scan. Corrected to eleven dead
    tables, a live surface of **46 tables + 1 view** (38 domain + 8 framework), with `notifications`,
    `credential_alert_log` and `course_competencies` added to the §15 subsystem rows they belong to.
    Every one of the 57 is now accounted for in exactly one of the two lists — a schema dump and this
    document can be reconciled line by line, which was the whole point of keeping the dead list at all.
*   **Record-level scoping restated as covering four controllers, not three.** Both technical documents
    still named only `EmployeeController`, `PerformanceController` and `GapAnalysisController` as callers
    of `scopeToVisibleEmployees()` / `authorizeEmployeeAccess()`, and both listed `LearningController`
    among the controllers that call *neither* — while this release added five such calls to it. A
    security document that under-reports a control is the safer direction to be wrong in, but it is
    still wrong, and it was steering readers to a module they would have believed unscoped.
*   `CLAUDE.md` — the raw-Query-Builder call-site count restated as **~255** (was ~129; this release
    roughly doubled it), the dead-table count as **eleven** with `notifications` removed and
    `credential_types` put in its place, and `LearningController` added to the record-scoping list.
    This file is read by every agent that touches the repo, so a stale claim here propagates.
*   `HIMS_USER_GUIDE.md` — new **My Development** section; the bell note gains the third alert kind
    (CPD verification) and discloses that credential and reassessment alerts are **not private to
    you**; credential statuses relabelled to the four the UI actually renders (*Active* / *Expiring
    soon* / *Expired* / *No expiry* — "valid" was never on screen).
*   Cadence corrected from "nightly"/"overnight" to a scheduled daily sweep across all four documents
    and the AI grounding block. The default has been 06:30 since `config/hims.php` was added; "nightly"
    was wrong the day it was written.
*   `public/css/hims.css` restated as **1105 lines** and the shell as **50 pages** in `CLAUDE.md` and
    both technical documents. Four of the six places said 742 and ~32/~46 — figures from before the
    notification, CPD, attendance and progression views landed. Small numbers, but they are the ones a
    reader uses to judge whether the rest of the document was written against this version of the code.

---

## v2.6.1 — 2026-08-06

Fix release. The assistant stops inventing parts of the UI that were never built.

### Fixed

**The assistant described features that do not exist.** Asked *"how do I enroll an employee to a
course?"* it produced a confident seven-step flow: open a Course Catalog page, click Enroll, **search
for the employee's name and select them**, **choose an enrolment type (self / manager / admin)**,
**set the enrolment date**, then confirm. Only the first two steps resemble HIMS. The rest describe
controls that have never existed, and the premise is unsupported outright — `LearningController::enroll()`
takes no employee argument at all:

```php
$empId = $this->currentEmployeeId();   // always the signed-in user
'enrolled_by'     => $empId,           // always the signed-in user
'enrollment_date' => now()->toDateString(),
```

Enrolment is one button that enrols whoever clicks it. Nobody — admin included — can enrol another
person. Training registration works the same way.

**Cause.** `AbstractAiProvider::systemContext()` framed the assistant as a hospital-HR helper and
stopped there. It gave the model no knowledge of HIMS itself, and the assistant has neither database
access nor any view of the running UI, so a *"how do I…"* question had nothing to answer from but the
generic LMS conventions in its training data. It answered from those, in the register of someone
reading the screen. A wrong answer of that kind is worse than a refusal: the user goes hunting for an
employee picker that was never built and concludes the system is broken.

**Fix.** New `App\Services\Ai\HimsKnowledge::appGuide()`, appended to the system prompt on every
request (~3.5 KB, ~900 tokens). Verified against `routes/web.php`, the module controllers and the
Blade views, it carries:

*   the real sidebar, and which role may write in each module;
*   the two flows people actually ask about — course enrolment and training registration — with the
    on-screen labels quoted exactly, so directions can be followed literally;
*   a list of what HIMS **does not** have: enrolling anyone but yourself, an employee picker,
    enrolment types, enrolment dates, bulk enrolment, approval steps, progress tracking, quizzes,
    notifications, uploads, certificate generation, a mobile app, an API;
*   answering rules — never invent a page, button, field or step; say so when the answer is unknown;
    and correct the premise first when asked to do something to another employee that HIMS only
    supports for oneself.

> **The negatives are what does the work.** Listing what HIMS has does not stop invention, because
> the model fills whatever gap is left — it will assume an enterprise LMS has an admin enrolment
> path, since almost all of them do. Only an explicit contradiction displaces that. The "does not
> exist" section is the operative half of the guide, and `HimsKnowledgeTest` fails if it thins out.

### Added

*   `tests/Unit/HimsKnowledgeTest.php` — 17 tests, 31 assertions. Pins each claim the guide must keep
    making, checks every sidebar module is covered, requires the "does not have" list to stay at six
    entries or more, and asserts the guide actually reaches the prompt both with and without the
    `AiAccessPolicy` scope fragment. A correct guide that is never sent is the same defect as no guide.

> **Why a test for prompt text.** It is not executable, so nothing breaks when it drifts out of step
> with the app — the assistant just quietly resumes misleading people, and nobody finds out until a
> user follows instructions into a page that has no such button. This carries the same standing
> obligation as `AiAccessPolicy::TOPICS`: when a route, button or permission changes, change the
> guide too.

### Docs

*   `HIMS_USER_GUIDE.md` — course enrolment and training registration now state that **you can only
    enrol/register yourself**, which was the substance of the reported question and was previously
    unstated. §10 gains a caveat that the assistant cannot see the page you are on and that a button
    it names but you cannot find probably does not exist. Two FAQ entries added: how to enrol a member
    of staff (you cannot), and what to do when the assistant describes controls that are not there.
*   `HIMS_ARCHITECTURE_AND_SECURITY.md` §5 — new **Application grounding** row.
*   `HIMS_SYSTEM_DOCUMENTATION.md` §5 and §12.8 — grounding recorded alongside the existing note that
    the assistant has no database access.

---

## v2.6.0 — 2026-08-06

Feature release. The AI assistant becomes a docked sidebar with multiple conversations and working
memory, and gains a role-based boundary on what it will discuss. One new table, one altered table.

### Added — AI assistant is a docked right-hand rail

The floating 🤖 bubble and its pop-up panel are gone. The assistant is now a persistent rail down the
right side of the shell, in the manner of an IDE's secondary sidebar, opened from a 🤖 button in the
topbar next to the notifications and help icons.

*   The page **reflows** rather than being covered: two CSS custom properties drive it — `--hims-ai-w`
    (rail width) and `--hims-ai-offset` (the margin the page gives up, held at 0 when the rail is closed
    or overlaying).
*   **Resizable** by dragging the rail's left edge; the width persists.
*   Below 1100px the rail **overlays** the page instead of pushing it, so narrow screens keep their
    content width.
*   `z-index: 98` — below the topbar dropdowns, so the notifications and help panels still open over it.

Markup lives in the new `resources/views/partials/ai-rail.blade.php`; ~330 lines of rail JS and ~260
lines of CSS were added to `layouts/hims.blade.php` and `public/css/hims.css` respectively.

### Added — Multiple conversations with session-scoped memory

Chat was previously one flat, undivided per-user log: no way to start a new conversation, and no way to
look back at an old one.

*   New table **`ai_chat_sessions`** (`id`, `user_id`, `title`, timestamps), one row per conversation.
    The title is derived from the first question, so an abandoned "New chat" never gets a misleading name.
*   **`ai_chat_messages`** gains `session_id` and `seq`. Existing messages are adopted into one
    "Earlier conversation" session per user, so no history is orphaned.
*   The rail lists conversations most-recent-first, with **New chat**, rename, and delete.

**The stored history is now replayed to the provider.** Before this it was persisted and shown to the
browser but never sent back, so every turn was stateless — a follow-up question like "and how long does
that take?" had no subject. `AiController::query()` now reads the current session's earlier turns
*before* inserting the new question and passes them to `ask()`.

The contract widened to carry it: `AiProvider::ask(string $prompt, array $history = [], ?string $scope = null)`.
All four drivers were updated. Anthropic's system prompt stays a separate top-level `system` field, so
`AnthropicProvider::buildMessages()` remains two-argument.

`AbstractAiProvider::sanitiseHistory()` cleans the transcript before it goes anywhere: it drops replies
HIMS wrote itself — `⚠️` provider failures and `🔒` access refusals — **together with the question each
one stood in for**, forces strict `user`/`ai` alternation (Anthropic rejects consecutive same-role turns
outright), removes a trailing unanswered question, and caps the result by turn count and total
characters, oldest first. Memory is session-scoped and never crosses conversations.

> **Why `session_id` has no foreign key.** Adding one via `Schema::table()` makes Laravel's SQLite
> grammar rebuild the table, and the rebuild reconstructs columns from `BlueprintState`, which does not
> capture CHECK constraints — `role`'s `ENUM('user','ai')` would silently become a plain varchar in the
> test database while MySQL kept a real `ENUM`. The same rebuild copies rows with `pragma foreign_keys`
> off, so an FK MySQL would reject against existing history would still pass green in CI. Ownership is
> enforced in `AiController::ownedSession()` instead, and session deletes remove their messages
> explicitly since there is no cascade to rely on.

> **Why `seq` exists.** `created_at` cannot order a conversation: `AiController` writes one `now()` to
> both the question and the answer row and the column has whole-second precision, so the two halves of a
> turn tie. Invisible while history was only displayed; not survivable once the order is what the model
> reads.

### Security — Subject-matter RBAC on the assistant

The AI routes carry no `role:` middleware — the assistant is deliberately available to every signed-in
user — which left the chat as a potential side door to guidance the rest of the app gates by role. New
`App\Services\Ai\AiAccessPolicy` closes it, in two layers:

1.  **Hard block.** `deniedTopic()` classifies the question against a keyword table; if the asker's role
    does not hold the topic, `refusal()` is returned **instead of** calling the provider. Deterministic,
    ahead of any network call, costs no tokens, and cannot be talked around by the prompt. This is the
    actual control.
2.  **Advisory scope.** `scopeFor()` builds a per-request fragment naming the caller's role and their
    restrictions, appended to the system prompt. It shapes borderline answers the classifier lets
    through. Prompt instructions are advisory and are never the only barrier.

| Topic | Roles permitted |
|---|---|
| User accounts, passwords, system roles | `admin` |
| Succession planning, talent pipeline, readiness | `admin`, `hr_manager`, `supervisor` |
| Other employees' records, salary, disciplinary history | `admin`, `hr_manager`, `supervisor` |
| Department administration | `admin`, `hr_manager` |
| Hospital-wide analytics, attrition, cross-department comparison | `admin`, `hr_manager` |

Performance, competency, learning, training and recognition are absent from the table by design — they
are open to every authenticated role at the route level, so they are never blocked. Topics are tested
most-sensitive-first, so a question touching both accounts and training is judged on accounts. Patterns
cover Tagalog terms where they matter (`sweldo` for salary).

The refusal is prefixed **`🔒`**, deliberately not `⚠️`: that prefix means "the provider failed"
throughout this stack and `CompetencyGapAnalysisService::parseAiJson()` keys on it, whereas a refusal is
a successful, intended outcome. It is stored as a normal `ai` message so the transcript stays honest and
a reload does not make the question look unanswered — and it is excluded from replayed history, or the
model would read its own voice refusing and imitate it for the rest of the conversation.

Because `AiManager` shares each driver as a memoised singleton, the scope is built per request and
passed in rather than stored on the driver; a cached scope would leak one user's role into the next
request and into the gap-analysis service.

### Changed — `AiProvider` contract

`ask()` is now `ask(string $prompt, array $history = [], ?string $scope = null): string`. Both new
parameters default, so the existing one-shot caller (`CompetencyGapAnalysisService`) is unchanged.
`GeminiProvider`, `OpenAiProvider`, `AnthropicProvider` and `NullAiProvider` all implement the new
signature.

### Fixed — Orphaned turns in replayed history

Pre-existing, and made routine by refusals. Dropping a synthetic `⚠️` reply left its question behind;
the alternation rule then dropped the *next* question, re-pairing an old question with a newer answer.
`sanitiseHistory()` now removes a synthetic reply and its question as a unit, and where two questions
run consecutively it drops the **older** — the answer that follows belongs to the most recent question.

### Added — Test coverage

`tests/Feature/AiChatSessionTest.php` grew from 8 tests to **33 (142 assertions)**, all passing. Beyond
the existing cross-user isolation and session-lifecycle cases: memory replay and its absence across
sessions, refusal persistence, refusal-and-question sanitisation, two `#[DataProvider]` tables asserting
ten blocked and ten permitted role/question pairs, that an admin is never blocked, and that the scope is
rebuilt per request rather than cached on the shared singleton.

### Docs

`HIMS_SYSTEM_DOCUMENTATION.md` §5 and §12.8, `HIMS_ARCHITECTURE_AND_SECURITY.md` §2.8/§3.2.1/§5/§6, and
`HIMS_USER_GUIDE.md` §10 updated. Two statements the code had made false were corrected: the system
documentation said the stored history "is never sent back to the provider, so each turn is stateless",
and the user guide said each question "is answered on its own without memory of the previous ones".

---
## v2.5.0-beta.2 — 2026-08-04 (pre-release)

SMTP timeout fix & Brevo HTTPS API mail transport. Password reset now works on Railway without a Pro plan.

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

### Added — Brevo (Sendinblue) HTTPS API mail transport

`symfony/brevo-mailer` is now installed. Setting `MAIL_MAILER=brevo` and `BREVO_API_KEY` in the Railway
dashboard (or `.env`) is all that is needed — **no code change, no SMTP port, no app password**.

Railway (and most PaaS hosts on lower plans) blocks outbound SMTP entirely. The four SMTP presets
(gmail/outlook/yahoo/smtp) all use port 587 and therefore all fail there. Brevo sends via HTTPS (port 443
to `api.brevo.com`), which is open on every plan, so it bypasses the block completely.

*   `config/mail.php` now includes the `brevo` mailer entry.
*   `config/services.php` includes `'brevo' => ['key' => env('BREVO_API_KEY')]`.
*   `.env.example` documents the setup: `MAIL_MAILER=brevo`, `BREVO_API_KEY`, `MAIL_FROM_ADDRESS`.
*   `php artisan hims:mail-test` understands API transports (brevo, postmark, ses) and prints API key
    status and Brevo-specific diagnostics rather than SMTP host/port/username checks.

**Single Sender advantage:** Brevo allows verifying a single personal or work email address in their dashboard
(`https://app.brevo.com/senders`) via an email link, without requiring full DNS domain verification.

### Docs — Railway blocks outbound SMTP below the Pro plan

Not a defect in this codebase, but the reason mail fails on the deployed instance, so it is now documented in
`HIMS_ARCHITECTURE_AND_SECURITY.md` §4.8. Railway firewalls ports 25/465/587/2525 on Free, Trial and Hobby
plans, so all four SMTP presets here fail identically regardless of credentials. The recommended path is now
`MAIL_MAILER=brevo` (see above), which requires only a Brevo account and API key — no code change.

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
