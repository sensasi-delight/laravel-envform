<?php

declare(strict_types=1);

namespace EnvForm;

if (! function_exists('\EnvForm\addLeadingWhitespace')) {
    /**
     * Add leading whitespace to a value.
     */
    function addLeadingWhitespace(
        int|string $value
    ): string {
        return str_pad(
            (string) $value,
            2,
            ' ',
            STR_PAD_LEFT
        );
    }
}
