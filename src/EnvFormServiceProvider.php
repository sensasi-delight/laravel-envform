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
        // ####### DotEnv ########

        $this->app->singleton(DotEnv\Service::class);

        // ####### Hints ########

        $this->app->bind(
            Hint\Repository::class,
            fn () => new Hint\Repository([
                __DIR__.'/../../resources',
            ])
        );

        // ####### FormValue ########

        $this->app->singleton(FormValue\Service::class);

        // #### Registry #####

        $this->app->singleton(Registry\Service::class);
        $this->app->bind(KeyGenerator\Service::class);
        $this->app->bind(OptionResolver\Service::class);

        // #### ShouldAsk #####

        $this->app->singleton(ShouldAsk\Service::class);
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
