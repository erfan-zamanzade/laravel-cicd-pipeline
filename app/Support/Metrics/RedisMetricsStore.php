<?php

namespace App\Support\Metrics;

use Illuminate\Redis\Connections\Connection;

class RedisMetricsStore implements MetricsStore
{
    public function __construct(
        private readonly Connection $redis,
        private readonly string $prefix,
    ) {}

    public function increment(string $metric, array $labels, float $by = 1): void
    {
        $this->redis->command('hincrbyfloat', [$this->prefix.$metric, Labels::encode($labels), $by]);
    }

    public function series(string $metric): array
    {
        /** @var array<string, string>|false $values */
        $values = $this->redis->command('hgetall', [$this->prefix.$metric]);

        return array_map('floatval', $values ?: []);
    }
}
