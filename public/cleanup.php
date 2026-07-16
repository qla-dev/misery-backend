<?php

declare(strict_types=1);

use App\Services\GameCleanupService;
use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));
require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (PHP_SAPI !== 'cli') {
    $expectedUsername = (string) (config('game.cleanup_username') ?: config('cms.username'));
    $expectedPassword = (string) (config('game.cleanup_password') ?: config('cms.password'));
    $username = (string) ($_SERVER['PHP_AUTH_USER'] ?? '');
    $password = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');

    if ($expectedUsername === '' || $expectedPassword === ''
        || ! hash_equals($expectedUsername, $username)
        || ! hash_equals($expectedPassword, $password)) {
        header('WWW-Authenticate: Basic realm="Misery Index cleanup"');
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => 'Authentication required.']);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

$result = $app->make(GameCleanupService::class)->cleanup();
echo json_encode(['status' => 'ok'] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
