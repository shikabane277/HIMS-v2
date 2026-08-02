<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('manage-performance', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));
        Gate::define('manage-review-cycles', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // Competency assessment and credential verification.
        Gate::define('manage-competency', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // Course/pathway authoring and training scheduling.
        Gate::define('manage-learning', fn ($user) => $user->hasRole('admin', 'hr_manager'));
        Gate::define('manage-training', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // Succession planning is deliberately narrow.
        Gate::define('manage-succession', fn ($user) => $user->hasRole('admin', 'hr_manager'));
        Gate::define('view-succession', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));

        // Organisation-wide analytics vs. own-department analytics.
        Gate::define('view-org-analytics', fn ($user) => $user->hasRole('admin', 'hr_manager'));

        // The AI gap analysis reads across employee performance data.
        Gate::define('run-gap-analysis', fn ($user) => $user->hasRole('admin', 'hr_manager', 'supervisor'));
    }
}
