<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The catalogue of things the assistant is allowed to *do*, as opposed to
 * describe.
 *
 * WHY THE ENTRIES CARRY A ROUTE NAME AND NOT A ROLE LIST
 *
 * Executing an action calls the controller method directly, which bypasses the
 * `role:` middleware that normally guards it. The obvious fix — copy the role
 * list into each entry — creates a second source of truth that silently rots
 * the first time someone edits web.php. So the roles are read back off the real
 * route at runtime via gatherMiddleware(). Change the middleware and the
 * assistant's permissions change with it; there is nothing here to forget.
 *
 * A route with no `role:` middleware is open to every signed-in user. That is
 * deliberate and matches the web UI: registering for a training session, logging
 * CPD, and submitting feedback are self-service. Course
 * enrolment is deliberately *not* in that list any more — it is created from
 * Required Training by a supervisor or above, so there is no action here for it.
 *
 * `params` doubles as the field whitelist and as the prompt documentation the
 * planner shows the model, so the descriptions are written for the model to
 * read. Anything not listed here is dropped before the controller sees it —
 * the model cannot smuggle in a column.
 *
 * `resolve` marks params whose value arrives as a human name and has to become
 * a UUID (see AiEntityResolver). `uri` lists route segments in positional
 * order. `table`/`pk` are used for the audit row and for the best-effort
 * lookup of a freshly created id.
 */
final class AiActionRegistry
{
    private const ACTIONS = [
        // ── Performance ──────────────────────────────────────────
        'performance.cycle.create' => [
            'route' => 'performance.cycles.store',
            'label' => 'Create a performance review cycle',
            'table' => 'review_cycles',
            'pk' => 'cycle_id',
            'params' => [
                'cycle_name' => 'name, e.g. "2027 Annual Performance Review"',
                'cycle_type' => 'annual | semi_annual | quarterly | probationary',
                'start_date' => 'YYYY-MM-DD',
                'end_date' => 'YYYY-MM-DD, later than start_date',
            ],
        ],
        'performance.cycle.update' => [
            'route' => 'performance.cycles.update',
            'label' => 'Change a review cycle (including opening or closing it)',
            'table' => 'review_cycles',
            'pk' => 'cycle_id',
            'uri' => ['id'],
            'resolve' => ['id' => 'cycle'],
            'params' => [
                'cycle_name' => 'name',
                'cycle_type' => 'annual | semi_annual | quarterly | probationary',
                'start_date' => 'YYYY-MM-DD',
                'end_date' => 'YYYY-MM-DD, later than start_date',
                'status' => 'planned | active | closed | archived',
            ],
        ],
        'performance.review.create' => [
            'route' => 'performance.reviews.store',
            'label' => 'Start a performance review for an employee in a cycle',
            'table' => 'performance_reviews',
            'pk' => 'review_id',
            'resolve' => ['employee_id' => 'employee', 'cycle_id' => 'cycle', 'reviewer_id' => 'employee'],
            'params' => [
                'employee_id' => 'the employee being reviewed, by name or code',
                'cycle_id' => 'the review cycle, by name',
                'reviewer_id' => 'optional reviewer, by name',
                'review_type' => 'standard | probationary | promotion',
            ],
        ],
        'performance.review.status' => [
            'route' => 'performance.reviews.score.save',
            'label' => 'Mark a review draft or finished, or write its summary text',
            'table' => 'performance_reviews',
            'pk' => 'review_id',
            'uri' => ['id'],
            'params' => [
                'status' => 'draft | finished',
                'strengths_text' => 'optional summary of strengths',
                'improvements_text' => 'optional summary of areas to improve',
            ],
        ],

        // ── Competency ───────────────────────────────────────────
        'competency.assessment.create' => [
            'route' => 'competency.assessments.store',
            'label' => 'Record a competency assessment (the gap is computed by the database)',
            'table' => 'competency_assessments',
            'pk' => 'assessment_id',
            'resolve' => ['employee_id' => 'employee', 'competency_id' => 'competency'],
            'params' => [
                'employee_id' => 'the employee, by name or code',
                'competency_id' => 'the competency, by name',
                'current_proficiency' => 'integer 1-5',
                'assessment_method' => 'observation | self_assessment | supervisor_rating | practical_test | written_exam',
                'assessed_date' => 'YYYY-MM-DD, defaults to today',
                'next_assessment_due' => 'YYYY-MM-DD, optional',
                'notes' => 'optional free text',
            ],
        ],
        'competency.credential.create' => [
            'route' => 'competency.credentials.store',
            'label' => 'Record a licence or credential for an employee',
            'table' => 'employee_credentials',
            'pk' => 'credential_id',
            'resolve' => ['employee_id' => 'employee'],
            'params' => [
                'employee_id' => 'the employee, by name or code',
                'credential_type' => 'e.g. "PRC Licence", "BLS Certification"',
                'credential_number' => 'optional',
                'issuing_body' => 'optional',
                'issue_date' => 'YYYY-MM-DD, optional',
                'expiry_date' => 'YYYY-MM-DD, optional, not before issue_date',
            ],
        ],
        'competency.domain.create' => [
            'route' => 'competency.domains.store',
            'label' => 'Create a competency domain',
            'table' => 'competency_domains',
            'pk' => 'domain_id',
            'params' => [
                'domain_name' => 'unique name',
                'description' => 'optional',
            ],
        ],
        // ── Learning ─────────────────────────────────────────────
        'learning.course.create' => [
            'route' => 'learning.courses.store',
            'label' => 'Create a course in the catalogue',
            'table' => 'courses',
            'pk' => 'course_id',
            'params' => [
                'title' => 'course title',
                'category' => 'e.g. "Clinical", "Safety", "Leadership"',
                'cpd_hours' => 'number of CPD hours, 0 or more',
                'difficulty_level' => 'beginner | intermediate | advanced',
            ],
        ],
        'learning.pathway.create' => [
            'route' => 'learning.pathways.store',
            'label' => 'Create a learning pathway',
            'table' => 'learning_pathways',
            'pk' => 'pathway_id',
            'params' => [
                'pathway_name' => 'pathway name',
                'description' => 'optional',
                'total_cpd_hours' => 'optional number, up to 9999.9',
            ],
        ],
        'learning.course.competencies' => [
            'route' => 'learning.courses.competencies.update',
            'label' => 'Set which competencies a course teaches (replaces the existing tags)',
            'table' => 'course_competencies',
            'pk' => 'course_id',
            'uri' => ['courseId'],
            'resolve' => ['courseId' => 'course', 'competencies' => 'competency[]'],
            'params' => [
                'competencies' => 'list of competency names the course covers',
            ],
        ],
        'learning.cpd.log' => [
            'route' => 'learning.cpd.store',
            'label' => 'Log a CPD activity',
            'table' => 'cpd_records',
            'pk' => 'cpd_id',
            'resolve' => ['employee_id' => 'employee'],
            'params' => [
                'employee_id' => 'admin/HR only, by name; otherwise leave out and it files against you',
                'source_type' => 'course | training | external',
                'activity_name' => 'what the activity was',
                'cpd_hours' => 'number, 0.5 or more',
                'date_earned' => 'YYYY-MM-DD, today or earlier',
            ],
        ],
        'learning.cpd.verify' => [
            'route' => 'learning.cpd.verify',
            'label' => 'Verify a CPD record that is awaiting approval',
            'table' => 'cpd_records',
            'pk' => 'cpd_id',
            'uri' => ['cpdId'],
            'params' => [],
        ],

        // ── Training ─────────────────────────────────────────────
        'training.session.create' => [
            'route' => 'training.sessions.store',
            'label' => 'Schedule a training session',
            'table' => 'training_sessions',
            'pk' => 'session_id',
            'resolve' => ['instructor_id' => 'employee'],
            'params' => [
                'title' => 'session title',
                'category' => 'e.g. "Clinical", "Safety"',
                'instructor_id' => 'the instructor, by name',
                'session_date' => 'YYYY-MM-DD',
                'start_time' => 'HH:MM',
                'end_time' => 'HH:MM, after start_time',
                'capacity' => 'integer, 1 or more',
            ],
        ],
        'training.venue.create' => [
            'route' => 'training.venues.store',
            'label' => 'Add a training venue',
            'table' => 'training_venues',
            'pk' => 'venue_id',
            'params' => [
                'venue_name' => 'venue name',
                'capacity' => 'integer, 1 or more',
                'building' => 'optional',
                'floor' => 'optional',
            ],
        ],
        'training.feedback.submit' => [
            'route' => 'training.sessions.feedback.store',
            'label' => 'Submit your feedback on a session you attended',
            'table' => 'training_feedback',
            'pk' => 'feedback_id',
            'uri' => ['sessionId'],
            'resolve' => ['sessionId' => 'session'],
            'params' => [
                'overall_rating' => 'integer 1-5, required',
                'content_rating' => 'integer 1-5, optional',
                'instructor_rating' => 'integer 1-5, optional',
                'venue_rating' => 'integer 1-5, optional',
                'comments' => 'optional free text',
            ],
        ],
        'training.register' => [
            'route' => 'training.register',
            'label' => 'Register YOURSELF for a training session (cannot register anybody else)',
            'table' => 'training_registrations',
            'pk' => 'registration_id',
            'uri' => ['sessionId'],
            'resolve' => ['sessionId' => 'session'],
            'params' => [],
        ],

        // ── Succession ───────────────────────────────────────────
        'succession.position.create' => [
            'route' => 'succession.positions.store',
            'label' => 'Flag a position as critical for succession planning',
            'table' => 'critical_positions',
            'pk' => 'position_id',
            'resolve' => ['department_id' => 'department'],
            'params' => [
                'position_title' => 'job title',
                'department_id' => 'the department, by name',
                'vacancy_risk' => 'low | medium | high, defaults to medium',
            ],
        ],
        'succession.candidate.create' => [
            'route' => 'succession.candidates.store',
            'label' => 'Nominate an employee as a successor for a critical position',
            'table' => 'succession_candidates',
            'pk' => 'candidate_id',
            'resolve' => ['employee_id' => 'employee', 'position_id' => 'position', 'mentor_id' => 'employee'],
            'params' => [
                'employee_id' => 'the candidate, by name or code',
                'position_id' => 'the critical position, by title',
                'performance_score' => 'integer 1-5',
                'potential_score' => 'integer 1-5',
                'readiness_level' => 'ready_now | 1_2_years | 2_5_years | long_term',
                'mentor_id' => 'optional mentor, by name',
            ],
        ],
        'succession.candidate.update' => [
            'route' => 'succession.candidates.update',
            'label' => 'Re-score a succession candidate',
            'table' => 'succession_candidates',
            'pk' => 'candidate_id',
            'uri' => ['id'],
            'resolve' => ['mentor_id' => 'employee'],
            'params' => [
                'performance_score' => 'integer 1-5',
                'potential_score' => 'integer 1-5',
                'readiness_level' => 'ready_now | 1_2_years | 2_5_years | long_term',
                'mentor_id' => 'optional mentor, by name',
            ],
        ],
        'succession.candidate.withdraw' => [
            'route' => 'succession.candidates.withdraw',
            'label' => 'Withdraw a succession candidate',
            'table' => 'succession_candidates',
            'pk' => 'candidate_id',
            'uri' => ['id'],
            'destructive' => true,
            'params' => [],
        ],
        'succession.milestone.create' => [
            'route' => 'succession.milestones.store',
            'label' => "Add a development milestone to a candidate's path",
            'table' => 'leadership_development_paths',
            'pk' => 'path_id',
            'uri' => ['id'],
            'params' => [
                'milestone_title' => 'what the milestone is',
                'milestone_type' => 'course | assignment | mentoring | rotation | certification | project',
                'description' => 'optional',
                'target_date' => 'YYYY-MM-DD, optional',
            ],
        ],
        'succession.milestone.update' => [
            'route' => 'succession.milestones.update',
            'label' => 'Change a development milestone status',
            'table' => 'leadership_development_paths',
            'pk' => 'path_id',
            'uri' => ['id', 'pathId'],
            'params' => ['status' => 'not_started | in_progress | completed'],
        ],
        'succession.milestone.delete' => [
            'route' => 'succession.milestones.destroy',
            'label' => 'Remove a development milestone',
            'table' => 'leadership_development_paths',
            'pk' => 'path_id',
            'uri' => ['id', 'pathId'],
            'destructive' => true,
            'params' => [],
        ],

        // ── Employees / Departments / Users ─────────────────────
        'employee.create' => [
            'route' => 'employees.store',
            'label' => 'Add an employee to the system',
            'table' => 'employees',
            'pk' => 'employee_id',
            'resolve' => ['department_id' => 'department'],
            'params' => [
                'first_name' => 'first name',
                'last_name' => 'last name',
                'email' => 'email address, must be unique',
                'department_id' => 'department, by name',
                'role_id' => 'role id (ask if unsure)',
                'hire_date' => 'YYYY-MM-DD',
            ],
        ],
        'employee.update' => [
            'route' => 'employees.update',
            'label' => 'Edit an employee record',
            'table' => 'employees',
            'pk' => 'employee_id',
            'uri' => ['id'],
            'resolve' => ['department_id' => 'department', 'supervisor_id' => 'employee'],
            'params' => [
                'first_name' => 'first name',
                'last_name' => 'last name',
                'email' => 'email, must be unique',
                'department_id' => 'department, by name',
                'role_id' => 'role id',
                'hire_date' => 'YYYY-MM-DD',
                'employment_status' => 'active | probationary | on_leave | suspended | terminated | resigned',
                'supervisor_id' => 'optional supervisor, by name',
            ],
        ],
        'employee.delete' => [
            'route' => 'employees.destroy',
            'label' => 'Delete an employee record',
            'table' => 'employees',
            'pk' => 'employee_id',
            'uri' => ['id'],
            'destructive' => true,
            'params' => [],
        ],
        'department.create' => [
            'route' => 'departments.store',
            'label' => 'Create a department',
            'table' => 'departments',
            'pk' => 'department_id',
            'params' => [
                'name' => 'department name, must be unique',
                'department_code' => 'optional code',
            ],
        ],
        'user.create' => [
            'route' => 'users.store',
            'label' => 'Create a login account',
            'table' => 'users',
            'pk' => 'id',
            'resolve' => ['employee_id' => 'employee'],
            'mirror' => ['password' => 'password_confirmation'],
            'params' => [
                'name' => 'account name',
                'email' => 'email, must be unique',
                'password' => 'plain password, 8 characters or more',
                'role' => 'admin | hr_manager | supervisor | staff',
                'employee_id' => 'optional employee to link, by name',
            ],
        ],
        'user.update' => [
            'route' => 'users.update',
            'label' => 'Edit a login account',
            'table' => 'users',
            'pk' => 'id',
            'uri' => ['user'],
            'resolve' => ['employee_id' => 'employee'],
            'params' => [
                'name' => 'account name',
                'email' => 'email, must be unique',
                'role' => 'admin | hr_manager | supervisor | staff',
                'employee_id' => 'optional employee to link, by name',
            ],
        ],
        'user.delete' => [
            'route' => 'users.destroy',
            'label' => 'Delete a login account',
            'table' => 'users',
            'pk' => 'id',
            'uri' => ['user'],
            'destructive' => true,
            'params' => [],
        ],
    ];

    /**
     * The actions this user is allowed to perform, filtered by their role and
     * the route middleware that ordinarily guards each write route.
     *
     * @return array<string, array> Subset of ACTIONS, keyed by action key
     */
    public static function availableTo(User $user): array
    {
        $role = $user->role;
        $available = [];

        foreach (self::ACTIONS as $key => $spec) {
            $route = Route::getRoutes()->getByName($spec['route']);

            // An unrouted or missing route is a misconfiguration — it cannot be
            // invoked, so exclude it rather than letting the executor abort.
            if (! $route) {
                continue;
            }

            if (! self::rolePasses($route->gatherMiddleware(), $role)) {
                continue;
            }

            $available[$key] = $spec;
        }

        return $available;
    }

    /**
     * Does this role satisfy EVERY `role:` middleware on the route?
     *
     * A route can carry more than one. The employee routes are the case that
     * matters: the prefix group applies role:admin,hr_manager,supervisor and
     * then employees.destroy narrows it to role:admin,hr_manager. Laravel runs
     * both, so a supervisor is refused — and this must match that behaviour.
     * Reading only the first would hand supervisors the delete action.
     *
     * No `role:` middleware at all means every signed-in user may perform it.
     */
    private static function rolePasses(array $middleware, ?string $role): bool
    {
        foreach ($middleware as $m) {
            if (! str_starts_with($m, 'role:')) {
                continue;
            }

            if (! in_array($role, explode(',', substr($m, 5)), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The permitted actions rendered for the planner prompt: one line per
     * action, then its parameters. This is the only place the model learns what
     * it may call, so an action absent here cannot be invoked.
     */
    public static function catalogueFor(User $user): string
    {
        $lines = [];

        foreach (self::availableTo($user) as $key => $spec) {
            $flag = ($spec['destructive'] ?? false) ? ' [DESTRUCTIVE]' : '';
            $lines[] = "- {$key}{$flag}: {$spec['label']}";

            foreach ($spec['uri'] ?? [] as $seg) {
                $lines[] = "    {$seg}: which record to act on, by name";
            }

            foreach ($spec['params'] as $name => $desc) {
                $lines[] = "    {$name}: {$desc}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Single-action lookup, or null if the key is unknown or the user lacks
     * permission.
     */
    public static function get(string $key, User $user): ?array
    {
        $available = self::availableTo($user);

        return $available[$key] ?? null;
    }
}
