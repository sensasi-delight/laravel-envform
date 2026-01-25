<?php

declare(strict_types=1);

/**
 * Service to Driver Mapping.
 *
 * This file defines third-party services (Redis, AWS, Mailgun, etc.)
 * and the subsystem drivers that activate them.
 */
return [
    'redis' => [
        'activators' => [
            'cache.default' => ['redis'],
            'session.driver' => ['redis'],
            'queue.default' => ['redis'],
        ],
        'master_keys' => [
            'database.redis.default.host',
        ],
        'patterns' => [
            'database.redis.*',
        ],
    ],
    'aws' => [
        'activators' => [
            'cache.default' => ['dynamodb'],
            'queue.default' => ['sqs'],
            'filesystems.default' => ['s3'],
            'mail.default' => ['ses'],
        ],
        'master_keys' => [
            'services.sqs.key',
            'services.s3.key',
            'services.ses.key',
        ],
        'patterns' => [
            'services.sqs.*',
            'services.s3.*',
            'services.ses.*',
        ],
    ],
    'mailgun' => [
        'activators' => [
            'mail.default' => ['mailgun'],
        ],
        'master_keys' => [
            'services.mailgun.secret',
        ],
        'patterns' => [
            'services.mailgun.*',
        ],
    ],
    'postmark' => [
        'activators' => [
            'mail.default' => ['postmark'],
        ],
        'master_keys' => [
            'services.postmark.token',
        ],
        'patterns' => [
            'services.postmark.*',
        ],
    ],
];
