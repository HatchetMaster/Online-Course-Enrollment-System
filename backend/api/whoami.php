<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';
header('Content-Type: application/json; charset=utf-8');

$user = bootstrap_get('current_user');
if (!$user) {
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'id' => $user['id'],
    'username' => $user['username'] ?? null
]);

echo json_encode([
    'session_id' => session_id(),
    'cookies' => $_COOKIE,
    'session_csrf' => $_SESSION['csrf_token'] ?? null,
    'server_time' => gmdate('c'),
], JSON_PRETTY_PRINT);