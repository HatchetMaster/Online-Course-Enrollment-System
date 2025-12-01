<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';


log_info('registration_debug', 'Incoming', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    '_POST' => array_slice($_POST, 0, 20),           
    'session_csrf' => $_SESSION['csrf_token'] ?? null,
    'cookies' => $_COOKIE,
]);


$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$payload = [];
if (stripos($contentType, 'application/json') !== false) {
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true) ?? [];
} else {
    $payload = $_POST;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && stripos($contentType, 'application/json') === false) {
    $submitted = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($submitted) || !is_string($sessionToken) || !hash_equals($sessionToken, $submitted)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid form submission (CSRF)']);
        exit;
    }
}

$handler = bootstrap_get('registration_handler');
if (!($handler instanceof RegistrationHandler)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration handler missing']);
    exit;
}

$res = $handler->handle($payload);

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (stripos($accept, 'application/json') !== false || stripos($contentType, 'application/json') !== false) {
    http_response_code($res['status_code'] ?? 200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res);
    exit;
}

if (!empty($res['success'])) {
    $_SESSION['_flash'] = ['success' => $res['message'] ?? 'Registered'];
    header('Location: /OCES/frontend/site/login.html');
    exit;
} else {
    $_SESSION['_flash'] = ['error' => $res['message'] ?? 'Registration failed'];
    header('Location: /OCES/frontend/site/registration.html');
    exit;
}
