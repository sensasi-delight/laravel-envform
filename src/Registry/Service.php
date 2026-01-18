<?php

declare(strict_types=1);

namespace EnvForm\Registry;

use EnvForm\DTO\EnvVar;
use Illuminate\Support\Collection;

final readonly class Service
{
    /** @var Collection<int, EnvVar> */
    private Collection $vars;

    /**
     * @throws \Exception
     */
    public function __construct(
        private readonly RepositoryContract $repository,
    ) {
        $rawFindings = $this->repository->scan();
        $rules = $this->getAllRules();

        $this->vars = $rawFindings->groupBy('envKey')
            ->map(function (Collection $occurrences, string $envKey) use ($rules) {
                $configKeys = $occurrences->pluck('configKey');
                $firstOccurrence = $occurrences->first();

                if ($firstOccurrence === null) {
                    throw new \Exception("Could not find any occurrences for {$envKey}", 1);
                }

                // Calculate dependencies based on rules
                $dependencies = [];
                foreach ($rules as $triggerKey => $conditions) {
                    foreach ($conditions as $triggerValue => $patterns) {
                        foreach ($configKeys as $ck) {
                            foreach ($patterns as $pattern) {
                                if (fnmatch($pattern, $ck)) {
                                    $dependencies[$triggerKey][$triggerValue] = $patterns;
                                    break;
                                }
                            }
                        }
                    }
                }

                $isTrigger = $configKeys->contains(fn (string $configKey) => \array_key_exists($configKey, $rules));

                return new EnvVar(
                    $configKeys,
                    $firstOccurrence['defaultValue'],
                    $dependencies,
                    $firstOccurrence['file'],
                    $firstOccurrence['file'], // Group by file
                    $isTrigger,
                    $envKey,
                );
            })->sortBy('key')->values();
    }

    /**
     * @return Collection<int, EnvVar>
     */
    public function all(): Collection
    {
        return $this->vars;
    }

    public function find(string $configKey): ?EnvVar
    {
        return $this->vars->firstWhere(fn ($var) => $var->configKeys->contains($configKey));
    }

    /** @return Collection<int, string> */
    public function groups(): Collection
    {
        return $this->vars->pluck('group')->unique();
    }

    /**
     * @return array<string, array<string, array<int, string>>>
     */
    private function getAllRules(): array
    {
        return [
            'cache.default' => [
                'array' => ['cache.stores.array.*'],
                'database' => ['cache.stores.database.*'],
                'file' => ['cache.stores.file.*'],
                'memcached' => ['cache.stores.memcached.*'],
                'redis' => ['cache.stores.redis.*'],
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
