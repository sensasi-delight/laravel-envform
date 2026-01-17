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

        $this->app->singleton(Services\EnvManager::class);

        // ####### Hints ########

        $this->app->bind(
            Hint\RepositoryContract::class,
            fn () => new Hint\Repository([
                __DIR__.'/../../resources',
            ])
        );

        $this->app->singleton(
            Contracts\UserSessionService::class, Services\UserSession::class
        );

        // #### Registry #####

        $this->app->singleton(Registry\Service::class);

        $this->app->bind(
            Registry\RepositoryContract::class,
            Registry\Repository::class
        );

        $this->app->bind(
            Contracts\WizardService::class,
            Services\Wizard::class
        );
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
