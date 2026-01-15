<?php

declare(strict_types=1);

namespace EnvForm;

use EnvForm\Console\Commands\Main;
use Illuminate\Support\ServiceProvider;

final class EnvFormServiceProvider extends ServiceProvider
{
    final public function register(): void
    {
        $this->app->singleton(Services\EnvironmentState::class);
        $this->app->bind(Contracts\InputProvider::class, Services\EnvironmentState::class);
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
