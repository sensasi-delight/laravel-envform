<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Illuminate\Support\Collection;

final class EnvReader
{
    /**
     * @return Collection<int, object{key: string, value: string}>
     */
    final public function read(string $filePath): Collection
    {
        if (! file_exists($filePath)) {
            return collect();
        }

        $lines = file(
            $filePath,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        if ($lines === false) {
            return collect();
        }

        $data = collect();

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (\count($parts) === 2) {
                $data->add(
                    (object) [
                        'key' => trim($parts[0]),
                        'value' => trim(
                            $parts[1],
                            " '\""
                        ),
                    ]
                );
            }
        }

        return $data;
    }
}
