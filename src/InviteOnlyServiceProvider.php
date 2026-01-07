<?php

declare(strict_types=1);

namespace OffloadProject\InviteOnly;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OffloadProject\InviteOnly\Console\SendInvitationRemindersCommand;
use OffloadProject\InviteOnly\Contracts\InviteOnlyContract;
use OffloadProject\InviteOnly\Http\Controllers\InvitationController;

final class InviteOnlyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/invite-only.php', 'invite-only');

        $this->app->singleton(InviteOnlyContract::class, InviteOnly::class);
        $this->app->alias(InviteOnlyContract::class, 'invite-only');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/invite-only.php' => config_path('invite-only.php'),
        ], 'invite-only-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'invite-only-migrations');

        $this->registerRoutes();
        $this->registerCommands();
    }

    private function registerRoutes(): void
    {
        if (! config('invite-only.routes.enabled', true)) {
            return;
        }

        Route::prefix(config('invite-only.routes.prefix', 'invitations'))
            ->middleware(config('invite-only.routes.middleware', ['web']))
            ->group(function (): void {
                Route::get('{token}', [InvitationController::class, 'show'])
                    ->name('invite-only.invitations.show');
                Route::post('{token}/accept', [InvitationController::class, 'accept'])
                    ->name('invite-only.invitations.accept');
                Route::post('{token}/decline', [InvitationController::class, 'decline'])
                    ->name('invite-only.invitations.decline');
            });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendInvitationRemindersCommand::class,
            ]);
        }
    }
}
