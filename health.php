<?php
/**
 * Basic health endpoint for hosting probes.
 */
header('Content-Type: application/json');

$status = [
    'ok' => true,
    'service' => 'studify',
    'timestamp' => gmdate('c'),
];

try {
    require_once __DIR__ . '/config/db.php';
    $status['db'] = ($conn instanceof mysqli && $conn->ping()) ? 'ok' : 'down';
    if ($status['db'] !== 'ok') {
        $status['ok'] = false;
    }
} catch (Throwable $e) {
    $status['ok'] = false;
    $status['db'] = 'error';
}

http_response_code($status['ok'] ? 200 : 503);
echo json_encode($status);
