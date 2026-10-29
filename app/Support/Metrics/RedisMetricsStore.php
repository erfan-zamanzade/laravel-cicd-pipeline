<?php

namespace App\Support\Metrics;

use Illuminate\Contracts\Redis\Factory;
use Illuminate\Redis\Connections\Connection;

class RedisMetricsStore implements MetricsStore
{
    /**
     * The connection is resolved on first use, not here: constructing the
     * store must never touch the network, or an unreachable Redis would fail
     * every request (including /health) before the middleware can catch it.
     */
    public function __construct(
        private readonly Factory $redis,
        private readonly ?string $connection,
        private readonly string $prefix,
    ) {}

    public function increment(string $metric, array $labels, float $by = 1): void
    {
        $this->connection()->command('hincrbyfloat', [$this->prefix.$metric, Labels::encode($labels), $by]);
    }

    public function series(string $metric): array
    {
        /** @var array<string, string>|false $values */
        $values = $this->connection()->command('hgetall', [$this->prefix.$metric]);

        return array_map('floatval', $values ?: []);
    }

    private function connection(): Connection
    {
        /** @var Connection */
        return $this->redis->connection($this->connection);
    }
}
