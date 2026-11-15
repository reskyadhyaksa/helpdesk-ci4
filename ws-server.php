<?php

/**
 * Server WebSocket notifikasi dalam aplikasi (Workerman). Jalankan sebagai proses panjang:
 *
 *   php ws-server.php start
 *
 * - Browser menyambung ke ws://HOST:WS_PORT/?token=... (token dari GET /notifications/token, berumur singkat).
 * - Aplikasi mendorong notifikasi lewat port internal 127.0.0.1:WS_INTERNAL_PORT (satu baris JSON per notifikasi).
 * Konfigurasi dari .env: WS_PORT, WS_INTERNAL_PORT, WS_SECRET. File ini mandiri (tanpa bootstrap CodeIgniter),
 * karena Workerman di Windows menjalankan ulang file ini untuk tiap worker.
 */

use App\Services\WsHub;
use App\Services\WsToken;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Timer;
use Workerman\Worker;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/Services/WsToken.php';
require __DIR__ . '/app/Services/WsHub.php';

// .env sederhana: KEY = value (tanda kutip opsional); nilai yang sudah ada di lingkungan tidak ditimpa
$envFile = __DIR__ . '/.env';

if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (! str_contains($line, '=') || str_starts_with(ltrim($line), '#')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value         = trim($value, "\"'");

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

$secret       = (string) getenv('WS_SECRET');
$port         = (int) (getenv('WS_PORT') ?: 8282);
$internalPort = (int) (getenv('WS_INTERNAL_PORT') ?: 2207);

if (strlen($secret) < 16) {
    fwrite(STDERR, "WS_SECRET belum diisi (minimal 16 karakter) di .env.\n");
    exit(1);
}

$tokens = new WsToken($secret);
$hub    = new WsHub();

/** @var array<int, TcpConnection> koneksi aktif menurut id, supaya koneksi yang digeser bisa ditutup */
$live = [];

$worker        = new Worker("websocket://0.0.0.0:{$port}");
$worker->name  = 'helpdesk-ws';
$worker->count = 1;

$worker->onWorkerStart = static function () use ($hub, $internalPort): void {
    // Port internal hanya di 127.0.0.1: aplikasi mengirim "{user_id, notification}" per baris
    $inner            = new Worker("text://127.0.0.1:{$internalPort}");
    $inner->onMessage = static function (TcpConnection $connection, string $line) use ($hub): void {
        $command = WsHub::parseInternalLine($line);

        if ($command !== null) {
            $hub->push($command['user_id'], $command['notification']);
        }

        $connection->close();
    };
    $inner->listen();

    Timer::add(30, static fn () => $hub->ping());
};

$worker->onWebSocketConnect = static function (TcpConnection $connection, Request $request) use ($tokens, $hub, &$live): void {
    $userId = $tokens->verify((string) $request->get('token', ''));

    if ($userId === null) {
        $connection->close();

        return;
    }

    $live[$connection->id] = $connection;

    $evicted = $hub->register((string) $connection->id, $userId, static function (string $data) use ($connection): void {
        $connection->send($data);
    });

    foreach ($evicted as $oldId) {
        if (isset($live[(int) $oldId])) {
            $live[(int) $oldId]->close();
            unset($live[(int) $oldId]);
        }
    }
};

$worker->onClose = static function (TcpConnection $connection) use ($hub, &$live): void {
    $hub->unregister((string) $connection->id);
    unset($live[$connection->id]);
};

Worker::runAll();
