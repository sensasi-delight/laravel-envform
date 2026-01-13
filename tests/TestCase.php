<?php

declare(strict_types=1);

namespace Tests;

use EnvForm\EnvFormServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            EnvFormServiceProvider::class,
        ];
    }
}
