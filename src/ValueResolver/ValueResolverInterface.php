<?php

declare(strict_types=1);

namespace EnvForm\ValueResolver;

interface ValueResolverInterface
{
    /**
     * Resolves a value for a given config path or environment key.
     * Priority: FormValue > DotEnv > Config Default > Implicit
     *
     * @param  string  $key  Dot-notation config path or Env Key
     *
     * @throws \LogicException On circular dependencies
     */
    public function resolve(string $key): mixed;

    /**
     * Determine if a value is explicitly set in FormValue or DotEnv.
     */
    public function has(string $key): bool;
}
