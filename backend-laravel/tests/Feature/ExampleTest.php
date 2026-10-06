<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_serves_the_frontend_or_reports_a_missing_build(): void
    {
        $response = $this->get('/');

        if (file_exists(public_path('frontend-web/index.html'))) {
            $response->assertOk();

            return;
        }

        $response->assertStatus(503)
            ->assertSee('React web build not found.');
    }
}


