<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/api/init.php';

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;
if (!$username || !$password) {
    echo "Usage: php test-login-cli.php <username> <password>\n";
    exit(1);
}

$handler = bootstrap_get('login_handler');
if (!($handler instanceof LoginHandler)) {
    echo "LoginHandler not present\n";
    exit(1);
}

$res = $handler->handle($username, $password);

echo "=== Raw Result ===\n";
echo json_encode($res, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Diagnostics ===\n";
echo "Status Code: " . ($res['status_code'] ?? 'n/a') . "\n";
echo "Success:     " . (!empty($res['success']) ? 'true' : 'false') . "\n";
echo "Need 2FA:    " . (!empty($res['need2fa']) ? 'true' : 'false') . "\n";
echo "User ID:     " . ($res['user_id'] ?? 'null') . "\n";
echo "Message:     " . ($res['message'] ?? 'n/a') . "\n";

if (!empty($res['success']) && empty($res['need2fa'])) {
    $_SESSION['user_id'] = $res['user_id'];
    $_SESSION['_flash']['success'] = 'Login successful (CLI test).';
    echo "\nSession user_id set to {$_SESSION['user_id']}\n";
    echo "Flash message set: " . $_SESSION['_flash']['success'] . "\n";
} elseif (!empty($res['need2fa'])) {
    $_SESSION['_flash']['info'] = '2FA required (CLI test).';
    echo "\nFlash message set: " . $_SESSION['_flash']['info'] . "\n";
} else {
    $_SESSION['_flash']['error'] = $res['message'] ?? 'Invalid credentials (CLI test).';
    echo "\nFlash message set: " . $_SESSION['_flash']['error'] . "\n";
}
