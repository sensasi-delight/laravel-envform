<?php

declare(strict_types=1);

namespace EnvForm;

use EnvForm\Console\Commands\EnvForm;
use Illuminate\Support\ServiceProvider;

/**
 * The primary service provider for the EnvForm package.
 * Responsible for bootstrapping commands and binding core services into the Laravel container.
 */
final class EnvFormServiceProvider extends ServiceProvider
{
    final public function register(): void
    {
        $this->app->bind(
            Contracts\EnvFileService::class,
            Services\EnvFile::class
        );

        $this->app->singleton(
            Contracts\EnvRegistryService::class,
            Services\EnvRegistry::class
        );

        $this->app->singleton(
            Contracts\UserSessionService::class, Services\UserSession::class
        );

        $this->app->singleton(Services\EnvManager::class);
    }

    final public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnvForm::class,
            ]);
        }
    }
}
