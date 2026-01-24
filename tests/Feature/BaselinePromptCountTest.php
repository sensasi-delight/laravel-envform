<?php

declare(strict_types=1);

namespace Tests\Feature;

use EnvForm\FormValue;
use EnvForm\Registry;
use EnvForm\ShouldAsk;
use Tests\TestCase;

class BaselinePromptCountTest extends TestCase
{
    private ShouldAsk\Service $shouldAsk;

    private FormValue\Service $formValue;

    private Registry\Service $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $fixturePath = (string) realpath(__DIR__.'/../Fixture');
        if (! \Illuminate\Support\Facades\File::isDirectory(config_path())) {
            \Illuminate\Support\Facades\File::makeDirectory(config_path(), 0755, true);
        }
        \Illuminate\Support\Facades\File::copyDirectory($fixturePath.'/config', config_path());

        if ($this->app === null) {
            return;
        }

        $this->app->singleton(Registry\Service::class, function ($app) {
            return new Registry\Service(new Registry\Repository);
        });

        $this->formValue = $this->app->make(FormValue\Service::class);
        $this->shouldAsk = $this->app->make(ShouldAsk\Service::class);
        $this->registry = $this->app->make(Registry\Service::class);
    }

    public function test_it_reduces_prompts_on_default_baseline(): void
    {
        // Set default drivers
        $this->formValue->set('CACHE_STORE', 'file');
        $this->formValue->set('QUEUE_CONNECTION', 'sync');
        $this->formValue->set('SESSION_DRIVER', 'file');
        $this->formValue->set('MAIL_MAILER', 'smtp'); // Default standard

        $this->shouldAsk->refresh();

        $visibleCount = $this->shouldAsk->countVisible();

        // Count non-essential variables that should be hidden
        // Redis, AWS (S3/SQS/SES), Mailgun, Postmark
        $redisVars = $this->registry->all()->filter(fn ($v) => str_contains($v->key, 'REDIS_'));
        $awsVars = $this->registry->all()->filter(fn ($v) => str_contains($v->key, 'AWS_'));
        $mailgunVars = $this->registry->all()->filter(fn ($v) => str_contains($v->key, 'MAILGUN_'));
        $postmarkVars = $this->registry->all()->filter(fn ($v) => str_contains($v->key, 'POSTMARK_'));

        foreach ($redisVars as $v) {
            $this->assertFalse($this->shouldAsk->isVisible($v), "{$v->key} should be hidden");
        }
        foreach ($awsVars as $v) {
            $this->assertFalse($this->shouldAsk->isVisible($v), "{$v->key} should be hidden");
        }
        foreach ($mailgunVars as $v) {
            $this->assertFalse($this->shouldAsk->isVisible($v), "{$v->key} should be hidden");
        }
        foreach ($postmarkVars as $v) {
            $this->assertFalse($this->shouldAsk->isVisible($v), "{$v->key} should be hidden");
        }

        // Verify some essential ones are still visible if missing (e.g. APP_NAME, etc.)
        $appName = $this->registry->all()->firstWhere('key', 'APP_NAME');
        $this->assertNotNull($appName);

        $this->assertTrue($this->shouldAsk->isVisible($appName), 'APP_NAME should still be visible');
    }
}
