<?php

namespace SuperAudit\SuperAudit;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use SuperAudit\SuperAudit\Commands\SetupAuditTriggers;
use SuperAudit\SuperAudit\Commands\DropAuditTriggers;
use SuperAudit\SuperAudit\Commands\RebuildAuditTriggers;
use SuperAudit\SuperAudit\Middleware\SetAuditVariables;

/**
 * SuperAuditServiceProvider
 * 
 * Registers the Super Audit package with Laravel.
 * Publishes configuration, migrations, and registers commands and middleware.
 */
class SuperAuditServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge default configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/super-audit.php', 
            'super-audit'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/super-audit.php' => config_path('super-audit.php'),
        ], 'super-audit-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/Migrations/2024_01_01_000000_create_audit_logs_table.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_audit_logs_table.php'),
        ], 'super-audit-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupAuditTriggers::class,
                DropAuditTriggers::class,
                RebuildAuditTriggers::class,
            ]);

            // Set session variables for console commands
            try {
                DB::statement("SET @current_user_id = NULL");
                DB::statement("SET @current_url = NULL");
            } catch (\Exception $e) {
                // Database might not be available yet
            }
        }

        // Register middleware
        if (config('super-audit.auto_register_middleware', true)) {
            $router = $this->app['router'];
            $router->pushMiddlewareToGroup('web', SetAuditVariables::class);
            $router->pushMiddlewareToGroup('api', SetAuditVariables::class);
        }
    }
}
