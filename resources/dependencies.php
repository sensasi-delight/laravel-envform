<?php

declare(strict_types=1);

/**
 * [
 *  dependent config keys pattern => dependant config key pattern,
 *  ...
 * ]
 *
 * @return array<string, string>
 */
return [
    'cache.stores.*' => 'cache.default',
    'database.connections.*' => 'database.default',
    'filesystem.disks.*' => 'filesystem.default',
    'logging.channels.*' => 'logging.default',
    'mail.mailers.*' => 'mail.default',
    'queue.connections.*' => 'queue.default',
];
