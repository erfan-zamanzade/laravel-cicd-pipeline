<?php

namespace App\Providers;

use App\Support\Metrics\ArrayMetricsStore;
use App\Support\Metrics\MetricsStore;
use App\Support\Metrics\RedisMetricsStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MetricsStore::class, fn () => match (config('metrics.store')) {
            'array' => new ArrayMetricsStore,
            default => new RedisMetricsStore(
                $this->app->make(RedisFactory::class),
                config('metrics.redis_connection'),
                config('metrics.prefix'),
            ),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
