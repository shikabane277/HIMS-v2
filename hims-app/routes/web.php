<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\CompetencyController;
use App\Http\Controllers\GapAnalysisController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\SuccessionController;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Public ──────────────────────────────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

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

    // ── Performance Management ───────────────────────────────
    Route::prefix('performance')->name('performance.')->group(function () {
        Route::get('/',                         [PerformanceController::class, 'index'])->name('index');
        Route::get('/reviews',                  [PerformanceController::class, 'reviewsIndex'])->name('reviews.index');

        // Creating and scoring reviews is for admin, HR and department heads.
        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/reviews/create',       [PerformanceController::class, 'createReview'])->name('reviews.create');
            Route::post('/reviews',             [PerformanceController::class, 'storeReview'])->name('reviews.store');
            Route::get('/reviews/{id}/score',   [PerformanceController::class, 'scoreReview'])->name('reviews.score');
            Route::put('/reviews/{id}/score',   [PerformanceController::class, 'saveScores'])->name('reviews.score.save');
        });

        Route::get('/reviews/{id}',             [PerformanceController::class, 'show'])->name('show');

        Route::prefix('cycles')->name('cycles.')->group(function () {
            // Cycle administration is HR/admin only.
            Route::middleware('role:admin,hr_manager')->group(function () {
                Route::get('/create',           [PerformanceController::class, 'createCycle'])->name('create');
                Route::post('/',                [PerformanceController::class, 'storeCycle'])->name('store');
                Route::get('/{id}/edit',        [PerformanceController::class, 'editCycle'])->name('edit');
                Route::put('/{id}',             [PerformanceController::class, 'updateCycle'])->name('update');
            });
            Route::get('/{id}',                 [PerformanceController::class, 'showCycle'])->name('show');
        });
    });

    // ── Competency Management ────────────────────────────────
    Route::prefix('competency')->name('competency.')->group(function () {
        Route::get('/',                             [CompetencyController::class, 'index'])->name('index');
        Route::get('/credentials',                  [CompetencyController::class, 'credentialsIndex'])->name('credentials.index');

        // Assessing staff and registering credentials is a supervisory action.
        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/assessments/create',       [CompetencyController::class, 'createAssessment'])->name('assessments.create');
            Route::post('/assessments',             [CompetencyController::class, 'storeAssessment'])->name('assessments.store');
            Route::get('/credentials/create',       [CompetencyController::class, 'createCredential'])->name('credentials.create');
            Route::post('/credentials',             [CompetencyController::class, 'storeCredential'])->name('credentials.store');
        });

        // The framework itself is HR/admin territory.
        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/domains/create',           [CompetencyController::class, 'createDomain'])->name('domains.create');
            Route::post('/domains',                 [CompetencyController::class, 'storeDomain'])->name('domains.store');
        });

        // ── AI-Assisted Competency Gap Analysis (Objective 6) ──
        Route::middleware('role:admin,hr_manager,supervisor')->prefix('gap-analysis')->name('gap.')->group(function () {
            Route::get('/',                          [GapAnalysisController::class, 'index'])->name('index');
            Route::get('/department',                [GapAnalysisController::class, 'department'])->name('department');
            Route::get('/employee/{employeeId}',     [GapAnalysisController::class, 'employee'])->name('employee');
            Route::get('/employee/{employeeId}/json',[GapAnalysisController::class, 'employeeJson'])->name('employee.json');
        });

        // Wildcard last so it cannot swallow /domains/create.
        Route::get('/domains/{id}',                 [CompetencyController::class, 'showDomain'])->name('domains.show');
    });

    // ── Learning Management ──────────────────────────────────
    Route::prefix('learning')->name('learning.')->group(function () {
        Route::get('/',                             [LearningController::class, 'index'])->name('index');
        Route::get('/pathways',                     [LearningController::class, 'pathwaysIndex'])->name('pathways.index');
        Route::get('/cpd',                          [LearningController::class, 'cpdIndex'])->name('cpd.index');

        // Authoring the catalogue is HR/admin only.
        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/courses/create',           [LearningController::class, 'createCourse'])->name('courses.create');
            Route::post('/courses',                 [LearningController::class, 'storeCourse'])->name('courses.store');
            Route::get('/pathways/create',          [LearningController::class, 'createPathway'])->name('pathways.create');
            Route::post('/pathways',                [LearningController::class, 'storePathway'])->name('pathways.store');
        });

        Route::get('/courses/{id}',                 [LearningController::class, 'showCourse'])->name('courses.show');
        Route::post('/courses/{id}/enroll',         [LearningController::class, 'enroll'])->name('enroll');
    });

    // ── Training Management ──────────────────────────────────
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/',                             [TrainingController::class, 'index'])->name('index');
        Route::get('/venues',                       [TrainingController::class, 'venuesIndex'])->name('venues.index');

        Route::middleware('role:admin,hr_manager,supervisor')->group(function () {
            Route::get('/sessions/create',          [TrainingController::class, 'createSession'])->name('sessions.create');
            Route::post('/sessions',                [TrainingController::class, 'storeSession'])->name('sessions.store');
        });

        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/venues/create',            [TrainingController::class, 'createVenue'])->name('venues.create');
            Route::post('/venues',                  [TrainingController::class, 'storeVenue'])->name('venues.store');
        });

        Route::get('/sessions/{id}',                [TrainingController::class, 'showSession'])->name('sessions.show');
        Route::post('/sessions/{id}/register',      [TrainingController::class, 'register'])->name('register');
    });

    // ── Succession Planning ──────────────────────────────────
    // Talent pipeline data is sensitive; staff have no access at all.
    Route::prefix('succession')->name('succession.')
        ->middleware('role:admin,hr_manager,supervisor')->group(function () {
        Route::get('/',                             [SuccessionController::class, 'index'])->name('index');
        Route::get('/positions',                    [SuccessionController::class, 'positionsIndex'])->name('positions.index');

        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/positions/create',         [SuccessionController::class, 'createPosition'])->name('positions.create');
            Route::post('/positions',               [SuccessionController::class, 'storePosition'])->name('positions.store');
            Route::get('/candidates/create',        [SuccessionController::class, 'createCandidate'])->name('candidates.create');
            Route::post('/candidates',              [SuccessionController::class, 'storeCandidate'])->name('candidates.store');
            Route::get('/candidates/{id}/edit',     [SuccessionController::class, 'editCandidate'])->name('candidates.edit');
            Route::put('/candidates/{id}',          [SuccessionController::class, 'updateCandidate'])->name('candidates.update');
            Route::delete('/candidates/{id}',       [SuccessionController::class, 'withdrawCandidate'])->name('candidates.withdraw');
        });

        // Development milestones: supervisors maintain the plans for the people
        // they have nominated, so these stay at the group's access level.
        Route::post('/candidates/{id}/milestones',                 [SuccessionController::class, 'storeMilestone'])->name('milestones.store');
        Route::put('/candidates/{id}/milestones/{pathId}',         [SuccessionController::class, 'updateMilestone'])->name('milestones.update');
        Route::delete('/candidates/{id}/milestones/{pathId}',      [SuccessionController::class, 'destroyMilestone'])->name('milestones.destroy');

        // Wildcards last so they cannot swallow /positions/create or /candidates/create.
        Route::get('/positions/{id}',               [SuccessionController::class, 'showPosition'])->name('positions.show');
        Route::get('/candidates/{id}',              [SuccessionController::class, 'showCandidate'])->name('candidates.show');
    });

    // ── Social Recognition ───────────────────────────────────
    // Deliberately open to every role — peer recognition is the point.
    Route::prefix('recognition')->name('recognition.')->group(function () {
        Route::get('/',                             [RecognitionController::class, 'index'])->name('index');
        Route::get('/posts/create',                 [RecognitionController::class, 'createPost'])->name('posts.create');
        Route::post('/posts',                       [RecognitionController::class, 'storePost'])->name('posts.store');
        Route::post('/posts/{id}/react',            [RecognitionController::class, 'react'])->name('react');
        Route::post('/posts/{id}/comments',         [RecognitionController::class, 'storeComment'])->name('comments.store');

        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/badges/create',            [RecognitionController::class, 'createBadge'])->name('badges.create');
            Route::post('/badges',                  [RecognitionController::class, 'storeBadge'])->name('badges.store');
        });
    });

    // ── Employees ────────────────────────────────────────────
    // Reading the directory needs a supervisory role; the controller further
    // narrows a supervisor to their own department.
    Route::prefix('employees')->name('employees.')
        ->middleware('role:admin,hr_manager,supervisor')->group(function () {
        Route::get('/',           [EmployeeController::class, 'index'])->name('index');
        Route::get('/{id}',       [EmployeeController::class, 'show'])->name('show')->where('id', '[0-9a-fA-F-]{36}');

        Route::middleware('role:admin,hr_manager')->group(function () {
            Route::get('/create',     [EmployeeController::class, 'create'])->name('create');
            Route::post('/',          [EmployeeController::class, 'store'])->name('store');
            Route::get('/{id}/edit',  [EmployeeController::class, 'edit'])->name('edit');
            Route::put('/{id}',       [EmployeeController::class, 'update'])->name('update');
            Route::delete('/{id}',    [EmployeeController::class, 'destroy'])->name('destroy');
        });
    });

    // ── Departments ──────────────────────────────────────────
    Route::prefix('departments')->name('departments.')
        ->middleware('role:admin,hr_manager')->group(function () {
        Route::get('/', function () {
            $depts = \Illuminate\Support\Facades\DB::table('departments as d')
                ->leftJoin('employees as e','d.department_id','=','e.department_id')
                ->leftJoin('employees as h','d.head_employee_id','=','h.employee_id')
                ->select('d.*',
                    \Illuminate\Support\Facades\DB::raw('COUNT(e.employee_id) as employee_count'),
                    \Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(h.first_name,''),' ',COALESCE(h.last_name,'')) as head_name"))
                ->groupBy('d.department_id','d.name','d.department_code','d.head_employee_id',
                          'd.parent_dept_id','d.is_clinical','d.created_at','d.updated_at',
                          'h.first_name','h.last_name')
                ->orderBy('d.name')->get();
            return view('departments.index', compact('depts'));
        })->name('index');

        Route::post('/', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'name'            => 'required|string|max:150|unique:departments,name',
                'department_code' => 'nullable|string|max:20|unique:departments,department_code',
            ]);
            \Illuminate\Support\Facades\DB::table('departments')->insert([
                'department_id'   => \Illuminate\Support\Str::uuid(),
                'name'            => $request->name,
                'department_code' => $request->department_code ?: null,
                'is_clinical'     => $request->boolean('is_clinical'),
                'created_at'      => now(), 'updated_at' => now(),
            ]);
            return redirect()->route('departments.index')->with('success','Department added.');
        })->name('store');
    });

    // ── AI ───────────────────────────────────────────────────
    Route::post('/ai/query',          [AiController::class, 'query'])->name('ai.query');
    Route::get('/ai/history',         [AiController::class, 'history'])->name('ai.history');
    Route::delete('/ai/history',      [AiController::class, 'clearHistory'])->name('ai.history.clear');

    Route::post('/log-error', function (\Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\Log::error('JS ERROR: ' . $request->input('message') . ' in ' . $request->input('source') . ' on line ' . $request->input('lineno'));
        return response()->json(['ok' => true]);
    })->name('log-error');

    // ── User Management ───────────────────────────────────────
    // Creating logins and assigning roles is the keys to the kingdom.
    // No detail page — user records are managed through index/create/edit only.
    Route::resource('users', UserController::class)->except('show')->middleware('role:admin');

    // ── Profile ──────────────────────────────────────────────
    Route::get('/profile',      [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',    [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',   [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
