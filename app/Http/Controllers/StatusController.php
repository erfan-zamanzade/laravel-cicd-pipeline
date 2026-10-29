<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StatusController extends Controller
{
    /**
     * Build and runtime details, so a deployment can be traced to its commit.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'version' => config('app.version'),
            'commit' => config('app.commit'),
            'environment' => app()->environment(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ]);
    }

    /**
     * Liveness: the process is up and serving PHP. Never touches dependencies,
     * so an outage elsewhere does not get this container restarted.
     */
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Readiness: the dependencies this instance needs are reachable.
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                $key = 'readiness:'.Str::random(8);
                Cache::put($key, 'ok', 5);
                throw_unless(Cache::pull($key) === 'ok', 'RuntimeException', 'Cache round trip failed.');
            }),
        ];

        $ready = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $ready ? 'ok' : 'unavailable',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    /**
     * @return array{status: string, latency_ms: float, error?: string}
     */
    private function check(callable $probe): array
    {
        $start = hrtime(true);

        try {
            $probe();
            $result = ['status' => 'ok'];
        } catch (Throwable $e) {
            report($e);
            $result = [
                'status' => 'failed',
                'error' => config('app.debug') ? $e->getMessage() : 'unavailable',
            ];
        }

        return $result + ['latency_ms' => round((hrtime(true) - $start) / 1e6, 2)];
    }
}
