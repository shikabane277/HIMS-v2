<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\CompetencyController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GapAnalysisController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SuccessionController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserController;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// ── Public ──────────────────────────────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

// Public verification for QR codes and certificate links
Route::get('/certificates/{code}', [LearningController::class, 'showCertificate'])->name('certificates.verify');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
| Every route below requires a verified login. Write operations and
| cross-employee data are further gated with the 'role' middleware
| (App\Http\Middleware\EnsureUserHasRole), which reads users.role.
|
| Roles: admin | hr_manager | supervisor | staff
|
| The matching Gate definitions live in AppServiceProvider::registerGates()
| and are what the sidebar's @can checks use, so navigation and enforcement
| stay in step.
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard — one route, role-scoped content.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', GlobalSearchController::class)->name('search');

    // ── Performance Management ───────────────────────────────
    Route::prefix('performance')->name('performance.')->group(function () {
        Route::get('/', [PerformanceController::class, 'index'])->name('index');
        Route::get('/reviews', [PerformanceController::class, 'reviewsIndex'])->name('reviews.index');

        // Opening and scoring a review is for admin, HR and department heads —
        // but holding one of those roles is only the door. Which employee you may
        // actually review is decided per-record in the controller against
        // employees.supervisor_id, so an HR account cannot review a nurse whose
        // supervisor is present and able to do it themselves; and scoring is
        // narrower still, restricted to the one account named in reviewer_id.
        //
        // The scoring screen sits inside this group rather than beside it because
        // there is now exactly one person who can use it. It is also what
        // AiActionRegistry reads to decide who may drive performance.review.status
        // from the assistant, so the role list here is load-bearing beyond the
        // routing.
        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/reviews/create', [PerformanceController::class, 'createReview'])->name('reviews.create');
            Route::post('/reviews', [PerformanceController::class, 'storeReview'])->name('reviews.store');
            Route::get('/reviews/{id}/score', [PerformanceController::class, 'scoreReview'])->name('reviews.score');
            Route::put('/reviews/{id}/score', [PerformanceController::class, 'saveScores'])->name('reviews.score.save');
            Route::post('/reviews/{id}/goals', [PerformanceController::class, 'storeGoal'])->name('reviews.goals.store');
            Route::put('/reviews/goals/{goalId}', [PerformanceController::class, 'updateGoal'])->name('reviews.goals.update');
            Route::post('/reviews/{id}/pip', [PerformanceController::class, 'storePip'])->name('reviews.pip.store');
            Route::put('/reviews/pip/{pipId}', [PerformanceController::class, 'updatePip'])->name('reviews.pip.update');
        });

        // Reading a review stays open to any signed-in account — show() answers
        // it by identity: your own review, one you wrote, or one about somebody
        // who reports to you.
        Route::post('/reviews/{id}/employee-response', [PerformanceController::class, 'storeEmployeeResponse'])
            ->name('reviews.employee-response.store');
        Route::get('/reviews/{id}', [PerformanceController::class, 'show'])->name('show');

        Route::prefix('cycles')->name('cycles.')->group(function () {
            // Cycle administration is HR/admin only. Neither creating nor
            // editing has a GET page — both open a modal on the index (and the
            // Edit button also on the show page), posting straight to store/update.
            Route::middleware('role:admin,hr_manager')->group(function () {
                Route::post('/', [PerformanceController::class, 'storeCycle'])->name('store');
                Route::put('/{id}', [PerformanceController::class, 'updateCycle'])->name('update');
            });
            Route::get('/{id}', [PerformanceController::class, 'showCycle'])->name('show');
        });

        Route::prefix('kpis')->name('kpis.')->middleware('role:admin,hr_manager')->group(function () {
            Route::get('/', [PerformanceController::class, 'kpiIndex'])->name('index');
            Route::post('/', [PerformanceController::class, 'kpiStore'])->name('store');
            Route::put('/{id}', [PerformanceController::class, 'kpiUpdate'])->name('update');
            Route::post('/{id}/toggle-status', [PerformanceController::class, 'kpiToggleStatus'])->name('toggle-status');
        });
    });

    // ── Competency Management ────────────────────────────────
    Route::prefix('competency')->name('competency.')->group(function () {
        Route::get('/', [CompetencyController::class, 'index'])->name('index');
        Route::get('/credentials', [CompetencyController::class, 'credentialsIndex'])->name('credentials.index');

        // Assessing staff and registering credentials is a supervisory action.
        // Neither has a GET page: both open a modal on the competency index
        // (Add Credential also on the credentials index) and post straight here.
        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::post('/sync', [CompetencyController::class, 'syncHr'])->name('sync');
            Route::put('/integrations/{id}', [CompetencyController::class, 'updateIntegration'])->name('integrations.update');
            Route::post('/assessments', [CompetencyController::class, 'storeAssessment'])->name('assessments.store');
            Route::get('/credentials/import/template', [CompetencyController::class, 'downloadCredentialsTemplate'])->name('credentials.import.template');
            Route::post('/credentials/import', [CompetencyController::class, 'importCredentialsCsv'])->name('credentials.import');
            Route::post('/credentials', [CompetencyController::class, 'storeCredential'])->name('credentials.store');
        });

        // The framework itself is HR/admin territory. Creating a domain is a
        // modal on the Competency index (?new=domain), so there is no GET page.
        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::post('/domains', [CompetencyController::class, 'storeDomain'])->name('domains.store');
            Route::put('/domains/{id}', [CompetencyController::class, 'updateDomain'])->name('domains.update');
            Route::patch('/domains/{id}/toggle-status', [CompetencyController::class, 'toggleDomainStatus'])->name('domains.toggle-status');
            Route::delete('/domains/{id}', [CompetencyController::class, 'destroyDomain'])->name('domains.destroy');

            Route::get('/role-requirements', [CompetencyController::class, 'roleRequirementsIndex'])->name('role-requirements.index');
            Route::post('/role-requirements', [CompetencyController::class, 'storeRoleRequirement'])->name('role-requirements.store');
            Route::put('/role-requirements/{id}', [CompetencyController::class, 'updateRoleRequirement'])->name('role-requirements.update');
            Route::delete('/role-requirements/{id}', [CompetencyController::class, 'destroyRoleRequirement'])->name('role-requirements.destroy');
        });

        // ── AI-Driven Competency Gap Analysis (Objective 6) ──
        Route::middleware('role:admin,hr_manager,supervisor')->prefix('gap-analysis')->name('gap.')->group(function () {
            Route::get('/', [GapAnalysisController::class, 'index'])->name('index');
            Route::get('/department', [GapAnalysisController::class, 'department'])->name('department');
            Route::get('/employee/{employeeId}', [GapAnalysisController::class, 'employee'])->name('employee');
            Route::get('/employee/{employeeId}/json', [GapAnalysisController::class, 'employeeJson'])->name('employee.json');
        });

        // Wildcard last on principle: it is the only GET under /domains now
        // that the create page is a modal, but a future literal sibling added
        // above this line keeps working, and one added below would not.
        Route::get('/domains/{id}', [CompetencyController::class, 'showDomain'])->name('domains.show');
    });

    // ── Learning Management ──────────────────────────────────
    // One module with two halves, split by whose question a page answers rather
    // than by tab. The individual-facing pages (catalogue, my CPD, my pathways,
    // my renewal cycles) are open to every signed-in account. The institutional
    // pages — what the hospital requires, who is short of it, the evidence a
    // survey asks for — sit behind the two role bands at the bottom of this
    // group and used to live under a separate `compliance` prefix. They are
    // reached through the tab strip in partials/learning-tabs.blade.php, which
    // gates each tab on the same Gate as the route it points at, so a member of
    // staff is never offered a tab that would refuse them.
    Route::prefix('learning')->name('learning.')->group(function () {
        Route::get('/', [LearningController::class, 'index'])->name('index');
        Route::get('/modules', [LearningController::class, 'modulesIndex'])->name('modules.index');
        Route::get('/pathways', [LearningController::class, 'pathwaysIndex'])->name('pathways.index');
        Route::get('/cpd', [LearningController::class, 'cpdIndex'])->name('cpd.index');
        Route::get('/certificates', fn() => redirect()->route('recognition.certificates.index'))->name('certificates.index');
        Route::get('/certificates/{code}', [LearningController::class, 'showCertificate'])->name('certificates.show');

        // Authoring the catalogue is HR/admin only. Courses and pathways are
        // created and edited from modals on the Learning index (New Pathway also
        // from the pathways index), so none of them has a GET page.
        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::post('/courses', [LearningController::class, 'storeCourse'])->name('courses.store');
            Route::put('/courses/{id}', [LearningController::class, 'updateCourse'])->name('courses.update');
            Route::post('/courses/{id}/toggle-status', [LearningController::class, 'toggleCourseStatus'])->name('courses.toggle-status');
            Route::post('/pathways', [LearningController::class, 'storePathway'])->name('pathways.store');
            Route::post('/cpd/{id}/verify', [LearningController::class, 'verifyCpd'])->name('cpd.verify');

            // Re-tag an existing course against the competencies it remediates.
            Route::post('/courses/{id}/competencies', [LearningController::class, 'updateCourseCompetencies'])
                ->name('courses.competencies.update');

            // Undoing a completion withdraws evidence and its CPD credit, so it
            // is narrower than recording one.
            Route::post('/enrollments/{id}/reopen', [LearningController::class, 'reopenEnrollment'])
                ->name('enrollments.reopen');
        });

        // Recording a completion is supervisor-and-up, mirroring session
        // check-in: it is a statement about somebody, so nobody self-certifies.
        // The controller holds supervisors to their own direct reports.
        Route::post('/enrollments/{id}/complete', [LearningController::class, 'completeEnrollment'])
            ->middleware('role:admin,hr_manager,supervisor')
            ->name('enrollments.complete');

        Route::post('/cpd', [LearningController::class, 'storeCpd'])->name('cpd.store');

        // My own renewal cycles — visible to everyone, since the person owing
        // the hours is the one who has to earn them.
        Route::get('/my-cycles', [ComplianceController::class, 'myCycles'])->name('cycles.mine');

        // ── The institutional half (was the `compliance` prefix) ──
        // Supervisors get the read-only oversight pages, scoped to their own
        // reporting line by scopeToVisibleEmployees(); HR/admin own the renewal
        // rules and the hospital-wide reports. Served by ComplianceController,
        // which keeps its name because it is still the hospital-facing half —
        // only the URLs and the navigation moved.
        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/required', [ComplianceController::class, 'index'])->name('assignments.index');
            Route::post('/required', [ComplianceController::class, 'storeAssignment'])->name('assignments.store');
            Route::get('/renewals', [ComplianceController::class, 'atRisk'])->name('renewals.index');
            Route::get('/accreditation', [ComplianceController::class, 'accreditationReport'])->name('accreditation');
        });

        // Renewal rules set hospital-wide policy, and account coverage exposes
        // login state — both HR/admin only.
        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/renewals/rules', [ComplianceController::class, 'rulesIndex'])->name('renewals.rules');
            Route::post('/renewals/rules', [ComplianceController::class, 'storeRule'])->name('renewals.rules.store');
            Route::post('/renewals/sync', [ComplianceController::class, 'syncCycles'])->name('renewals.sync');
            Route::get('/accounts', [ComplianceController::class, 'accountCoverage'])->name('accounts');
        });

        // Wildcards last so they cannot swallow a literal sibling above.
        Route::get('/courses/{id}', [LearningController::class, 'showCourse'])->name('courses.show');
        Route::get('/required/{id}', [ComplianceController::class, 'showAssignment'])
            ->middleware('role:admin,hr_manager,supervisor')->name('assignments.show');
    });

    // Backward-compatible names for bookmarks, integrations, and older tests.
    // The Compliance sidebar was merged into Learning, but these aliases keep
    // existing callers on the same controller and authorization rules.
    Route::prefix('compliance')->name('compliance.')->group(function () {
        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/required', [ComplianceController::class, 'index'])->name('index');
            Route::post('/required', [ComplianceController::class, 'storeAssignment'])->name('assignments.store');
            Route::get('/renewals', [ComplianceController::class, 'atRisk'])->name('at-risk');
            Route::get('/accreditation', [ComplianceController::class, 'accreditationReport'])->name('accreditation');
            Route::get('/required/{id}', [ComplianceController::class, 'showAssignment'])->name('assignments.show');
        });
        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/accounts', [ComplianceController::class, 'accountCoverage'])->name('account-coverage');
            Route::get('/renewals/rules', [ComplianceController::class, 'rulesIndex'])->name('rules.index');
            Route::post('/renewals/rules', [ComplianceController::class, 'storeRule'])->name('rules.store');
            Route::post('/renewals/sync', [ComplianceController::class, 'syncCycles'])->name('rules.sync');
        });
    });

    // ── Training Management ──────────────────────────────────
    // All three forms here are modals on the pages they belong to — sessions and
    // venues on their index (?new=session, ?new=venue), feedback on the session
    // itself (?feedback=1) — so none of them has a GET route.
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/', [TrainingController::class, 'index'])->name('index');
        Route::get('/venues', [TrainingController::class, 'venuesIndex'])->name('venues.index');

        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::post('/sessions', [TrainingController::class, 'storeSession'])->name('sessions.store');
            Route::put('/sessions/{id}', [TrainingController::class, 'updateSession'])->name('sessions.update');
            Route::post('/sessions/{id}/reschedule', [TrainingController::class, 'rescheduleSession'])->name('sessions.reschedule');
            Route::post('/sessions/{id}/cancel', [TrainingController::class, 'cancelSession'])->name('sessions.cancel');
            Route::post('/sessions/{id}/checkin', [TrainingController::class, 'checkIn'])->name('sessions.checkin');
        });

        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::post('/venues', [TrainingController::class, 'storeVenue'])->name('venues.store');
            Route::put('/venues/{id}', [TrainingController::class, 'updateVenue'])->name('venues.update');
            Route::delete('/venues/{id}', [TrainingController::class, 'destroyVenue'])->name('venues.destroy');
        });

        Route::get('/sessions/{id}', [TrainingController::class, 'showSession'])->name('sessions.show');
        Route::post('/sessions/{id}/register', [TrainingController::class, 'register'])->name('register');
        Route::post('/sessions/{id}/feedback', [TrainingController::class, 'storeFeedback'])->name('sessions.feedback.store');
    });

    // ── Succession Planning ──────────────────────────────────
    // Talent pipeline data is sensitive; staff have no access at all.
    Route::prefix('succession')->name('succession.')
        ->middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/', [SuccessionController::class, 'index'])->name('index');
            Route::get('/positions', [SuccessionController::class, 'positionsIndex'])->name('positions.index');

            Route::middleware('role:admin,hr_manager')->group(function () {
                Route::get('/positions/create', [SuccessionController::class, 'createPosition'])->name('positions.create');
                Route::post('/positions', [SuccessionController::class, 'storePosition'])->name('positions.store');
                Route::post('/positions/{id}/review', [SuccessionController::class, 'reviewPosition'])->name('positions.review');
                Route::get('/candidates/create', [SuccessionController::class, 'createCandidate'])->name('candidates.create');
                Route::post('/candidates', [SuccessionController::class, 'storeCandidate'])->name('candidates.store');
                Route::get('/candidates/{id}/edit', [SuccessionController::class, 'editCandidate'])->name('candidates.edit');
                Route::put('/candidates/{id}', [SuccessionController::class, 'updateCandidate'])->name('candidates.update');
                Route::post('/candidates/{id}/approve', [SuccessionController::class, 'approveCandidate'])->name('candidates.approve');
                Route::post('/candidates/{id}/reject', [SuccessionController::class, 'rejectCandidate'])->name('candidates.reject');
                Route::delete('/candidates/{id}', [SuccessionController::class, 'withdrawCandidate'])->name('candidates.withdraw');
            });

            // Development milestones: supervisors maintain the plans for the people
            // they have nominated, so these stay at the group's access level.
            Route::post('/candidates/{id}/milestones', [SuccessionController::class, 'storeMilestone'])->name('milestones.store');
            Route::put('/candidates/{id}/milestones/{pathId}', [SuccessionController::class, 'updateMilestone'])->name('milestones.update');
            Route::delete('/candidates/{id}/milestones/{pathId}', [SuccessionController::class, 'destroyMilestone'])->name('milestones.destroy');

            // Wildcards last so they cannot swallow /positions/create or /candidates/create.
            Route::get('/positions/{id}', [SuccessionController::class, 'showPosition'])->name('positions.show');
            Route::get('/candidates/{id}', [SuccessionController::class, 'showCandidate'])->name('candidates.show');
        });

    // ── Employees ────────────────────────────────────────────
    // ── Development progression ──────────────────────────────
    // Deliberately outside the supervisory group below: a member of staff has
    // no directory access but must still see their own development record.
    // EmployeeController::progression() calls authorizeEmployeeAccess(), which
    // is what actually keeps staff to their own row and a supervisor to their
    // department.
    // Social Recognition is open to every authenticated role. Write paths
    // require a linked employee profile inside the controller; an account-only
    // administrator may read the wall but cannot impersonate an employee.
    Route::prefix('recognition')->name('recognition.')->group(function () {
        Route::get('/', [RecognitionController::class, 'index'])->name('index');
        Route::get('/certificates', [LearningController::class, 'certificatesIndex'])->name('certificates.index');
        Route::post('/posts', [RecognitionController::class, 'storePost'])->name('posts.store');
        Route::post('/posts/{postId}/react', [RecognitionController::class, 'react'])->name('react');
        Route::post('/posts/{postId}/comments', [RecognitionController::class, 'storeComment'])->name('comments.store');

        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::post('/badges', [RecognitionController::class, 'storeBadge'])->name('badges.store');
            Route::post('/posts/{postId}/admit-points', [RecognitionController::class, 'admitPoints'])->name('posts.admit_points');
            Route::post('/posts/{postId}/reject-points', [RecognitionController::class, 'rejectPoints'])->name('posts.reject_points');
            Route::patch('/posts/{postId}/moderation', [RecognitionController::class, 'moderatePost'])->name('posts.moderate');
            Route::patch('/comments/{commentId}/moderation', [RecognitionController::class, 'moderateComment'])->name('comments.moderate');
        });
    });

    Route::get('/employees/{id}/progression', [EmployeeController::class, 'progression'])
        ->name('employees.progression')->where('id', '[0-9a-fA-F-]{36}');

    // Shortcut so the sidebar can link "My Development" without knowing the id.
    Route::get('/my-progression', [EmployeeController::class, 'myProgression'])
        ->name('employees.progression.mine');

    // Reading the directory needs a supervisory role; the controller further
    // narrows a supervisor to their own department.
    Route::prefix('employees')->name('employees.')
        ->middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->name('index');

            Route::middleware('role:admin,hr_manager')->group(function () {
                Route::get('/manager-setup', [EmployeeController::class, 'managerSetup'])->name('manager-setup');
                Route::get('/import/template', [EmployeeController::class, 'downloadTemplate'])->name('import.template');
                Route::post('/import', [EmployeeController::class, 'importCsv'])->name('import');
                Route::get('/create', [EmployeeController::class, 'create'])->name('create');
                Route::post('/', [EmployeeController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [EmployeeController::class, 'edit'])->name('edit');
                Route::put('/{id}', [EmployeeController::class, 'update'])->name('update');
                Route::delete('/{id}', [EmployeeController::class, 'destroy'])->name('destroy');
            });

            Route::get('/{id}', [EmployeeController::class, 'show'])->name('show')->where('id', '[0-9a-fA-F-]{36}');
        });

    // ── Departments ──────────────────────────────────────────
    Route::prefix('departments')->name('departments.')
        ->middleware('role:admin,hr_manager')->group(function () {
            Route::get('/', function () {
                $depts = DB::table('departments as d')
                    ->leftJoin('employees as e', 'd.department_id', '=', 'e.department_id')
                    ->leftJoin('employees as h', 'd.head_employee_id', '=', 'h.employee_id')
                    ->select('d.*',
                        DB::raw('COUNT(e.employee_id) as employee_count'),
                        DB::raw("CONCAT(COALESCE(h.first_name,''),' ',COALESCE(h.last_name,'')) as head_name"))
                    ->groupBy('d.department_id', 'd.name', 'd.department_code', 'd.head_employee_id',
                        'd.parent_dept_id', 'd.is_clinical', 'd.is_active', 'd.created_at', 'd.updated_at',
                        'h.first_name', 'h.last_name')
                    ->orderBy('d.name')->get();

                $employees = DB::table('employees')->where('employment_status', 'active')->orderBy('first_name')->get();

                return view('departments.index', compact('depts', 'employees'));
            })->name('index');

            Route::post('/', function (Request $request) {
                $request->validate([
                    'name' => 'required|string|max:150|unique:departments,name',
                    'department_code' => 'nullable|string|max:20|unique:departments,department_code',
                    'head_employee_id' => 'nullable|string|exists:employees,employee_id',
                    'is_clinical' => 'nullable|boolean',
                    'is_active' => 'nullable|boolean',
                ]);
                DB::table('departments')->insert([
                    'department_id' => Str::uuid(),
                    'name' => $request->name,
                    'department_code' => $request->department_code ?: null,
                    'head_employee_id' => $request->head_employee_id ?: null,
                    'is_clinical' => $request->boolean('is_clinical'),
                    'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return redirect()->route('departments.index')->with('success', 'Department added.');
            })->name('store');

            Route::put('/{id}', function (Request $request, string $id) {
                $dept = DB::table('departments')->where('department_id', $id)->first();
                abort_if(! $dept, 404);

                $request->validate([
                    'name' => 'required|string|max:150|unique:departments,name,'.$id.',department_id',
                    'department_code' => 'nullable|string|max:20|unique:departments,department_code,'.$id.',department_id',
                    'head_employee_id' => 'nullable|string|exists:employees,employee_id',
                    'is_clinical' => 'nullable|boolean',
                    'is_active' => 'nullable|boolean',
                ]);

                DB::table('departments')->where('department_id', $id)->update([
                    'name' => $request->name,
                    'department_code' => $request->department_code ?: null,
                    'head_employee_id' => $request->head_employee_id ?: null,
                    'is_clinical' => $request->boolean('is_clinical'),
                    'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                    'updated_at' => now(),
                ]);

                return redirect()->route('departments.index')->with('success', 'Department updated.');
            })->name('update');

            Route::delete('/{id}', function (string $id) {
                $dept = DB::table('departments')->where('department_id', $id)->first();
                abort_if(! $dept, 404);

                $hasStaff = DB::table('employees')->where('department_id', $id)->exists();
                if ($hasStaff) {
                    return redirect()->route('departments.index')->with('error', 'Cannot delete a department with active staff. Please reassign employees first.');
                }

                DB::table('departments')->where('department_id', $id)->delete();

                return redirect()->route('departments.index')->with('success', 'Department deleted.');
            })->name('destroy');
        });

    // ── AI ───────────────────────────────────────────────────
    // No role: middleware — the assistant is available to every signed-in user.
    // Per-user isolation is enforced inside AiController (see ownedSession()),
    // since ai_chat_messages.session_id carries no FK to scope on.
    Route::post('/ai/query', [AiController::class, 'query'])->middleware('throttle:30,1')->name('ai.query');
    Route::get('/ai/history', [AiController::class, 'history'])->name('ai.history');
    Route::delete('/ai/history', [AiController::class, 'clearHistory'])->name('ai.history.clear');

    // Chat sessions (the sidebar's conversation list).
    Route::get('/ai/sessions', [AiController::class, 'sessions'])->name('ai.sessions');
    Route::post('/ai/sessions', [AiController::class, 'storeSession'])->name('ai.sessions.store');
    Route::get('/ai/sessions/{session}/messages', [AiController::class, 'sessionMessages'])->name('ai.sessions.messages');
    Route::patch('/ai/sessions/{session}', [AiController::class, 'updateSession'])->name('ai.sessions.update');
    Route::delete('/ai/sessions/{session}', [AiController::class, 'destroySession'])->name('ai.sessions.destroy');

    // ── Notifications ─────────────────────────────────────────
    // The topbar bell is fed by a view composer (AppServiceProvider::
    // composeNotifications()), so it needs no index route — only somewhere for
    // "Mark all read" to POST to. Scoped to the caller's own employee_id inside
    // NotificationService, so one employee cannot dismiss another's alerts.
    Route::post('/notifications/read-all', function () {
        $cleared = app(NotificationService::class)
            ->markAllRead(auth()->user()->employee_id);

        return response()->json(['ok' => true, 'cleared' => $cleared]);
    })->name('notifications.read-all');

    Route::post('/notifications/{notification}/read', function (string $notification) {
        $updated = app(NotificationService::class)
            ->markRead(auth()->user()->employee_id, $notification);

        return response()->json(['ok' => true, 'updated' => $updated]);
    })->name('notifications.read');

    Route::post('/log-error', function (Request $request) {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2048'],
            'source' => ['nullable', 'string', 'max:255'],
            'lineno' => ['nullable'],
        ]);

        $message = Str::limit($validated['message'], 2048);
        $source = Str::limit($validated['source'] ?? 'unknown', 255);
        $lineno = $validated['lineno'] ?? '?';

        Log::error("JS ERROR: {$message} in {$source} on line {$lineno}", [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    })->middleware('throttle:5,1')->name('log-error');

    // ── Reports & Analytics (PB-18, PB-19) ───────────────────
    Route::middleware('role:admin,hr_manager,supervisor')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/appraisals/{id}', [ReportController::class, 'appraisalAnalytics'])->name('appraisal.show');
        Route::get('/performance', [ReportController::class, 'performanceReport'])->name('performance');
        Route::get('/performance/export', [ReportController::class, 'exportPerformanceCsv'])->name('performance.export');
        Route::get('/compliance', [ReportController::class, 'complianceReport'])->name('compliance');
        Route::get('/compliance/export', [ReportController::class, 'exportComplianceCsv'])->name('compliance.export');
    });

    // ── User Management ───────────────────────────────────────
    // Creating logins and assigning roles is the keys to the kingdom.
    // No detail page — user records are managed through index/create/edit only.
    Route::resource('users', UserController::class)->except('show')->middleware('role:admin');
    Route::post('/users/{id}/unlock', [UserController::class, 'unlockAccount'])->name('users.unlock')->middleware('role:admin');
    Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active')->middleware('role:admin');
    Route::post('/settings/ai-data-sharing', [UserController::class, 'updateAiSettings'])->name('settings.ai-data-sharing')->middleware('role:admin');
    Route::get('/audit/history', [UserController::class, 'auditHistory'])
        ->middleware('role:admin,hr_manager')->name('audit.history');
    Route::get('/audit/export', [UserController::class, 'exportAuditTrail'])
        ->middleware('role:admin,hr_manager')->name('audit.export');

    // ── Profile ──────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/totp/setup', [ProfileController::class, 'setupTotp'])->name('profile.totp.setup');
    Route::post('/profile/totp/confirm', [ProfileController::class, 'confirmTotp'])->name('profile.totp.confirm');
    Route::post('/profile/totp/disable', [ProfileController::class, 'disableTotp'])->name('profile.totp.disable');
});

require __DIR__.'/auth.php';
