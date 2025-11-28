<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/lib/api_helpers.php';

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

http_response_code(200);
echo json_encode($res);
exit;
