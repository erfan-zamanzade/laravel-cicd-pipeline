<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Metrics Store
    |--------------------------------------------------------------------------
    |
    | Where request counters are kept between requests. "redis" shares them
    | across PHP-FPM workers and replicas; "array" keeps them in memory for the
    | current process only and is meant for tests.
    |
    | Supported: "redis", "array"
    |
    */

    'store' => env('METRICS_STORE', 'redis'),

    'redis_connection' => env('METRICS_REDIS_CONNECTION', 'default'),

    'prefix' => env('METRICS_PREFIX', 'metrics:'),

];
