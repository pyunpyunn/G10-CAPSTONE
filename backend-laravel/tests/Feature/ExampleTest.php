<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_reports_a_missing_frontend_build(): void
    {
        $response = $this->get('/');

        $response->assertStatus(503)
            ->assertSee('React web build not found.');
    }
}
