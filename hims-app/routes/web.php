<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\CompetencyController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\SuccessionController;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AiController;
use Illuminate\Support\Facades\Route;

// ── Public ──────────────────────────────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

// ── Authenticated Routes ─────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Performance Management ───────────────────────────────
    Route::prefix('performance')->name('performance.')->group(function () {
        Route::get('/',                         [PerformanceController::class, 'index'])->name('index');
        Route::get('/reviews',                  [PerformanceController::class, 'reviewsIndex'])->name('reviews.index');
        Route::get('/reviews/{id}',             [PerformanceController::class, 'show'])->name('show');
        Route::prefix('cycles')->name('cycles.')->group(function () {
            Route::get('/create',               [PerformanceController::class, 'createCycle'])->name('create');
            Route::post('/',                    [PerformanceController::class, 'storeCycle'])->name('store');
            Route::get('/{id}',                 [PerformanceController::class, 'showCycle'])->name('show');
            Route::get('/{id}/edit',            [PerformanceController::class, 'editCycle'])->name('edit');
        });
    });

    // ── Competency Management ────────────────────────────────
    Route::prefix('competency')->name('competency.')->group(function () {
        Route::get('/',                             [CompetencyController::class, 'index'])->name('index');
        Route::get('/assessments/create',           [CompetencyController::class, 'createAssessment'])->name('assessments.create');
        Route::post('/assessments',                 [CompetencyController::class, 'storeAssessment'])->name('assessments.store');
        Route::get('/credentials',                  [CompetencyController::class, 'credentialsIndex'])->name('credentials.index');
        Route::get('/credentials/create',           [CompetencyController::class, 'createCredential'])->name('credentials.create');
        Route::post('/credentials',                 [CompetencyController::class, 'storeCredential'])->name('credentials.store');
        Route::get('/domains/create',               [CompetencyController::class, 'createDomain'])->name('domains.create');
        Route::post('/domains',                     [CompetencyController::class, 'storeDomain'])->name('domains.store');
        Route::get('/domains/{id}',                 [CompetencyController::class, 'showDomain'])->name('domains.show');
    });

    // ── Learning Management ──────────────────────────────────
    Route::prefix('learning')->name('learning.')->group(function () {
        Route::get('/',                             [LearningController::class, 'index'])->name('index');
        Route::get('/courses/create',               [LearningController::class, 'createCourse'])->name('courses.create');
        Route::post('/courses',                     [LearningController::class, 'storeCourse'])->name('courses.store');
        Route::get('/courses/{id}',                 [LearningController::class, 'showCourse'])->name('courses.show');
        Route::post('/courses/{id}/enroll',         [LearningController::class, 'enroll'])->name('enroll');
        Route::get('/pathways',                     [LearningController::class, 'pathwaysIndex'])->name('pathways.index');
        Route::get('/pathways/create',              [LearningController::class, 'createPathway'])->name('pathways.create');
        Route::post('/pathways',                    [LearningController::class, 'storePathway'])->name('pathways.store');
        Route::get('/cpd',                          [LearningController::class, 'cpdIndex'])->name('cpd.index');
    });

    // ── Training Management ──────────────────────────────────
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/',                             [TrainingController::class, 'index'])->name('index');
        Route::get('/sessions/create',              [TrainingController::class, 'createSession'])->name('sessions.create');
        Route::post('/sessions',                    [TrainingController::class, 'storeSession'])->name('sessions.store');
        Route::get('/sessions/{id}',                [TrainingController::class, 'showSession'])->name('sessions.show');
        Route::post('/sessions/{id}/register',      [TrainingController::class, 'register'])->name('register');
        Route::get('/venues',                       [TrainingController::class, 'venuesIndex'])->name('venues.index');
        Route::get('/venues/create',                [TrainingController::class, 'createVenue'])->name('venues.create');
        Route::post('/venues',                      [TrainingController::class, 'storeVenue'])->name('venues.store');
    });

    // ── Succession Planning ──────────────────────────────────
    Route::prefix('succession')->name('succession.')->group(function () {
        Route::get('/',                             [SuccessionController::class, 'index'])->name('index');
        Route::get('/positions',                    [SuccessionController::class, 'positionsIndex'])->name('positions.index');
        Route::get('/positions/create',             [SuccessionController::class, 'createPosition'])->name('positions.create');
        Route::post('/positions',                   [SuccessionController::class, 'storePosition'])->name('positions.store');
        Route::get('/positions/{id}',               [SuccessionController::class, 'showPosition'])->name('positions.show');
        Route::get('/candidates/create',            [SuccessionController::class, 'createCandidate'])->name('candidates.create');
        Route::post('/candidates',                  [SuccessionController::class, 'storeCandidate'])->name('candidates.store');
        Route::get('/candidates/{id}',              [SuccessionController::class, 'showCandidate'])->name('candidates.show');
    });

    // ── Social Recognition ───────────────────────────────────
    Route::prefix('recognition')->name('recognition.')->group(function () {
        Route::get('/',                             [RecognitionController::class, 'index'])->name('index');
        Route::get('/posts/create',                 [RecognitionController::class, 'createPost'])->name('posts.create');
        Route::post('/posts',                       [RecognitionController::class, 'storePost'])->name('posts.store');
        Route::post('/posts/{id}/react',            [RecognitionController::class, 'react'])->name('react');
        Route::post('/posts/{id}/comments',         [RecognitionController::class, 'storeComment'])->name('comments.store');
        Route::get('/badges/create',                [RecognitionController::class, 'createBadge'])->name('badges.create');
        Route::post('/badges',                      [RecognitionController::class, 'storeBadge'])->name('badges.store');
    });

    // ── Employees & Departments ──────────────────────────────
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/',         [EmployeeController::class, 'index'])->name('index');
        Route::get('/create',   [EmployeeController::class, 'create'])->name('create');
        Route::post('/',        [EmployeeController::class, 'store'])->name('store');
        Route::get('/{id}',     [EmployeeController::class, 'show'])->name('show');
    });

    Route::prefix('departments')->name('departments.')->group(function () {
        Route::get('/', function () {
            $depts = \Illuminate\Support\Facades\DB::table('departments')->orderBy('name')->get();
            return view('departments.index', compact('depts'));
        })->name('index');
    });

    // ── AI ───────────────────────────────────────────────────
    Route::post('/ai/query', [AiController::class, 'query'])->name('ai.query');

    // ── Profile ──────────────────────────────────────────────
    Route::get('/profile',      [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',    [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',   [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
