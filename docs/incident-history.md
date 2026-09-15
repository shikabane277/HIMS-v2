# Incident history

Narratives behind the rules in `.claude/rules/` and `CLAUDE.md`. Each entry is a bug that actually shipped; the rule it produced lives in the scoped rules file, and this file exists so the rule can stay short without losing the reason it was written.

Read this when a rule looks arbitrary, when you are tempted to relax one, or when debugging something that smells familiar.

---

## The development database was destroyed by the test suite

**2026-08-11.** Someone ran `php artisan test --env=testing`. There is no `.env.testing`, so Laravel fell back to `.env` — where `DB_DATABASE=hims_v2`, the *development* database. `RefreshDatabase` on a non-sqlite connection runs `migrate:fresh`, which dropped and recreated every table.

Recovered by binlog point-in-time replay.

Why the flag looked safe: `--env=testing` reads as "use the test environment", and on most Laravel projects it is. The trap is the missing file — the flag names an environment that does not exist, and Laravel does not treat that as an error.

Why plain `php artisan test` is safe: `phpunit.xml` pins sqlite `:memory:`.

Why the scratch-database override works at all: `phpunit.xml`'s `<env>` entries are **not** `force="true"`, so a shell-exported value wins. That is the mechanism behind `DB_CONNECTION=mysql DB_DATABASE=hims_align_check php artisan test` — and also why a stale export left in a shell is dangerous.

Rule: `.claude/rules/testing.md`.

---

## beta.3 shipped three unrelated-looking bug reports from one stale CSS file

`public/css/hims.css` is served outside the Vite build, so its URL is constant forever. The deployed origin sends `Cache-Control: max-age=14400` and Cloudflare sits in front. For four hours after the release, the browser *and* the shared edge cache served the previous stylesheet against the new container's HTML.

The page did not go unstyled — which is exactly what made it hard to diagnose. Everything that existed before the release still looked right. Only markup whose classes were new rendered bare:

- `.hims-tabs` — the Learning and Recognition tab strips rendered as underlined links.
- `.hims-modal` — no modal styling.
- `.hims-checklist` — no checklist styling.
- `#ai-rail` — with no `position: fixed` reaching it, the rail laid out at the foot of the document, so `openRail()`'s closing `input.focus()` scrolled to the bottom of the page instead of opening a panel.

Three tickets, one cause. Fix: both unbuilt static assets now append their own content hash, `substr(md5_file($path), 0, 8)`, at `partials/app-css.blade.php:31` and `partials/favicon.blade.php:27`, each `is_file()`-guarded so a checkout missing the asset renders rather than throws.

`Unit\AssetCacheBustingContractTest` pins the hash to the running file's actual md5 — a hardcoded `?v=2` looks identical in the HTML and dies the first time someone edits the CSS without bumping it.

Rule: `.claude/rules/blade-ui.md`.

---

## Three pages 500'd at once from search anchors one loop too high

`GlobalSearchController` builds row-level destinations by appending `#cpd-`-style fragment prefixes to a route, so some view must render `id="cpd-{{ $cpd->cpd_id }}"`. Three views rendered the anchor **above** the loop that binds the variable — on the `<thead>` row or on the wrapper:

- `learning/cpd/index`
- `competency/domains/show`
- `performance/show`

Blade compiles `{{ $cpd->cpd_id }}` to a bare PHP variable read. Outside the loop the variable is undefined, which is a fatal `ErrorException` — a **500 on every visit**, not an empty string and not a missing scroll.

Fix: `Unit\SearchAnchorContractTest` scrapes the fragment prefixes straight out of `GlobalSearchController`, requires each to have a view that renders it, and walks upward from every anchor site to the nearest `@foreach`/`@forelse` to assert the id interpolates *that* loop's variable. Its third test is a floor (≥8 known anchors) so a regex that silently stops matching cannot pass as a clean scan.

Rule: `.claude/rules/services.md`.

---

## Two stale-column 500s that every sqlite test passed

**Portable SQL is not the same as correct SQL, and sqlite will not tell you.**

### `jci_standard_code` selected off the wrong table

The accreditation report shipped with `jci_standard_code` selected off `competencies`, where the column does not exist. Every sqlite test passed because none of them put a competency row in front of the query, so the failing branch never ran. It surfaced only as a MySQL 500.

The tag lives on **`competency_categories`** — one join above the competency. The same join is needed in `ComplianceController::competencyRollup()` and `SuccessionController::readinessEvidence()`.

Lesson: when a query joins a column you have not personally read out of the migration, check the migration.

### `credential_name` printed from a `SELECT *`

`employees/progression.blade.php` printed `$cr->credential_name` from a `select *` on `employee_credentials`, where the column is **`credential_type`**. Nothing in the query complained — `select *` has no column list to be wrong about — and the page 500'd inside the `@foreach`, but only for an employee who actually had a credential.

All four `EmployeeProgressionTest` cases that existed then seeded employees with **no** credentials, so the loop body had never executed in a test and the run stayed green. A fifth case, `test_progression_view_renders_credentials()`, was added specifically to enter it — which is why that class now counts five.

Lesson: **a `@foreach` whose body a test never enters is untested code**, whatever the coverage of the surrounding page. When adding a table to a view, seed at least one row of it.

The credential display name is `credential_type` everywhere — `employees/show`, `competency/credentials/index`, `competency/gap-analysis/employee` and all four dashboard partials agreed; the progression page was the lone outlier.

Rule: `.claude/rules/query-builder.md`.

---

## A green "Met" badge on a skill nobody had ever been assessed on

`competency/index`'s Department Skills Gap Matrix averaged assessment scores per competency. The query used a **left** join on `competency_assessments`, so a competency nobody had been assessed on came back with `AVG(...) = NULL` — and the view's `?? 0` turned that null into a zero, which the badge logic read as a met threshold. Green badge, untested skill.

Filtering to one department makes that the common case rather than the rare one, because competencies are department-agnostic — there is no `competencies.department_id`, so `?department_id=` narrows which *assessments* are averaged by joining `competency_assessments` → `employees` and filtering on the employee's department.

Fix: the join is now **inner**, so never-assessed competencies are excluded from the matrix entirely rather than appearing as passes.

Rule: `.claude/rules/blade-ui.md`.

---

## Modals centred in the middle of the page instead of the viewport

Modal backdrops rendered inside `<main>` resolved `inset: 0` against the full page content box rather than the viewport, centring the panel in the middle of the *document* — on a long page (Competency), far below the fold.

Cause: the content wrapper inside `<main>` carries `.animate-in`, whose `fadeInUp` keyframes end on `transform: translateY(0)` and are held there by `animation-fill-mode: forwards`. **Any transform other than `none` makes an element the containing block for its `position: fixed` descendants.** So "fixed" stopped meaning fixed.

The CSS was always correct and was not touched. Fix: every modal partial `@push`es *only* its `.hims-modal-backdrop` to the layout's `@stack('modals')`, which sits outside `<main>` — rendering as a direct child of `<body>`.

Symptom to watch for: a new modal that forgets the push looks fine on short pages and drifts on long ones.

Rule: `.claude/rules/blade-ui.md`.

---

## KPI weight percentages summed to 102%

`kpi_library.weight` is a `decimal(3,2)` that only means anything next to the other weights on the same review, because `overall_score` is a weighted mean dividing by their total. The screens print each KPI's quotient — "how much of the final score does this KPI decide?"

Rounding each percentage independently overshoots. Both seeded reviews summed to **102%**, in a column a reader adds up.

Fix: `KpiWeighting::displayShares()` apportions the integers by largest remainder so they total exactly 100. **A screen must call `displayShares()`, never `shares()`.**

Related trap in the same class: the denominator is the *rated* rows, not the attached ones. An unrated KPI is absent from the roll-up, not a zero — `cleanScore()` nulls it, its weight leaves the denominator, and the rest grow to fill the gap.

Rule: `.claude/rules/services.md`.

---

## The AI was asked for evidence while holding three numbers

A review's three free-text fields — `performance_reviews.strengths_text`, `.improvements_text` and every `review_kpi_scores.comments` note — were all being **selected** by `CompetencyGapAnalysisService::performanceSignal()` and then dropped before the prompt heredoc. So the model was asked for `evidence`, `root_causes` and `strengths_to_leverage` while holding three numbers and a cycle name.

Fix: `App\Support\ReviewFeedback`, whose `group()` feeds **both** the page and the prompt — `competency/gap-analysis/employee.blade.php` iterates the array `promptLines()` renders, so a comment cannot reach one consumer without the other. That drift *was* the bug.

Rule: `.claude/rules/services.md`.

---

## Every frozen review showed as outstanding work on the dashboard

A review's editability is derived from its cycle's `end_date`, never from a stored status column — so `performance_reviews.status` only ever holds `draft` or `finished`. `completed` is nobody's to set.

The Pending Reviews tile shipped as `whereNotIn('status', ['completed'])`. Once nothing wrote `completed`, that filter excluded nothing: every frozen review in every ended cycle sat on the tile as outstanding work, while the review screen showed it Completed and refused edits.

Fix: both tiles now join `review_cycles` and go through `CycleStatus::whereNotEnded()`. **A new read that wants "open reviews" must join the cycle** — there is no shortcut through the column, and sqlite will not warn you because the tile is MySQL-gated.

Rule: `.claude/rules/authorization.md`.

---

## A "Weighted" column that applied no weighting

`supervisor_rating` is the plain mean of the KPI ratings; `overall_score` is the same ratings weighted by `kpi_library.weight`. They were labelled "Supervisor Rating" and "Final Score" — two names that read as synonyms — beside a per-KPI **Weighted** column that printed `supervisor_score` verbatim under a heading claiming a weighting it never applied. Identical on all 24 live rows, because `saveScores()` writes `weighted_score` as a straight copy.

Fix: the column is gone from both `performance/show` and `performance/reviews/score`. The labels are now "Average of KPI ratings" and "Final Score / weighted by KPI importance", with a sentence saying why they differ and which one HIMS quotes. The per-KPI cell that replaced it is `KpiWeighting::displayShares()`.

**Only the display went.** `review_kpi_scores.weighted_score` is still written by `saveScores()` and still read by `CompetencyGapAnalysisService` and `competency/gap-analysis/employee`, so the column stays. What was also removed is its entry in `show()`'s named select, which nothing read once the column was dropped from the table — a selected column nothing reads is how the two stale-column 500s above started.

Rule: `.claude/rules/authorization.md`.

---

## Headers centred above left-aligned data, app-wide

The browser default for `th` is `text-align: center` and for `td` it is `left`. Bootstrap's reboot papers over the mismatch with `th { text-align: inherit }` — but **HIMS loads no Bootstrap CSS, only the bootstrap-icons font**, so nothing supplied that. The declaration was simply absent from `.hims-table th`, and every header in the app sat centred above left-aligned data.

One declaration in `hims.css` fixed the shared style. `Unit\TableAlignmentContractTest` asserts it is present *and* that no later rule re-centres it — the responsive blocks restyle `.hims-table th` padding, so a careless addition there could.

Per-column verification was done once as a throwaway headed-Chrome harness, comparing each `th`'s computed `text-align` against its column's `td`s by `cellIndex` across 34 pages: **46 rendered tables, 242 header cells, 0 mismatches.** The harness has since been deleted, so there is nothing to re-run. If you rebuild it, note that Chrome reports the initial value as `start` — normalise `start`→`left` and `end`→`right` before comparing, or every unstyled column reads as a mismatch.

Rule: `.claude/rules/blade-ui.md`.

---

## The app clock was not the hospital clock

All three date-boundary helpers ask "has this date passed?" against `now()->toDateString()`. Under Laravel's stock UTC default that question answered "no" for the first eight hours of every Philippine day — so a cycle that ended yesterday stayed scoreable until 8am, and a credential expiring today read as current.

`config('app.timezone')` is now `Asia/Manila`, and that is an **authorization** setting, not a display one. `Unit\CycleStatusTest::test_the_app_clock_is_the_hospital_clock` pins it, and `phpunit.xml` deliberately does not override the timezone so the assertion is live in the suite.

Rule: `.claude/rules/services.md`.
