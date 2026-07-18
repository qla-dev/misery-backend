<?php

declare(strict_types=1);

use App\Services\GameCleanupService;
use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));
require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

$result = $app->make(GameCleanupService::class)->cleanup();
echo json_encode(['status' => 'ok'] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
