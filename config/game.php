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
    'bot_turn_delay_min_ms' => max(0, (int) env('BOT_TURN_DELAY_MIN_MS', 650)),
    'bot_turn_delay_max_ms' => max(0, (int) env('BOT_TURN_DELAY_MAX_MS', 1600)),
    'auto_creation' => filter_var(env('AUTO_CREATION', false), FILTER_VALIDATE_BOOL),
    'minimum_public_room_listings' => max(0, (int) env('MINIMUM_PUBLIC_ROOM_LISTINGS', 10)),
    'synthetic_player_names' => [
        // Female names used across Bosnia and Herzegovina, Serbia, and Croatia.
        'Amina', 'Amra', 'Lejla', 'Emina', 'Hana', 'Sara', 'Lamija', 'Ajla', 'Nejra', 'Merjem',
        'Džejla', 'Asja', 'Iman', 'Naida', 'Selma', 'Elma', 'Azra', 'Belma', 'Irma', 'Adna',
        'Mia', 'Lucija', 'Ana', 'Petra', 'Ivana', 'Marija', 'Katarina', 'Nikolina', 'Martina', 'Antonija',
        'Iva', 'Lana', 'Ema', 'Dora', 'Nika', 'Klara', 'Laura', 'Elena', 'Karla', 'Tea',
        'Milica', 'Jovana', 'Anđela', 'Teodora', 'Aleksandra', 'Jelena', 'Tijana', 'Tamara', 'Nevena', 'Maša',
        'Sofija', 'Dunja', 'Mina', 'Isidora', 'Dragana', 'Bojana', 'Biljana', 'Gordana', 'Vesna', 'Zorana',
        'Sabina', 'Jasmina', 'Medina', 'Meliha', 'Senada', 'Šejla', 'Aldijana', 'Zerina', 'Dalila', 'Melisa',
        'Andrea', 'Gabriela', 'Kristina', 'Magdalena', 'Valentina', 'Viktorija', 'Barbara', 'Helena', 'Paula', 'Tena',
        'Danijela', 'Sanja', 'Maja', 'Nataša', 'Lidija', 'Mirjana', 'Snežana', 'Ljiljana', 'Branka', 'Olivera',
        'Una', 'Ena', 'Anja', 'Lorena', 'Korina', 'Cvita', 'Franka', 'Željka', 'Renata', 'Edita',

        // Male names used across Bosnia and Herzegovina, Serbia, and Croatia.
        'Amar', 'Adnan', 'Haris', 'Emir', 'Edin', 'Mirza', 'Tarik', 'Armin', 'Kenan', 'Faris',
        'Hamza', 'Ahmed', 'Ibrahim', 'Ismail', 'Jusuf', 'Davud', 'Benjamin', 'Kerim', 'Nermin', 'Samir',
        'Ivan', 'Luka', 'Marko', 'Josip', 'Stjepan', 'Petar', 'Ante', 'Nikola', 'Marin', 'Mateo',
        'Filip', 'Domagoj', 'Tomislav', 'Hrvoje', 'Krešimir', 'Zvonimir', 'Dario', 'Bruno', 'Karlo', 'Lovro',
        'Stefan', 'Miloš', 'Nemanja', 'Dušan', 'Lazar', 'Vuk', 'Uroš', 'Aleksa', 'Ognjen', 'Strahinja',
        'Mihajlo', 'Đorđe', 'Jovan', 'Dragan', 'Dejan', 'Goran', 'Zoran', 'Bojan', 'Milan', 'Slobodan',
        'Alen', 'Aldin', 'Anes', 'Enes', 'Ermin', 'Jasmin', 'Semir', 'Senad', 'Elvir', 'Mirsad',
        'Damir', 'Denis', 'Dino', 'Ervin', 'Zlatan', 'Vedran', 'Boris', 'Darko', 'Igor', 'Saša',
        'Aleksandar', 'Vladimir', 'Predrag', 'Nenad', 'Miroslav', 'Radovan', 'Rade', 'Željko', 'Branimir', 'Dalibor',
        'Jakov', 'Fran', 'Roko', 'Niko', 'Toma', 'Matija', 'Andrija', 'Ilija', 'Vedad', 'Bakir',
    ],
    'cleanup_username' => env('GAME_CLEANUP_USERNAME', env('CMS_USERNAME', 'admin')),
    'cleanup_password' => env('GAME_CLEANUP_PASSWORD', env('CMS_PASSWORD', '1234')),
];
