<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_boots_and_renders_the_landing_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Tong POS Platform');
    }

    public function test_the_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/health');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'Tong POS',
            ]);
    }
}
