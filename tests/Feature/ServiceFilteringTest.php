<?php

declare(strict_types=1);

namespace Tests\Feature;

use EnvForm\FormValue;
use EnvForm\Registry;
use EnvForm\ShouldAsk;
use Tests\TestCase;

class ServiceFilteringTest extends TestCase
{
    private ShouldAsk\Service $shouldAsk;

    private FormValue\Service $formValue;

    private Registry\Service $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $fixturePath = (string) realpath(__DIR__.'/../Fixture');

        // Copy fixture configs to the temporary app's config path
        if (! \Illuminate\Support\Facades\File::isDirectory(config_path())) {
            \Illuminate\Support\Facades\File::makeDirectory(config_path(), 0755, true);
        }
        \Illuminate\Support\Facades\File::copyDirectory($fixturePath.'/config', config_path());

        if ($this->app === null) {
            return;
        }

        // Force a rescan by re-instantiating Registry Service
        $this->app->singleton(Registry\Service::class, function ($app) {
            return new Registry\Service(new Registry\Repository);
        });

        $this->formValue = $this->app->make(FormValue\Service::class);
        $this->shouldAsk = $this->app->make(ShouldAsk\Service::class);
        $this->registry = $this->app->make(Registry\Service::class);
    }

    public function test_it_hides_redis_keys_when_not_active(): void
    {
        // Default drivers in fixtures (if any) or null
        // Let's assume sync queue and file cache
        $this->formValue->set('QUEUE_CONNECTION', 'sync');
        $this->formValue->set('CACHE_STORE', 'file');
        $this->formValue->set('SESSION_DRIVER', 'file');

        $this->shouldAsk->refresh();

        $redisVar = $this->registry->all()->firstWhere('key', 'REDIS_HOST');
        $this->assertNotNull($redisVar);
        $this->assertFalse($this->shouldAsk->isVisible($redisVar), 'REDIS_HOST should be hidden when Redis is not active');
    }

    public function test_it_shows_redis_keys_when_active_via_queue(): void
    {
        $this->formValue->set('QUEUE_CONNECTION', 'redis');
        $this->formValue->set('CACHE_STORE', 'file');

        $this->shouldAsk->refresh();

        $redisVar = $this->registry->all()->firstWhere('key', 'REDIS_HOST');
        $this->assertNotNull($redisVar);
        $this->assertTrue($this->shouldAsk->isVisible($redisVar), 'REDIS_HOST should be visible when Redis is active via Queue');
    }

    public function test_it_shows_redis_keys_when_active_via_master_key(): void
    {
        $this->formValue->set('QUEUE_CONNECTION', 'sync');
        $this->formValue->set('CACHE_STORE', 'file');

        // Master key for redis is database.redis.default.host -> REDIS_HOST
        $this->formValue->set('REDIS_HOST', '127.0.0.1');

        $this->shouldAsk->refresh();

        $redisVar = $this->registry->all()->firstWhere('key', 'REDIS_HOST');
        $this->assertNotNull($redisVar);
        $this->assertTrue($this->shouldAsk->isVisible($redisVar), 'REDIS_HOST should be visible when Master Key is set');
    }

    public function test_it_shows_aws_keys_when_active_via_s3_filesystem(): void
    {
        $this->formValue->set('FILESYSTEM_DISK', 's3');

        $this->shouldAsk->refresh();

        $awsKey = $this->registry->all()->firstWhere('key', 'AWS_ACCESS_KEY_ID');
        $this->assertNotNull($awsKey);
        $this->assertTrue($this->shouldAsk->isVisible($awsKey), 'AWS keys should be visible when S3 is active');
    }

    public function test_it_hides_aws_keys_when_inactive(): void
    {
        $this->formValue->set('FILESYSTEM_DISK', 'local');
        $this->formValue->set('QUEUE_CONNECTION', 'sync');
        $this->formValue->set('MAIL_MAILER', 'smtp');

        $this->shouldAsk->refresh();

        $awsKey = $this->registry->all()->firstWhere('key', 'AWS_ACCESS_KEY_ID');
        $this->assertNotNull($awsKey);
        $this->assertFalse($this->shouldAsk->isVisible($awsKey), 'AWS keys should be hidden when no AWS service is active');
    }
}
