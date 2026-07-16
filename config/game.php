<?php

return [
    'max_players' => 8,
    'ios_app_team_id' => env('IOS_APP_TEAM_ID', ''),
    'android_app_sha256_cert_fingerprints' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ANDROID_APP_SHA256_CERT_FINGERPRINTS', ''))
    ))),
    // Used only by rooms assigned to the polling transport.
    'ingame_polling_interval_ms' => max(250, (int) env('INGAME_POLLING_INTERVAL_MS', 3000)),
    'pusher_app_key' => env('PUSHER_APP_KEY', ''),
    'pusher_app_cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
    'pusher_heartbeat_interval_ms' => max(10_000, (int) env('PUSHER_HEARTBEAT_INTERVAL_MS', 20_000)),
    'pusher_connection_capacity' => max(1, (int) env('PUSHER_CONNECTION_CAPACITY', 100)),
    'ably_connection_capacity' => max(1, (int) env('ABLY_CONNECTION_CAPACITY', 200)),
    'realtime_primary_provider' => in_array(strtolower((string) env('REALTIME_PRIMARY_PROVIDER', 'pusher')), ['pusher', 'ably'], true)
        ? strtolower((string) env('REALTIME_PRIMARY_PROVIDER', 'pusher'))
        : 'pusher',
    'reverb_override' => filter_var(env('REVERB_OVERRIDE', false), FILTER_VALIDATE_BOOL),
    'reverb_app_key' => env('REVERB_APP_KEY', ''),
    'reverb_host' => env('REVERB_HOST', ''),
    'reverb_port' => (int) env('REVERB_PORT', 443),
    'reverb_scheme' => env('REVERB_SCHEME', 'https'),
    'provider_probe_timeout_ms' => max(500, (int) env('REALTIME_PROVIDER_PROBE_TIMEOUT_MS', 2500)),
    'member_inactivity_timeout_seconds' => max(15, (int) env('MEMBER_INACTIVITY_TIMEOUT_SECONDS', 60)),
    'host_lobby_inactivity_timeout_seconds' => max(30, (int) env('HOST_LOBBY_INACTIVITY_TIMEOUT_SECONDS', 120)),
    'started_game_move_timeout_seconds' => max(60, (int) env('STARTED_GAME_MOVE_TIMEOUT_SECONDS', 180)),
    'cleanup_username' => env('GAME_CLEANUP_USERNAME', env('CMS_USERNAME', 'admin')),
    'cleanup_password' => env('GAME_CLEANUP_PASSWORD', env('CMS_PASSWORD', '1234')),
];
