<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Services\Ai\AiManager;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AiManager resolves the configured AI driver; the AiProvider contract
        // resolves to whichever provider AI_PROVIDER selects (default: gemini).
        $this->app->singleton(AiManager::class, fn ($app) => new AiManager(
            (array) $app['config']->get('services.ai', [])
        ));

        $this->app->bind(AiProvider::class, fn ($app) => $app->make(AiManager::class)->provider());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->composeNotifications();

        Mail::extend('brevo', function (array $config) {
            $key = $config['key'] ?? config('services.brevo.key');

            return new BrevoApiTransport($key);
        });
    }

    /**
     * Feed the topbar bell.
     *
     * A view composer rather than 32 controller edits: every domain screen
     * extends layouts.hims, and none of them should have to remember to pass
     * notification data down. The query is skipped entirely for guests and for
     * accounts with no linked employee profile, which is what the auth screens
     * and the seeded admin are.
     */
    private function composeNotifications(): void
    {
        View::composer('layouts.hims', function ($view) {
            $user = auth()->user();
            $employeeId = $user?->employee_id;
            $notifications = $this->app->make(NotificationService::class);

            $view->with([
                'himsNotifications' => $user ? $notifications->feedFor($user, 12) : collect(),
                'himsUnreadCount' => $notifications->unreadCount($employeeId),
            ]);
        });
    }

    /**
     * Authorisation gates for the HIMS modules.
     *
     * users.role is one of: admin | hr_manager | supervisor | staff
     * These back both route middleware and @can checks in the sidebar/views,
     * so navigation and enforcement can never drift apart.
     */
    private function registerGates(): void
    {
        // Account and organisation administration.
        Gate::define('manage-users', fn ($user) => $user->isAdmin());
        Gate::define('manage-departments', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Employee records: HR owns the master data, supervisors read their own team.
        Gate::define('manage-employees', fn ($user) => $user->hasRole('admin', 'hr_manager'));
        Gate::define('view-employees', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // Performance: department heads run reviews for their own people.
        //
        // These two are the coarse door — they decide who sees the review
        // screens at all. They do NOT decide who may write a given review:
        // that is identity, checked per-record in PerformanceController against
        // employees.supervisor_id, because holding a role says nothing about
        // whether a particular person answers to you.
        Gate::define('manage-performance', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));
        Gate::define('manage-review-cycles', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Competency assessment and credential verification.
        Gate::define('manage-competency', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // The framework itself — the domains, and the categories and
        // competencies beneath them — is narrower than assessing against it: a
        // supervisor rates their ward against the framework but does not get to
        // redraw it. Mirrors the `role:admin,hr_manager` band the domain routes
        // already carry. It exists because a modal a supervisor can open is a
        // modal they can fill in and submit; the full-page form it replaced at
        // least refused them at the door, before they typed anything.
        Gate::define('manage-competency-framework', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Course/pathway authoring and training scheduling.
        Gate::define('manage-learning', fn ($user) => $user->hasRole('admin', 'hr_manager'));
        Gate::define('manage-training', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // The venue register is narrower than scheduling into it: a supervisor
        // books a room for their ward's session but does not get to add rooms to
        // the hospital's facility list. Mirrors the `role:admin,hr_manager` band
        // the venue route already carries. Same reason as the competency
        // framework gate — the Venues page is open to every role, so the trigger
        // that opens the modal needs the check the deleted GET page got for free
        // from its route.
        Gate::define('manage-venues', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Recording that somebody finished a course. Wider than manage-learning
        // (a supervisor knows who on their ward has done the fire drill) but
        // deliberately excludes staff: completion is a statement about a person,
        // so nobody self-certifies. Mirrors the session check-in rule.
        Gate::define('record-completion', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // Compliance oversight: supervisors see their own department's standing,
        // HR/admin set the policy behind it and read the hospital-wide reports.
        Gate::define('view-compliance', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));
        Gate::define('manage-compliance', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Succession planning is deliberately narrow.
        Gate::define('manage-succession', fn ($user) => $user->hasRole('admin', 'hr_manager'));
        Gate::define('view-succession', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // Posting is peer-to-peer and open to every signed-in role. Badge
        // governance and moderation are institutional responsibilities.
        Gate::define('manage-recognition', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Audit payloads can contain performance, succession and confidential
        // case state, so only the roles responsible for investigations read it.
        Gate::define('view-audit-history', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Organisation-wide analytics vs. own-department analytics.
        Gate::define('view-org-analytics', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // The AI gap analysis reads across employee performance data.
        Gate::define('run-gap-analysis', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));
    }
}
