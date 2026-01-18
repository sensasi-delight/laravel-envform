<?php

declare(strict_types=1);

namespace Tests\Unit\FormValue;

use EnvForm\FormValue\Service;
use Tests\TestCase;

final class ServiceTest extends TestCase
{
    public function test_it_stores_and_retrieves_values(): void
    {
        $service = new Service;

        $this->assertNull($service->get('APP_NAME'));

        $service->set('APP_NAME', 'Laravel');
        $this->assertEquals('Laravel', $service->get('APP_NAME'));
    }

    public function test_it_tracks_dirty_state(): void
    {
        $service = new Service;

        $this->assertFalse($service->isDirty());

        $service->set('FOO', 'BAR');
        $this->assertTrue($service->isDirty());
    }
}
