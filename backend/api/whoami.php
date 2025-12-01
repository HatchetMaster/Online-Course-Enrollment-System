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
    // Return info about current user if logged in, else success:false
    $current = bootstrap_get('current_user') ?? null;
    if (!empty($current) && !empty($current['id'])) {
        $username = null;
        if (!empty($current['username']))
            $username = $current['username'];
        respond_success(['id' => $current['id'], 'username' => $username], 200);
    } else {
        // not logged in
        respond_error_payload(401, 'authentication_required', 'authentication required');
    }
} catch (Throwable $e) {
    oces_simple_log('error', 'whoami_exception', ['exception' => $e->getMessage()]);
    respond_error_payload(500, 'internal_error', 'internal server error');
}

http_response_code(200);
