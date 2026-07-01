<?php

return [
    'error_tracking_dsn' => env('ERROR_TRACKING_DSN'),
    'health_endpoint' => env('MONITORING_HEALTH_ENDPOINT', '/health'),
];
