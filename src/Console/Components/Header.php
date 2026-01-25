<?php

declare(strict_types=1);

namespace EnvForm\Console\Components;

use Symfony\Component\Console\Terminal;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\note;

final class Header
{
    private static ?string $version = null;

    private const ART = <<<'EOT'
  ______            ______
 |  ____|          |  ____|
 | |__   ______   _| |__ ___  _ __ _ __ ___
 |  __| |  _ \ \ / /  __/ _ \| '__| '_ ' _ \
 | |____| | | \ V /| | | (_) | |  | | | | | |
 |______|_| |_|\_/ |_|  \___/|_|  |_| |_| |_|
EOT;

    public static function render(?string $subtitle = null): void
    {
        clear();

        $width = (new Terminal)->getWidth();
        $art = self::getAsciiArt();
        $version = self::getVersion();

        if ($width > 50) {
            if (self::hasColorSupport()) {
                $art = "\e[35m".$art."\e[0m";
            }

            $output = $art.PHP_EOL."  \e[2m".$version."\e[0m";
        } else {
            $output = self::truncate($art, $width);
            $output .= PHP_EOL."\e[2m".$version."\e[0m";
        }

        fwrite(STDOUT, $output.PHP_EOL.PHP_EOL);

        if ($subtitle) {
            note($subtitle);
        }
    }

    private static function getVersion(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }

        $composerPath = __DIR__.'/../../../composer.json';

        if (! file_exists($composerPath)) {
            return self::$version = 'v0.x';
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);

        return self::$version = 'v'.($composer['version'] ?? '0.x');
    }

    private static function getAsciiArt(): string
    {
        return self::ART;
    }

    private static function hasColorSupport(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        return stream_isatty(STDOUT);
    }

    /**
     * @internal Used for testing or manual stripping.
     */
    public static function stripAnsi(string $text): string
    {
        return (string) preg_replace('/\e\[[\d;]*m/', '', $text);
    }

    private static function truncate(string $text, int $width): string
    {
        $lines = explode(PHP_EOL, $text);
        $truncated = [];

        foreach ($lines as $line) {
            $truncated[] = substr($line, 0, $width);
        }

        return implode(PHP_EOL, $truncated);
    }
}
