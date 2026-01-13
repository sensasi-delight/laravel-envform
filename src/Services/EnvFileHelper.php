<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Symfony\Component\Finder\Finder;

final class EnvFileHelper
{
    /**
     * @return array<string, string>
     */
    public function findEnvFiles(string $basePath): array
    {
        $files = Finder::create()
            ->files()
            ->in($basePath)
            ->name('.env*')
            ->depth(0)
            ->ignoreDotFiles(false);

        $options = [];
        foreach ($files as $file) {
            $options[$file->getFilename()] = $file->getFilename();
        }

        return $options;
    }
}
