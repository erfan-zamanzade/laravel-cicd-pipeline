<?php

namespace Tests\Feature;

use Tests\TestCase;

class StatusEndpointsTest extends TestCase
{
    public function test_info_reports_build_metadata(): void
    {
        config(['app.version' => '1.2.3', 'app.commit' => 'abc1234']);

        $this->getJson('/')
            ->assertOk()
            ->assertJson(['version' => '1.2.3', 'commit' => 'abc1234'])
            ->assertJsonStructure(['name', 'environment', 'php', 'laravel']);
    }

    public function test_health_does_not_depend_on_the_database(): void
    {
        $this->breakDatabase();

        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_ready_when_dependencies_are_reachable(): void
    {
        $this->getJson('/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok');
    }

    public function test_not_ready_when_the_database_is_down(): void
    {
        $this->breakDatabase();

        $this->getJson('/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('checks.database.status', 'failed')
            ->assertJsonPath('checks.cache.status', 'ok');
    }

    public function test_errors_hide_details_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        $this->breakDatabase();

        $this->getJson('/ready')->assertJsonPath('checks.database.error', 'unavailable');
    }

    public function test_unknown_routes_return_json(): void
    {
        $this->getJson('/does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
    }

    private function breakDatabase(): void
    {
        config([
            'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/database.sqlite'],
            'database.default' => 'broken',
        ]);
    }
}
