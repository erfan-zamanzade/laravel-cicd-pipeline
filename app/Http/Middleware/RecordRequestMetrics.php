<?php

namespace App\Http\Middleware;

use App\Support\Metrics\MetricsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordRequestMetrics
{
    public function __construct(private readonly MetricsStore $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        /** @var Response $response */
        $response = $next($request);

        // Label by route pattern, not raw path, so the series count stays bounded.
        $labels = [
            'method' => $request->method(),
            'route' => $request->route()?->uri() ?? 'unmatched',
            'status' => (string) $response->getStatusCode(),
        ];

        try {
            $this->metrics->increment('http_requests_total', $labels);
            $this->metrics->increment('http_request_duration_seconds_sum', $labels, (hrtime(true) - $start) / 1e9);
        } catch (Throwable $e) {
            // Losing a metric must never fail the request it describes.
            report($e);
        }

        return $response;
    }
}
