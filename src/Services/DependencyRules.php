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
     * @todo Implement dynamic rules that constructed from scanning config files.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public static function getRules(): array
    {
        return [
            'cache.default' => [
                'array' => ['cache.stores.array.*'],
                'database' => ['cache.stores.database.*'],
                'file' => ['cache.stores.file.*'],
                'memcached' => ['cache.stores.memcached.*'],
                'redis' => ['cache.stores.redis.*', 'database.redis.*'],
                'dynamodb' => ['cache.stores.dynamodb.*', 'services.dynamodb.*'],
                'octane' => ['cache.stores.octane.*'],
                'failover' => ['cache.stores.failover.*'],
                'null' => ['cache.stores.null.*'],
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
