<?php

namespace App\Support\Metrics;

class ArrayMetricsStore implements MetricsStore
{
    /**
     * @var array<string, array<string, float>>
     */
    private array $series = [];

    public function increment(string $metric, array $labels, float $by = 1): void
    {
        $key = Labels::encode($labels);

        $this->series[$metric][$key] = ($this->series[$metric][$key] ?? 0) + $by;
    }

    public function series(string $metric): array
    {
        return $this->series[$metric] ?? [];
    }
}
