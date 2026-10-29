<?php

namespace App\Support\Metrics;

interface MetricsStore
{
    /**
     * Add a value to the series identified by the metric name and labels.
     *
     * @param  array<string, string>  $labels
     */
    public function increment(string $metric, array $labels, float $by = 1): void;

    /**
     * Every recorded series of a metric, keyed by its JSON-encoded labels.
     *
     * @return array<string, float>
     */
    public function series(string $metric): array;
}
