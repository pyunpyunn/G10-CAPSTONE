<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_identifies_the_backend_api(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('type', 'api')
            ->assertJsonPath('status', 'ok');
    }
}

