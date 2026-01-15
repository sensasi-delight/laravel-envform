<?php

declare(strict_types=1);

namespace EnvForm;

use EnvForm\Console\Commands\Main;
use Illuminate\Support\ServiceProvider;

/**
 * The primary service provider for the EnvForm package.
 * Responsible for bootstrapping commands and binding core services into the Laravel container.
 */
final class EnvFormServiceProvider extends ServiceProvider
{
    final public function register(): void
    {
        $this->app->singleton(Services\EnvRegistry::class);
        $this->app->singleton(Services\UserSession::class);
        $this->app->singleton(Services\EnvManager::class);
        $this->app->bind(Contracts\InputProvider::class, Services\UserSession::class);
    }

    final public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Main::class,
            ]);
        }
    }
}
