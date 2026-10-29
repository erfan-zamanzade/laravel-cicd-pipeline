<?php

namespace Tests\Feature;

use App\Support\Metrics\MetricsStore;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('metrics.store') === 'redis') {
            Redis::connection()->command('flushdb');
        }
    }

    public function test_requests_are_counted_by_route_pattern(): void
    {
        $this->get('/health');
        $this->get('/health');
        $this->get('/does-not-exist');

        $this->get('/metrics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            ->assertSee('http_requests_total{method="GET",route="health",status="200"} 2', false)
            ->assertSee('http_requests_total{method="GET",route="unmatched",status="404"} 1', false)
            ->assertSee('http_request_duration_seconds_sum{method="GET",route="health",status="200"}', false);
    }

    public function test_build_info_is_exposed(): void
    {
        config(['app.version' => '1.2.3', 'app.commit' => 'abc1234']);

        $this->get('/metrics')->assertSee('app_info{version="1.2.3",commit="abc1234"} 1', false);
    }

    public function test_a_broken_metrics_store_does_not_fail_requests(): void
    {
        $this->app->instance(MetricsStore::class, new class implements MetricsStore
        {
            public function increment(string $metric, array $labels, float $by = 1): void
            {
                throw new \RuntimeException('metrics backend down');
            }

            public function series(string $metric): array
            {
                return [];
            }
        });

        $this->get('/health')->assertOk();
    }
}
