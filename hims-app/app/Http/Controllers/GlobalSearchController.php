<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Permission-aware search for the shell search button.
 *
 * This is deliberately an allow-list of searchable records. A database-wide
 * LIKE over every table would eventually expose a confidential column simply
 * because somebody forgot to add it to a deny-list. Every source below joins
 * the same identity/reporting-line boundary used by its owning controller and
 * returns a destination that is already a real, navigable HIMS route.
 */
class GlobalSearchController extends Controller
{
    private const PER_SOURCE_LIMIT = 6;

    private const MAX_RESULTS = 50;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);

        $term = trim($validated['q']);

        if (Str::length($term) < 2) {
            return response()->json(['query' => $term, 'results' => [], 'total' => 0]);
        }

        $user = $request->user();
        $terms = $this->terms($term);
        $results = collect();

        $this->searchNavigation($results, $user, $term, $terms);
        $this->searchEmployees($results, $user, $terms);
        $this->searchPerformance($results, $user, $terms);
        $this->searchCompetency($results, $user, $terms);
        $this->searchLearning($results, $user, $terms);
        $this->searchTraining($results, $terms);
        $this->searchRecognition($results, $user, $terms);
        $this->searchSuccession($results, $user, $terms);
        $this->searchAdministration($results, $user, $terms);
        $this->searchAiHistory($results, $user, $terms);

        $results = $results->take(self::MAX_RESULTS)->values();

        return response()->json([
            'query' => $term,
            'results' => $results,
            'total' => $results->count(),
        ]);
    }

    /** @return array<int, string> */
    private function terms(string $term): array
    {
        return collect(preg_split('/\s+/u', Str::lower($term)) ?: [])
            ->filter()
            ->take(8)
            ->values()
            ->all();
    }

    /** @param array<int, string> $terms */
    private function matchesTerms(string $haystack, array $terms): bool
    {
        $haystack = Str::lower($haystack);

        foreach ($terms as $term) {
            if (! Str::contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, string> $terms */
    private function applySearch($query, array $columns, array $terms): void
    {
        foreach ($terms as $term) {
            $pattern = '%'.$term.'%';

            $query->where(function ($nested) use ($columns, $pattern) {
                $nested->where($columns[0], 'like', $pattern);

                foreach (array_slice($columns, 1) as $column) {
                    $nested->orWhere($column, 'like', $pattern);
                }
            });
        }
    }

    private function pushResult(Collection $results, array $result): void
    {
        if ($results->contains(fn ($existing) => $existing['id'] === $result['id'])) {
            return;
        }

        $results->push($result);
    }

    /** @param array<int, string> $terms */
    private function searchNavigation(Collection $results, User $user, string $term, array $terms): void
    {
        $pages = [
            ['id' => 'page:dashboard', 'group' => 'Pages', 'title' => 'Dashboard',
                'subtitle' => 'Organisation overview and activity', 'url' => route('dashboard'), 'icon' => 'bi-speedometer2', 'keywords' => 'home overview'],
            ['id' => 'page:development', 'group' => 'Pages', 'title' => 'My Development',
                'subtitle' => 'Your competencies, learning, credentials, and progress', 'url' => route('employees.progression.mine'), 'icon' => 'bi-graph-up-arrow', 'keywords' => 'employee profile growth'],
            ['id' => 'page:performance', 'group' => 'Pages', 'title' => 'Performance',
                'subtitle' => 'Reviews, goals, and review cycles', 'url' => route('performance.index'), 'icon' => 'bi-clipboard2-check', 'keywords' => 'reviews goals appraisal'],
            ['id' => 'page:reviews', 'group' => 'Pages', 'title' => 'Performance Reviews',
                'subtitle' => 'Review records you are allowed to see', 'url' => route('performance.reviews.index'), 'icon' => 'bi-journal-check', 'keywords' => 'review status reviewer'],
            ['id' => 'page:competency', 'group' => 'Pages', 'title' => 'Competency',
                'subtitle' => 'Skills framework, assessments, and gaps', 'url' => route('competency.index'), 'icon' => 'bi-bullseye', 'keywords' => 'skills framework assessments'],
            ['id' => 'page:credentials', 'group' => 'Pages', 'title' => 'Credentials & Licenses',
                'subtitle' => 'Professional credentials and expiry status', 'url' => route('competency.credentials.index'), 'icon' => 'bi-patch-check', 'keywords' => 'licenses certifications expiry'],
            ['id' => 'page:learning', 'group' => 'Pages', 'title' => 'Learning',
                'subtitle' => 'Course catalogue and learning pathways', 'url' => route('learning.index'), 'icon' => 'bi-book', 'keywords' => 'courses catalogue training'],
            ['id' => 'page:cpd', 'group' => 'Pages', 'title' => 'My CPD',
                'subtitle' => 'Continuing professional development log', 'url' => route('learning.cpd.index'), 'icon' => 'bi-award', 'keywords' => 'continuing professional development hours'],
            ['id' => 'page:pathways', 'group' => 'Pages', 'title' => 'Learning Pathways',
                'subtitle' => 'Role-based course sequences', 'url' => route('learning.pathways.index'), 'icon' => 'bi-signpost-split', 'keywords' => 'pathway development'],
            ['id' => 'page:sessions', 'group' => 'Pages', 'title' => 'Training Sessions',
                'subtitle' => 'Scheduled sessions and registration', 'url' => route('training.index'), 'icon' => 'bi-calendar-event', 'keywords' => 'training calendar sessions'],
            ['id' => 'page:venues', 'group' => 'Pages', 'title' => 'Training Venues',
                'subtitle' => 'Rooms and facilities used for training', 'url' => route('training.venues.index'), 'icon' => 'bi-geo-alt', 'keywords' => 'rooms facilities'],
            ['id' => 'page:recognition', 'group' => 'Pages', 'title' => 'Recognition',
                'subtitle' => 'Public and private appreciation', 'url' => route('recognition.index'), 'icon' => 'bi-stars', 'keywords' => 'wall appreciation badges'],
            ['id' => 'page:profile', 'group' => 'Pages', 'title' => 'My Profile',
                'subtitle' => 'Account details and password', 'url' => route('profile.edit'), 'icon' => 'bi-person-circle', 'keywords' => 'account settings'],
        ];

        if ($user->can('run-gap-analysis')) {
            $pages[] = ['id' => 'page:gap-analysis', 'group' => 'Pages', 'title' => 'AI Gap Analysis',
                'subtitle' => 'Competency gap analysis', 'url' => route('competency.gap.index'), 'icon' => 'bi-robot', 'keywords' => 'skills gaps ai'];
        }

        if ($user->can('view-compliance')) {
            $pages[] = ['id' => 'page:required-training', 'group' => 'Pages', 'title' => 'Required Training',
                'subtitle' => 'Mandatory assignments and completion', 'url' => route('learning.assignments.index'), 'icon' => 'bi-clipboard-check', 'keywords' => 'compliance mandatory assignments'];
            $pages[] = ['id' => 'page:renewals', 'group' => 'Pages', 'title' => 'Renewals',
                'subtitle' => 'Credential and CPD renewal risk', 'url' => route('learning.renewals.index'), 'icon' => 'bi-arrow-repeat', 'keywords' => 'renewal cycle risk'];
            $pages[] = ['id' => 'page:accreditation', 'group' => 'Pages', 'title' => 'Learning Reports',
                'subtitle' => 'Accreditation and compliance reports', 'url' => route('learning.accreditation'), 'icon' => 'bi-file-earmark-text', 'keywords' => 'reports accreditation'];
        }

        if ($user->can('view-succession')) {
            $pages[] = ['id' => 'page:succession', 'group' => 'Pages', 'title' => 'Succession',
                'subtitle' => 'Critical positions and named candidates', 'url' => route('succession.index'), 'icon' => 'bi-diagram-3', 'keywords' => 'talent pipeline readiness'];
        }

        if ($user->can('view-employees')) {
            $pages[] = ['id' => 'page:employees', 'group' => 'Pages', 'title' => 'Employees',
                'subtitle' => 'Directory within your access scope', 'url' => route('employees.index'), 'icon' => 'bi-people', 'keywords' => 'staff directory people'];
        }

        if ($user->can('manage-departments')) {
            $pages[] = ['id' => 'page:departments', 'group' => 'Pages', 'title' => 'Departments',
                'subtitle' => 'Hospital department directory', 'url' => route('departments.index'), 'icon' => 'bi-building', 'keywords' => 'organisation teams'];
        }

        if ($user->can('manage-users')) {
            $pages[] = ['id' => 'page:users', 'group' => 'Pages', 'title' => 'Users & Access',
                'subtitle' => 'Accounts, roles, and access control', 'url' => route('users.index'), 'icon' => 'bi-shield-lock', 'keywords' => 'accounts permissions roles'];
        }

        if ($user->can('view-audit-history')) {
            $pages[] = ['id' => 'page:audit', 'group' => 'Pages', 'title' => 'Audit History',
                'subtitle' => 'Accountable changes and exception records', 'url' => route('audit.history'), 'icon' => 'bi-clock-history', 'keywords' => 'audit log history'];
        }

        foreach ($pages as $page) {
            if (! $this->matchesTerms($page['title'].' '.$page['subtitle'].' '.$page['keywords'], $terms)) {
                continue;
            }

            $this->pushResult($results, [
                'id' => $page['id'],
                'group' => $page['group'],
                'type' => 'page',
                'title' => $page['title'],
                'subtitle' => $page['subtitle'],
                'url' => $page['url'],
                'icon' => $page['icon'],
            ]);
        }
    }

    /** @param array<int, string> $terms */
    private function searchEmployees(Collection $results, User $user, array $terms): void
    {
        $query = DB::table('employees as e')
            ->join('departments as d', 'e.department_id', '=', 'd.department_id')
            ->join('roles as r', 'e.role_id', '=', 'r.role_id')
            ->select('e.employee_id', 'e.first_name', 'e.last_name', 'e.employee_code', 'e.position_title', 'd.name as department_name', 'r.role_name');

        $this->applySearch($query, [
            'e.first_name', 'e.last_name', 'e.employee_code', 'e.email', 'e.position_title', 'd.name', 'r.role_name',
        ], $terms);

        $employees = $this->scopeToVisibleEmployees($query, 'e.employee_id')
            ->orderBy('e.last_name')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get();

        foreach ($employees as $employee) {
            $name = trim($employee->first_name.' '.$employee->last_name);
            $isDirectoryUser = $user->hasRole('admin', 'hr_manager', 'supervisor');

            $this->pushResult($results, [
                'id' => 'employee:'.$employee->employee_id,
                'group' => 'People',
                'type' => 'employee',
                'title' => $name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $employee->position_title,
                    $employee->department_name,
                    $employee->employee_code,
                ]))),
                'url' => $isDirectoryUser
                    ? route('employees.show', $employee->employee_id)
                    : route('employees.progression.mine'),
                'icon' => 'bi-person',
            ]);
        }
    }

    /** @param array<int, string> $terms */
    private function searchPerformance(Collection $results, User $user, array $terms): void
    {
        $cycles = DB::table('review_cycles')
            ->select('cycle_id', 'cycle_name', 'cycle_type', 'status', 'start_date', 'end_date');
        $this->applySearch($cycles, ['cycle_name', 'cycle_type', 'status'], $terms);

        foreach ($cycles->orderByDesc('start_date')->limit(self::PER_SOURCE_LIMIT)->get() as $cycle) {
            $this->pushResult($results, [
                'id' => 'cycle:'.$cycle->cycle_id,
                'group' => 'Performance',
                'type' => 'review_cycle',
                'title' => $cycle->cycle_name,
                'subtitle' => ucfirst(str_replace('_', ' ', $cycle->cycle_type)).' cycle · '.ucfirst($cycle->status),
                'url' => route('performance.cycles.show', $cycle->cycle_id),
                'icon' => 'bi-calendar2-check',
            ]);
        }

        $actor = $user->employee_id;

        if (! $actor) {
            return;
        }

        $reviews = DB::table('performance_reviews as pr')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->leftJoin('employees as reviewer', 'pr.reviewer_id', '=', 'reviewer.employee_id')
            ->where(function ($query) use ($actor) {
                $query->where('pr.employee_id', $actor)
                    ->orWhere('pr.reviewer_id', $actor)
                    ->orWhere('e.supervisor_id', $actor);
            })
            ->select(
                'pr.review_id', 'pr.status', 'pr.review_type', 'pr.employee_id',
                'e.first_name as employee_first', 'e.last_name as employee_last',
                'rc.cycle_name', 'reviewer.first_name as reviewer_first', 'reviewer.last_name as reviewer_last'
            );

        $this->applySearch($reviews, [
            'e.first_name', 'e.last_name', 'rc.cycle_name', 'pr.status', 'pr.review_type',
            'reviewer.first_name', 'reviewer.last_name',
        ], $terms);

        foreach ($reviews->orderByDesc('pr.updated_at')->limit(self::PER_SOURCE_LIMIT)->get() as $review) {
            $employeeName = trim($review->employee_first.' '.$review->employee_last);
            $reviewerName = trim(($review->reviewer_first ?? '').' '.($review->reviewer_last ?? ''));

            $this->pushResult($results, [
                'id' => 'review:'.$review->review_id,
                'group' => 'Performance',
                'type' => 'performance_review',
                'title' => 'Review for '.$employeeName,
                'subtitle' => trim(implode(' · ', array_filter([
                    $review->cycle_name,
                    ucfirst(str_replace('_', ' ', $review->status)),
                    $reviewerName ? 'Reviewer: '.$reviewerName : null,
                ]))),
                'url' => route('performance.show', $review->review_id),
                'icon' => 'bi-journal-check',
            ]);
        }

        $goals = DB::table('review_goals as goal')
            ->join('performance_reviews as pr', 'goal.review_id', '=', 'pr.review_id')
            ->join('employees as e', 'pr.employee_id', '=', 'e.employee_id')
            ->join('review_cycles as rc', 'pr.cycle_id', '=', 'rc.cycle_id')
            ->where(function ($query) use ($actor) {
                $query->where('pr.employee_id', $actor)
                    ->orWhere('pr.reviewer_id', $actor)
                    ->orWhere('e.supervisor_id', $actor);
            })
            ->select('goal.goal_id', 'goal.goal_title', 'goal.goal_description', 'goal.status', 'goal.review_id',
                'e.first_name as employee_first', 'e.last_name as employee_last', 'rc.cycle_name');

        $this->applySearch($goals, [
            'goal.goal_title', 'goal.goal_description', 'goal.status', 'e.first_name', 'e.last_name', 'rc.cycle_name',
        ], $terms);

        foreach ($goals->orderByDesc('goal.updated_at')->limit(self::PER_SOURCE_LIMIT)->get() as $goal) {
            $this->pushResult($results, [
                'id' => 'goal:'.$goal->goal_id,
                'group' => 'Performance',
                'type' => 'development_goal',
                'title' => $goal->goal_title ?: Str::limit((string) $goal->goal_description, 80),
                'subtitle' => trim($goal->cycle_name.' · '.trim($goal->employee_first.' '.$goal->employee_last)),
                'url' => route('performance.show', $goal->review_id).'#review-goal-'.$goal->goal_id,
                'icon' => 'bi-flag',
            ]);
        }
    }

    /** @param array<int, string> $terms */
    private function searchCompetency(Collection $results, User $user, array $terms): void
    {
        $domains = DB::table('competency_domains')
            ->select('domain_id', 'domain_name', 'description');
        $this->applySearch($domains, ['domain_name', 'description'], $terms);

        foreach ($domains->orderBy('domain_name')->limit(self::PER_SOURCE_LIMIT)->get() as $domain) {
            $this->pushResult($results, [
                'id' => 'domain:'.$domain->domain_id,
                'group' => 'Competency',
                'type' => 'competency_domain',
                'title' => $domain->domain_name,
                'subtitle' => Str::limit((string) ($domain->description ?: 'Competency framework domain'), 100),
                'url' => route('competency.domains.show', $domain->domain_id),
                'icon' => 'bi-diagram-3',
            ]);
        }

        $competencies = DB::table('competencies as c')
            ->join('competency_categories as category', 'c.category_id', '=', 'category.category_id')
            ->join('competency_domains as domain', 'category.domain_id', '=', 'domain.domain_id')
            ->select('c.competency_id', 'c.competency_name', 'c.competency_code', 'c.description', 'category.category_name', 'domain.domain_id', 'domain.domain_name');
        $this->applySearch($competencies, [
            'c.competency_name', 'c.competency_code', 'c.description', 'category.category_name', 'domain.domain_name',
        ], $terms);

        foreach ($competencies->orderBy('c.competency_name')->limit(self::PER_SOURCE_LIMIT)->get() as $competency) {
            $this->pushResult($results, [
                'id' => 'competency:'.$competency->competency_id,
                'group' => 'Competency',
                'type' => 'competency',
                'title' => $competency->competency_name,
                'subtitle' => trim($competency->domain_name.' · '.$competency->category_name.' · '.($competency->competency_code ?: '')),
                'url' => route('competency.domains.show', $competency->domain_id).'#competency-'.$competency->competency_id,
                'icon' => 'bi-bullseye',
            ]);
        }

        $credentials = DB::table('employee_credentials as credential')
            ->join('employees as e', 'credential.employee_id', '=', 'e.employee_id')
            ->select('credential.credential_id', 'credential.credential_type', 'credential.issuing_body', 'credential.expiry_date',
                'e.first_name', 'e.last_name');
        $this->applySearch($credentials, [
            'credential.credential_type', 'credential.issuing_body', 'e.first_name', 'e.last_name', 'e.employee_code',
        ], $terms);

        foreach ($this->scopeToVisibleEmployees($credentials, 'e.employee_id')
            ->orderBy('credential.expiry_date')->limit(self::PER_SOURCE_LIMIT)->get() as $credential) {
            $this->pushResult($results, [
                'id' => 'credential:'.$credential->credential_id,
                'group' => 'Competency',
                'type' => 'credential',
                'title' => $credential->credential_type,
                'subtitle' => trim(implode(' · ', array_filter([
                    trim($credential->first_name.' '.$credential->last_name),
                    $credential->issuing_body,
                    $credential->expiry_date ? 'Expires '.$credential->expiry_date : null,
                ]))),
                'url' => route('competency.credentials.index', ['focus' => $credential->credential_id]).'#credential-'.$credential->credential_id,
                'icon' => 'bi-patch-check',
            ]);
        }
    }

    /** @param array<int, string> $terms */
    private function searchLearning(Collection $results, User $user, array $terms): void
    {
        $courses = DB::table('courses')
            ->where('is_active', true)
            ->select('course_id', 'course_code', 'title', 'category', 'description', 'cpd_hours');
        $this->applySearch($courses, ['course_code', 'title', 'category', 'description'], $terms);

        foreach ($courses->orderBy('title')->limit(self::PER_SOURCE_LIMIT)->get() as $course) {
            $this->pushResult($results, [
                'id' => 'course:'.$course->course_id,
                'group' => 'Learning',
                'type' => 'course',
                'title' => $course->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $course->course_code,
                    $course->category,
                    $course->cpd_hours.' CPD hours',
                ]))),
                'url' => route('learning.courses.show', $course->course_id),
                'icon' => 'bi-book',
            ]);
        }

        $pathways = DB::table('learning_pathways')
            ->select('pathway_id', 'pathway_name', 'description', 'total_cpd_hours');
        $this->applySearch($pathways, ['pathway_name', 'description'], $terms);

        foreach ($pathways->orderBy('pathway_name')->limit(self::PER_SOURCE_LIMIT)->get() as $pathway) {
            $this->pushResult($results, [
                'id' => 'pathway:'.$pathway->pathway_id,
                'group' => 'Learning',
                'type' => 'learning_pathway',
                'title' => $pathway->pathway_name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $pathway->total_cpd_hours ? $pathway->total_cpd_hours.' CPD hours' : null,
                    Str::limit((string) $pathway->description, 80),
                ]))),
                'url' => route('learning.pathways.index').'#pathway-'.$pathway->pathway_id,
                'icon' => 'bi-signpost-split',
            ]);
        }

        $cpd = DB::table('cpd_records as cpd')
            ->join('employees as e', 'cpd.employee_id', '=', 'e.employee_id')
            ->select('cpd.cpd_id', 'cpd.activity_name', 'cpd.source_type', 'cpd.cpd_hours', 'cpd.date_earned', 'cpd.verified',
                'e.first_name', 'e.last_name', 'e.employee_code');
        $this->applySearch($cpd, ['cpd.activity_name', 'cpd.source_type', 'e.first_name', 'e.last_name', 'e.employee_code'], $terms);

        foreach ($this->scopeToVisibleEmployees($cpd, 'e.employee_id')
            ->orderByDesc('cpd.date_earned')->limit(self::PER_SOURCE_LIMIT)->get() as $record) {
            $this->pushResult($results, [
                'id' => 'cpd:'.$record->cpd_id,
                'group' => 'Learning',
                'type' => 'cpd_record',
                'title' => $record->activity_name,
                'subtitle' => trim(implode(' · ', array_filter([
                    trim($record->first_name.' '.$record->last_name),
                    ucfirst((string) $record->source_type),
                    $record->cpd_hours.' hours',
                    $record->verified ? 'Verified' : 'Pending verification',
                ]))),
                'url' => route('learning.cpd.index', ['focus' => $record->cpd_id]).'#cpd-'.$record->cpd_id,
                'icon' => 'bi-award',
            ]);
        }

        $cycles = DB::table('employee_renewal_cycles as cycle')
            ->join('renewal_rules as rule', 'cycle.rule_id', '=', 'rule.rule_id')
            ->join('employees as e', 'cycle.employee_id', '=', 'e.employee_id')
            ->select('cycle.cycle_id', 'cycle.employee_id', 'cycle.status', 'cycle.cycle_end', 'rule.label', 'rule.subject_key',
                'e.first_name', 'e.last_name');
        $this->applySearch($cycles, ['rule.label', 'rule.subject_key', 'cycle.status', 'e.first_name', 'e.last_name'], $terms);

        foreach ($this->scopeToVisibleEmployees($cycles, 'e.employee_id')
            ->orderBy('cycle.cycle_end')->limit(self::PER_SOURCE_LIMIT)->get() as $cycle) {
            $this->pushResult($results, [
                'id' => 'renewal-cycle:'.$cycle->cycle_id,
                'group' => 'Learning',
                'type' => 'renewal_cycle',
                'title' => $cycle->label,
                'subtitle' => trim(implode(' · ', array_filter([
                    trim($cycle->first_name.' '.$cycle->last_name),
                    ucfirst((string) $cycle->status),
                    $cycle->cycle_end ? 'Ends '.$cycle->cycle_end : null,
                ]))),
                'url' => route('learning.cycles.mine', ['employee' => $cycle->employee_id]),
                'icon' => 'bi-arrow-repeat',
            ]);
        }

        if ($user->can('manage-learning')) {
            $rules = DB::table('renewal_rules')
                ->select('rule_id', 'label', 'subject_key', 'subject_type', 'required_hours', 'cycle_months');
            $this->applySearch($rules, ['label', 'subject_key', 'subject_type'], $terms);

            foreach ($rules->orderBy('label')->limit(self::PER_SOURCE_LIMIT)->get() as $rule) {
                $this->pushResult($results, [
                    'id' => 'renewal-rule:'.$rule->rule_id,
                    'group' => 'Learning',
                    'type' => 'renewal_rule',
                    'title' => $rule->label,
                    'subtitle' => trim($rule->required_hours.' hours every '.$rule->cycle_months.' months'),
                    'url' => route('learning.renewals.rules'),
                    'icon' => 'bi-arrow-repeat',
                ]);
            }
        }

        if ($user->can('view-compliance')) {
            $this->searchAssignments($results, $user, $terms);
        }
    }

    /** @param array<int, string> $terms */
    private function searchAssignments(Collection $results, User $user, array $terms): void
    {
        $courseAssignments = DB::table('training_assignments as assignment')
            ->join('courses as course', 'assignment.subject_id', '=', 'course.course_id')
            ->where('assignment.subject_type', 'course')
            ->select('assignment.assignment_id', 'assignment.required_by', 'assignment.target_type', 'assignment.reason', 'course.title');
        $this->applySearch($courseAssignments, ['course.title', 'assignment.target_type', 'assignment.reason'], $terms);
        $this->scopeAssignmentVisibility($courseAssignments, $user, 'course_enrollments');

        foreach ($courseAssignments->orderByDesc('assignment.created_at')->limit(self::PER_SOURCE_LIMIT)->get() as $assignment) {
            $this->pushResult($results, [
                'id' => 'assignment:'.$assignment->assignment_id,
                'group' => 'Learning',
                'type' => 'training_assignment',
                'title' => 'Required: '.$assignment->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    ucfirst((string) $assignment->target_type),
                    $assignment->required_by ? 'Due '.$assignment->required_by : null,
                ]))),
                'url' => route('learning.assignments.show', $assignment->assignment_id),
                'icon' => 'bi-clipboard-check',
            ]);
        }

        $sessionAssignments = DB::table('training_assignments as assignment')
            ->join('training_sessions as session', 'assignment.subject_id', '=', 'session.session_id')
            ->where('assignment.subject_type', 'session')
            ->select('assignment.assignment_id', 'assignment.required_by', 'assignment.target_type', 'assignment.reason', 'session.title');
        $this->applySearch($sessionAssignments, ['session.title', 'assignment.target_type', 'assignment.reason'], $terms);
        $this->scopeAssignmentVisibility($sessionAssignments, $user, 'training_registrations');

        foreach ($sessionAssignments->orderByDesc('assignment.created_at')->limit(self::PER_SOURCE_LIMIT)->get() as $assignment) {
            $this->pushResult($results, [
                'id' => 'assignment:'.$assignment->assignment_id,
                'group' => 'Learning',
                'type' => 'training_assignment',
                'title' => 'Required session: '.$assignment->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    ucfirst((string) $assignment->target_type),
                    $assignment->required_by ? 'Due '.$assignment->required_by : null,
                ]))),
                'url' => route('learning.assignments.show', $assignment->assignment_id),
                'icon' => 'bi-clipboard-check',
            ]);
        }
    }

    private function scopeAssignmentVisibility($query, User $user, string $rosterTable): void
    {
        if ($user->seesWholeOrganisation()) {
            return;
        }

        $actor = $user->employee_id;

        if (! $actor) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($exists) use ($actor, $rosterTable) {
            $exists->selectRaw('1')
                ->from($rosterTable.' as roster')
                ->join('employees as employee', 'roster.employee_id', '=', 'employee.employee_id')
                ->whereColumn('roster.assignment_id', 'assignment.assignment_id')
                ->where(function ($visible) use ($actor) {
                    $visible->where('employee.employee_id', $actor)
                        ->orWhere('employee.supervisor_id', $actor);
                });
        });
    }

    /** @param array<int, string> $terms */
    private function searchTraining(Collection $results, array $terms): void
    {
        $sessions = DB::table('training_sessions as session')
            ->leftJoin('employees as instructor', 'session.instructor_id', '=', 'instructor.employee_id')
            ->leftJoin('training_venues as venue', 'session.venue_id', '=', 'venue.venue_id')
            ->select('session.session_id', 'session.session_code', 'session.title', 'session.category', 'session.description', 'session.session_date',
                'instructor.first_name as instructor_first', 'instructor.last_name as instructor_last', 'venue.venue_name');
        $this->applySearch($sessions, [
            'session.session_code', 'session.title', 'session.category', 'session.description',
            'instructor.first_name', 'instructor.last_name', 'venue.venue_name',
        ], $terms);

        foreach ($sessions->orderBy('session.session_date')->limit(self::PER_SOURCE_LIMIT)->get() as $session) {
            $this->pushResult($results, [
                'id' => 'session:'.$session->session_id,
                'group' => 'Training',
                'type' => 'training_session',
                'title' => $session->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $session->category,
                    $session->session_date,
                    $session->venue_name,
                ]))),
                'url' => route('training.sessions.show', $session->session_id),
                'icon' => 'bi-calendar-event',
            ]);
        }

        $venues = DB::table('training_venues')
            ->select('venue_id', 'venue_name', 'building', 'floor', 'capacity', 'is_active');
        $this->applySearch($venues, ['venue_name', 'building', 'floor'], $terms);

        foreach ($venues->orderBy('venue_name')->limit(self::PER_SOURCE_LIMIT)->get() as $venue) {
            $this->pushResult($results, [
                'id' => 'venue:'.$venue->venue_id,
                'group' => 'Training',
                'type' => 'training_venue',
                'title' => $venue->venue_name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $venue->building,
                    $venue->floor ? 'Floor '.$venue->floor : null,
                    $venue->capacity.' seats',
                ]))),
                'url' => route('training.venues.index').'#venue-'.$venue->venue_id,
                'icon' => 'bi-geo-alt',
            ]);
        }
    }

    /** @param array<int, string> $terms */
    private function searchRecognition(Collection $results, User $user, array $terms): void
    {
        $posts = DB::table('recognition_posts as post')
            ->join('employees as author', 'post.author_id', '=', 'author.employee_id')
            ->join('employees as recipient', 'post.recipient_id', '=', 'recipient.employee_id')
            ->leftJoin('recognition_badges as badge', 'post.badge_id', '=', 'badge.badge_id')
            ->where('post.moderation_status', 'approved')
            ->select('post.post_id', 'post.author_id', 'post.recipient_id', 'post.is_public', 'post.message',
                'author.first_name as author_first', 'author.last_name as author_last',
                'recipient.first_name as recipient_first', 'recipient.last_name as recipient_last',
                'badge.badge_name');

        if ($user->hasRole('admin', 'hr_manager')) {
            // Moderators can search the private queue as well as the public wall.
        } elseif ($user->employee_id) {
            $posts->where(function ($query) use ($user) {
                $query->where('post.is_public', true)
                    ->orWhere('post.author_id', $user->employee_id)
                    ->orWhere('post.recipient_id', $user->employee_id);
            });
        } else {
            $posts->where('post.is_public', true);
        }

        $this->applySearch($posts, [
            'post.message', 'author.first_name', 'author.last_name', 'recipient.first_name', 'recipient.last_name', 'badge.badge_name',
        ], $terms);

        foreach ($posts->orderByDesc('post.created_at')->limit(self::PER_SOURCE_LIMIT)->get() as $post) {
            $author = trim($post->author_first.' '.$post->author_last);
            $recipient = trim($post->recipient_first.' '.$post->recipient_last);
            $private = ! (bool) $post->is_public;

            $this->pushResult($results, [
                'id' => 'recognition:'.$post->post_id,
                'group' => 'Recognition',
                'type' => 'recognition_post',
                'title' => $author.' recognized '.$recipient,
                'subtitle' => trim(implode(' · ', array_filter([
                    $private ? 'Private recognition' : 'Public recognition',
                    $post->badge_name,
                    Str::limit((string) $post->message, 90),
                ]))),
                'url' => route('recognition.index', ['focus' => $post->post_id]).'#recognition-post-'.$post->post_id,
                'icon' => $private ? 'bi-lock' : 'bi-stars',
            ]);
        }
    }

    /** @param array<int, string> $terms */
    private function searchSuccession(Collection $results, User $user, array $terms): void
    {
        if (! $user->can('view-succession')) {
            return;
        }

        $positions = DB::table('critical_positions as position')
            ->join('departments as department', 'position.department_id', '=', 'department.department_id')
            ->leftJoin('employees as holder', 'position.current_holder_id', '=', 'holder.employee_id')
            ->select('position.position_id', 'position.position_title', 'position.is_critical', 'position.vacancy_risk',
                'department.name as department_name', 'holder.first_name as holder_first', 'holder.last_name as holder_last');

        if (! $user->seesWholeOrganisation()) {
            $actor = $user->employee_id ?? '';
            $positions->whereExists(function ($exists) use ($actor) {
                $exists->selectRaw('1')
                    ->from('succession_candidates as candidate')
                    ->join('employees as candidate_employee', 'candidate.employee_id', '=', 'candidate_employee.employee_id')
                    ->whereColumn('candidate.position_id', 'position.position_id')
                    ->where('candidate_employee.supervisor_id', $actor);
            });
        }

        $positionColumns = ['position.position_title', 'department.name', 'holder.first_name', 'holder.last_name'];
        if ($user->seesWholeOrganisation()) {
            $positionColumns[] = 'position.vacancy_risk';
        }
        $this->applySearch($positions, $positionColumns, $terms);

        foreach ($positions->orderBy('position.position_title')->limit(self::PER_SOURCE_LIMIT)->get() as $position) {
            $this->pushResult($results, [
                'id' => 'position:'.$position->position_id,
                'group' => 'Succession',
                'type' => 'critical_position',
                'title' => $position->position_title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $position->department_name,
                    trim(($position->holder_first ?? '').' '.($position->holder_last ?? '')) ?: 'Vacant',
                    $user->seesWholeOrganisation() && $position->vacancy_risk ? ucfirst($position->vacancy_risk).' risk' : null,
                ]))),
                'url' => route('succession.positions.show', $position->position_id),
                'icon' => 'bi-diagram-3',
            ]);
        }

        $candidates = DB::table('succession_candidates as candidate')
            ->join('employees as employee', 'candidate.employee_id', '=', 'employee.employee_id')
            ->join('critical_positions as position', 'candidate.position_id', '=', 'position.position_id')
            ->select('candidate.candidate_id', 'candidate.readiness_level', 'candidate.status', 'candidate.nine_box_label',
                'employee.first_name', 'employee.last_name', 'employee.position_title as current_position', 'position.position_title as target_position');

        if (! $user->seesWholeOrganisation()) {
            $candidates->where('employee.supervisor_id', $user->employee_id ?? '');
        }

        $candidateColumns = ['employee.first_name', 'employee.last_name', 'employee.position_title', 'position.position_title'];
        if ($user->seesWholeOrganisation()) {
            $candidateColumns = array_merge($candidateColumns, ['candidate.readiness_level', 'candidate.status', 'candidate.nine_box_label']);
        }
        $this->applySearch($candidates, $candidateColumns, $terms);

        foreach ($candidates->orderBy('employee.last_name')->limit(self::PER_SOURCE_LIMIT)->get() as $candidate) {
            $this->pushResult($results, [
                'id' => 'candidate:'.$candidate->candidate_id,
                'group' => 'Succession',
                'type' => 'succession_candidate',
                'title' => trim($candidate->first_name.' '.$candidate->last_name),
                'subtitle' => trim(implode(' · ', array_filter([
                    'Successor for '.$candidate->target_position,
                    $user->seesWholeOrganisation() && $candidate->readiness_level
                        ? ucfirst(str_replace('_', ' ', $candidate->readiness_level))
                        : null,
                ]))),
                'url' => route('succession.candidates.show', $candidate->candidate_id),
                'icon' => 'bi-person-badge',
            ]);
        }
    }

    /** @param array<int, string> $terms */
    private function searchAdministration(Collection $results, User $user, array $terms): void
    {
        if ($user->can('manage-departments')) {
            $departments = DB::table('departments')->select('department_id', 'name', 'department_code');
            $this->applySearch($departments, ['name', 'department_code'], $terms);

            foreach ($departments->orderBy('name')->limit(self::PER_SOURCE_LIMIT)->get() as $department) {
                $this->pushResult($results, [
                    'id' => 'department:'.$department->department_id,
                    'group' => 'Administration',
                    'type' => 'department',
                    'title' => $department->name,
                    'subtitle' => $department->department_code ?: 'Department directory',
                    'url' => route('departments.index').'#department-'.$department->department_id,
                    'icon' => 'bi-building',
                ]);
            }
        }

        if ($user->can('manage-users')) {
            $users = DB::table('users')->select('id', 'name', 'email', 'role');
            $this->applySearch($users, ['name', 'email', 'role'], $terms);

            foreach ($users->orderBy('name')->limit(self::PER_SOURCE_LIMIT)->get() as $account) {
                $this->pushResult($results, [
                    'id' => 'user:'.$account->id,
                    'group' => 'Administration',
                    'type' => 'user_account',
                    'title' => $account->name,
                    'subtitle' => $account->email.' · '.ucwords(str_replace('_', ' ', $account->role ?: 'staff')),
                    'url' => route('users.edit', $account->id),
                    'icon' => 'bi-person-gear',
                ]);
            }
        }
    }

    /** @param array<int, string> $terms */
    private function searchAiHistory(Collection $results, User $user, array $terms): void
    {
        $sessions = DB::table('ai_chat_sessions as session')
            ->where('session.user_id', $user->id)
            ->select('session.id', 'session.title', 'session.updated_at')
            ->where(function ($query) use ($terms, $user) {
                foreach ($terms as $term) {
                    $pattern = '%'.$term.'%';
                    $query->where(function ($nested) use ($pattern, $user) {
                        $nested->where('session.title', 'like', $pattern)
                            ->orWhereExists(function ($message) use ($pattern, $user) {
                                $message->selectRaw('1')
                                    ->from('ai_chat_messages as chat_message')
                                    ->whereColumn('chat_message.session_id', 'session.id')
                                    ->where('chat_message.user_id', $user->id)
                                    ->where('chat_message.message', 'like', $pattern);
                            });
                    });
                }
            });

        foreach ($sessions->orderByDesc('session.updated_at')->limit(self::PER_SOURCE_LIMIT)->get() as $session) {
            $this->pushResult($results, [
                'id' => 'ai-session:'.$session->id,
                'group' => 'AI Assistant',
                'type' => 'ai_session',
                'title' => $session->title ?: 'New chat',
                'subtitle' => 'Saved conversation',
                'url' => route('dashboard', ['ai_session' => $session->id]),
                'icon' => 'bi-robot',
            ]);
        }
    }
}
