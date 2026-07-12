<?php

return [
    // Used only while a game is in progress. Lobby polling remains fixed client-side.
    'ingame_polling_interval_ms' => max(250, (int) env('INGAME_POLLING_INTERVAL_MS', 3000)),
];
