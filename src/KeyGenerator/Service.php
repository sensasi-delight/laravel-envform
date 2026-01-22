<?php

declare(strict_types=1);

namespace EnvForm\KeyGenerator;

use Illuminate\Support\Facades\Artisan;

readonly class Service
{
    /**
     * Generate a new application key.
     */
    public function generate(): string
    {
        Artisan::call('key:generate', ['--show' => true]);

        return trim(Artisan::output());
    }
}
