<?php

return [
    'max_players' => 5,
    'ios_app_team_id' => env('IOS_APP_TEAM_ID', ''),
    'android_app_sha256_cert_fingerprints' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ANDROID_APP_SHA256_CERT_FINGERPRINTS', ''))
    ))),
    // Used only while a game is in progress. Lobby polling remains fixed client-side.
    'ingame_polling_interval_ms' => max(250, (int) env('INGAME_POLLING_INTERVAL_MS', 3000)),
];
