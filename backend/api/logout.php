<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Optional CSRF validation: enable by setting 'REQUIRE_LOGOUT_CSRF' => true in your config
$config = bootstrap_get('config') ?? [];
$requireCsrf = !empty($config['REQUIRE_LOGOUT_CSRF']);

if ($requireCsrf) {
    // Read JSON payload if present; check_csrf_from_payload will also accept X-CSRF-Token header
    $raw = file_get_contents('php://input');
    $payload = json_decode((string) $raw, true) ?: [];
    check_csrf_from_payload($payload); // will respond_error_payload(403, ...) and exit on failure
}

$handler = bootstrap_get('logout_handler');
if (!$handler instanceof LogoutHandler) {
    http_response_code(500);
    echo json_encode(['error' => 'internal_error']);
    exit;
}

$result = $handler->handle();
http_response_code($result['status_code'] ?? 500);
echo json_encode($result);