<?php

declare(strict_types=1);

namespace EnvForm\Services;

final class DependencyRules
{
    /**
     * Rules definition:
     * 'config.path' => [
     *    'value' => ['dependent.config.path.wildcard.*']
     * ]
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public static function getRules(): array
    {
        return [
            'cache.default' => [
                'redis' => ['cache.stores.redis.*', 'database.redis.*'],
                'memcached' => ['cache.stores.memcached.*'],
                'dynamodb' => ['cache.stores.dynamodb.*', 'services.dynamodb.*'],
            ],
            'database.default' => [
                'mysql' => ['database.connections.mysql.*'],
                'pgsql' => ['database.connections.pgsql.*'],
                'sqlsrv' => ['database.connections.sqlsrv.*'],
                'mariadb' => ['database.connections.mariadb.*'],
                'sqlite' => ['database.connections.sqlite.*'],
            ],
            'queue.default' => [
                'database' => ['queue.connections.database.*'],
                'beanstalkd' => ['queue.connections.beanstalkd.*'],
                'sqs' => ['queue.connections.sqs.*', 'services.sqs.*'],
                'redis' => ['queue.connections.redis.*'],
            ],
            'mail.default' => [
                'smtp' => ['mail.mailers.smtp.*'],
                'ses' => ['mail.mailers.ses.*', 'services.ses.*'],
                'mailgun' => ['mail.mailers.mailgun.*', 'services.mailgun.*'],
                'postmark' => ['mail.mailers.postmark.*', 'services.postmark.*'],
            ],
            'filesystem.default' => [
                's3' => ['filesystems.disks.s3.*', 'services.s3.*'],
            ],
        ];
    }
}
