<?php

declare(strict_types=1);

namespace EnvForm;

use EnvForm\Console\Commands\Main;
use Illuminate\Support\ServiceProvider;

final class EnvFormServiceProvider extends ServiceProvider
{
    final public function register(): void
    {
        $this->app->singleton(Services\KeyManager::class);
        $this->app->bind(Contracts\FormValueProvider::class, Services\KeyManager::class);
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
