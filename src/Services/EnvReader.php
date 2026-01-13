<?php

declare(strict_types=1);

namespace EnvForm\Services;

final class EnvReader
{
    /**
     * @return array<string, string>
     */
    final public function read(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $data = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $data[trim($parts[0])] = trim($parts[1], " '\"");
            }
        }

        return $data; // Strict return type array
    }
}
