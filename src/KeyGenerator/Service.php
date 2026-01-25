<?php

declare(strict_types=1);

namespace EnvForm\KeyGenerator;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;

readonly class Service
{
    /**
     * Generate a new application key using Laravel's internal logic.
     */
    public function generate(): string
    {
        $cipher = Config::get('app.cipher', 'AES-256-CBC');

        return 'base64:'.base64_encode(Encrypter::generateKey($cipher));
    }
}
