<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

function set_flash_and_redirect($type, $message, $location = '/OCES/frontend/site/login.html')
{
    $_SESSION['_flash'] = [$type => $message];
    header('Location: ' . $location);
    exit;
}

$submitted = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submitted) || !is_string($sessionToken) || !hash_equals($sessionToken, $submitted)) {
    set_flash_and_redirect('error', 'Invalid form submission (CSRF).');
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    set_flash_and_redirect('error', 'Username and password required.');
}

try {
    $loginHandler = bootstrap_get('login_handler');
    if ($loginHandler instanceof LoginHandler) {
        $res = $loginHandler->handle($username, $password);
        if (!empty($res['success']) && empty($res['need2fa'])) {
            $_SESSION['user_id'] = $res['user_id'];
            set_flash_and_redirect('success', 'Login successful.', '/OCES/frontend/site/index.html');
        } elseif (!empty($res['need2fa'])) {
            set_flash_and_redirect('info', '2FA required. Not implemented in this demo.');
        } else {
            set_flash_and_redirect('error', $res['message'] ?? 'Invalid credentials.');
        }
    } else {
        $pdo = bootstrap_get('db');
        $uhash = hmac_lookup($username);
        $stmt = $pdo->prepare('SELECT id, password_hash, failed_login_count, locked_until FROM tblStudents WHERE username_hash = :uhash LIMIT 1');
        $stmt->execute([':uhash' => $uhash]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            set_flash_and_redirect('error', 'Invalid credentials.');
        }
        $_SESSION['user_id'] = (int) $row['id'];
        set_flash_and_redirect('success', 'Login successful.', '/OCES/frontend/site/index.html');
    }
} catch (Throwable $e) {
    log_error('LoginForm', 'Error: ' . $e->getMessage());
    set_flash_and_redirect('error', 'Internal server error. Check logs.');
}