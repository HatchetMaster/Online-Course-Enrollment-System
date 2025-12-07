<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$user = bootstrap_get('current_user');

if (!$user) {
    // Not authenticated
    http_response_code(200);
    echo json_encode(['authenticated' => false]);
    exit;
}

// Authenticated -- return minimal public info only
$res = [
    'authenticated' => true,
    'id' => $user['id'],
    'username' => $user['username'] ?? null,
];

// include csrf_token for convenience
if (!empty($_SESSION['csrf_token'])) {
    $res['csrf_token'] = $_SESSION['csrf_token'];
}

try {
    $current = bootstrap_get('current_user') ?? null;
    if (empty($current) || empty($current['id'])) {
        // Not authenticated -> return 401 with standard error payload
        respond_error_payload(401, 'authentication_required', 'authentication required');
    }

    $payload = [
        'id' => $current['id'],
        'username' => $current['username'] ?? null
    ];

    if (!empty($_SESSION['csrf_token'])) {
        $payload['csrf_token'] = $_SESSION['csrf_token'];
    }

    respond_success($payload, 200);
} catch (Throwable $e) {
    oces_simple_log('error', 'whoami_exception', ['exception' => (string) $e]);
    respond_error_payload(500, 'internal_error', 'internal server error');
}
