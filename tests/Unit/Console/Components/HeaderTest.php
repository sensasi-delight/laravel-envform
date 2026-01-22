<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Components;

use EnvForm\Console\Components\Header;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class HeaderTest extends TestCase
{
    #[Test]
    public function it_strips_ansi_codes(): void
    {
        $input = "\e[35mEnvForm\e[0m";
        $expected = 'EnvForm';

        // Assuming stripAnsi is static private/protected
        $method = new ReflectionMethod(Header::class, 'stripAnsi');
        $method->setAccessible(true);

        // invoke(null) for static
        $this->assertSame($expected, $method->invoke(null, $input));
    }

    #[Test]
    public function it_truncates_text_correctly(): void
    {
        $input = 'EnvForm Wizard';
        $width = 7;

        $method = new ReflectionMethod(Header::class, 'truncate');
        $method->setAccessible(true);

        $this->assertSame('EnvForm', $method->invoke(null, $input, $width));
    }
}
