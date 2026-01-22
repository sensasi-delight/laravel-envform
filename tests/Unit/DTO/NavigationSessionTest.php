<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use EnvForm\DTO\EnvVar;
use EnvForm\DTO\NavigationSession;
use Tests\TestCase;

final class NavigationSessionTest extends TestCase
{
    public function test_it_tracks_current_step_and_navigation(): void
    {
        $steps = collect([
            new EnvVar(collect(['app.name']), 'Laravel', 'app.php', false, 'APP_NAME'),
            new EnvVar(collect(['app.env']), 'local', 'app.php', false, 'APP_ENV'),
        ]);

        $session = new NavigationSession($steps);

        $step1 = $session->currentStep();
        $this->assertNotNull($step1);
        $this->assertEquals('APP_NAME', $step1->key);
        $this->assertTrue($session->hasNext());
        $this->assertFalse($session->hasPrevious());

        $session->next();

        $step2 = $session->currentStep();
        $this->assertNotNull($step2);
        $this->assertEquals('APP_ENV', $step2->key);
        $this->assertFalse($session->hasNext());
        $this->assertTrue($session->hasPrevious());

        $session->previous();

        $step3 = $session->currentStep();
        $this->assertNotNull($step3);
        $this->assertEquals('APP_NAME', $step3->key);
    }

    public function test_it_handles_filtered_collections_with_reset_indices(): void
    {
        // Simulate a filtered collection where indices are not 0, 1
        $steps = collect([
            10 => new EnvVar(collect(['app.name']), 'Laravel', 'app.php', false, 'APP_NAME'),
            25 => new EnvVar(collect(['app.env']), 'local', 'app.php', false, 'APP_ENV'),
        ])->values(); // This is what Service.php now does

        $session = new NavigationSession($steps);

        $step1 = $session->currentStep();
        $this->assertNotNull($step1);
        $this->assertEquals('APP_NAME', $step1->key);

        $session->next();

        $step2 = $session->currentStep();
        $this->assertNotNull($step2);
        $this->assertEquals('APP_ENV', $step2->key);
    }
}
