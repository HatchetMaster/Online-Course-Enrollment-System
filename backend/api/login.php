<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = [$type => $message];
}

error_log('login.php called, method=' . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    flash_set('error', 'Invalid request method.');
    header('Location: /OCES/frontend/site/login.html');
    exit;
}

$submitted = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submitted) || !is_string($sessionToken) || !hash_equals($sessionToken, $submitted)) {
    error_log('Branch: CSRF fail');
    http_response_code(400);
    flash_set('error', 'Invalid form submission (CSRF).');
    header('Location: /OCES/frontend/site/login.html');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
if ($username === '' || $password === '') {
    error_log('Branch: empty credentials');
    flash_set('error', 'Username and password required.');
    header('Location: /OCES/frontend/site/login.html');
    exit;
}

try {
    $loginHandler = bootstrap_get('login_handler');
    if ($loginHandler instanceof LoginHandler) {
        $res = $loginHandler->handle($username, $password);
        error_log('LoginHandler result: ' . json_encode($res));
        error_log('Branch: handler result ' . json_encode($res));

        if (!empty($res['success']) && empty($res['need2fa'])) {
            $_SESSION['user_id'] = $res['user_id'];
            flash_set('success', 'Login successful.');
            header('Location: /OCES/frontend/site/index.html');
            exit;
        }

        if (!empty($res['need2fa'])) {
            flash_set('info', '2FA required (not implemented in demo).');
            header('Location: /OCES/frontend/site/login.html');
            exit;
        }

        flash_set('error', $res['message'] ?? 'Invalid credentials.');
        header('Location: /OCES/frontend/site/login.html');
        exit;
    }

    $pdo = bootstrap_get('db');
    $uhash = hmac_lookup($username);
    $stmt = $pdo->prepare('SELECT id, password_hash FROM tblStudents WHERE username_hash = :uhash LIMIT 1');
    $stmt->execute([':uhash' => $uhash]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        error_log('Branch: fallback invalid');
        flash_set('error', 'Invalid credentials.');
        header('Location: /OCES/frontend/site/login.html');
        exit;
    }

    $_SESSION['user_id'] = (int) $row['id'];
    flash_set('success', 'Login successful.');
    header('Location: /OCES/frontend/site/index.html');
    exit;

} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    flash_set('error', 'Internal server error.');
    header('Location: /OCES/frontend/site/login.html');
    exit;
}
