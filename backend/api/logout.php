<?php
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

$handler = bootstrap_get('logout_handler');
if (! $handler instanceof LogoutHandler) {
    http_response_code(500);
    echo json_encode(['error' => 'internal_error']);
    exit;
}

$result = $handler->handle();
http_response_code($result['status_code'] ?? 500);
echo json_encode($result);
