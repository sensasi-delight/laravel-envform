<?php

declare(strict_types=1);

/**
 * Implicit rules for Laravel configuration values.
 *
 * Each rule is a closure that receives the ValueResolver\Service instance
 * and returns the implicit value.
 */
return [
    'cache.stores.database.lock_table' => function (\EnvForm\ValueResolver\Service $resolver) {
        $table = $resolver->resolve('cache.stores.database.table') ?? 'cache';

        return $table.'_lock';
    },
];
