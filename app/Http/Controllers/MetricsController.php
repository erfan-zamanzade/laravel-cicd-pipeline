<?php

namespace App\Http\Controllers;

use App\Support\Metrics\Labels;
use App\Support\Metrics\MetricsStore;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    /**
     * Prometheus text exposition of the request metrics.
     */
    public function __invoke(MetricsStore $metrics): Response
    {
        $info = Labels::render([
            'version' => (string) config('app.version'),
            'commit' => (string) config('app.commit'),
        ]);

        $lines = [
            '# HELP app_info Build information of the running application.',
            '# TYPE app_info gauge',
            "app_info{$info} 1",
            '# HELP http_requests_total Total HTTP requests by method, route and status.',
            '# TYPE http_requests_total counter',
            ...$this->samples('http_requests_total', $metrics->series('http_requests_total')),
            '# HELP http_request_duration_seconds_sum Total time spent serving requests.',
            '# TYPE http_request_duration_seconds_sum counter',
            ...$this->samples('http_request_duration_seconds_sum', $metrics->series('http_request_duration_seconds_sum')),
        ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /**
     * @param  array<string, float>  $series
     * @return list<string>
     */
    private function samples(string $metric, array $series): array
    {
        ksort($series);

        $samples = [];
        foreach ($series as $labels => $value) {
            $samples[] = $metric.Labels::render(Labels::decode($labels)).' '.$value;
        }

        return $samples;
    }
}
