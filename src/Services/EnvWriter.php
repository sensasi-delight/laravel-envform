<?php

declare(strict_types=1);

namespace EnvForm\Services;

use Illuminate\Support\Facades\File;

final class EnvWriter
{
    final public function __construct(
        protected string $path
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    final public function update(array $values): void
    {
        $content = File::exists($this->path) ? File::get($this->path) : '';
        /** @var array<int, string> $lines */
        $lines = explode("\n", $content);

        $newContent = [];
        $processedKeys = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                $newContent[] = $line;

                continue;
            }

            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);

            if (\array_key_exists($key, $values)) {
                $val = $this->formatValue($values[$key]);
                $newContent[] = "{$key}={$val}";
                $processedKeys[] = $key;
            } else {
                $newContent[] = $line; // Keep existing
            }
        }

        // Append new keys
        $addedAny = false;
        foreach ($values as $key => $value) {
            if (! in_array($key, $processedKeys, true)) {
                if (! $addedAny && ! empty($newContent)) {
                    $newContent[] = ''; // Add spacing before new block
                    $addedAny = true;
                }
                $val = $this->formatValue($value);
                $newContent[] = "{$key}={$val}";
            }
        }

        File::put($this->path, implode("\n", $newContent));
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        $stringVal = (string) $value;

        if (str_contains($stringVal, ' ') || str_contains($stringVal, '#')) {
            return '"'.$stringVal.'"';
        }

        return $stringVal;
    }
}
