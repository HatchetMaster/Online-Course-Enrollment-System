<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/api_helpers.php';


$configPath = __DIR__ . '/../lib/config.php';
$configExample = __DIR__ . '/../lib/config.example.php';
if (file_exists($configPath)) {
    $CONFIG = include $configPath;
} elseif (file_exists($configExample)) {
    $CONFIG = include $configExample;
} else {
    $CONFIG = [];
}

if (isset($GLOBAL_OCES_CONFIG) && is_array($GLOBAL_OCES_CONFIG)) {
    $CONFIG = array_merge($CONFIG, $GLOBAL_OCES_CONFIG);
}

$DEMO_MODE = !empty($CONFIG['DEMO_MODE']);

$cookieSecure = !empty($CONFIG['FORCE_HTTPS']) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'secure' => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

error_reporting(E_ALL);
ini_set('session.use_only_cookies', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (php_sapi_name() !== 'cli') {
    $forceHttps = !empty($CONFIG['FORCE_HTTPS']) && ($_SERVER['HTTP_HOST'] !== 'localhost');
    if ($forceHttps && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
        $location = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $location, true, 301);
        exit;
    }
    if ($forceHttps) {
        header('Strict-Transport-Security: max-age=15768000; includeSubDomains; preload');
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$logFile = __DIR__ . '/../logs/app-errors.log';
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0750, true);
}

set_exception_handler(function ($e) use ($logFile, $DEMO_MODE) {
    $logLine = sprintf("[%s] %s in %s on line %d\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine());
    error_log($logLine, 3, $logFile);

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    $body = ['error' => 'internal_error'];
    if ($DEMO_MODE) {
        $body['message'] = $e->getMessage();
    }
    echo json_encode($body);
    exit;
});

if (!defined('DB_HOST'))
    define('DB_HOST', $CONFIG['DB_HOST'] ?? '127.0.0.1');
if (!defined('DB_PORT'))
    define('DB_PORT', intval($CONFIG['DB_PORT'] ?? 3306));
if (!defined('DB_USER'))
    define('DB_USER', $CONFIG['DB_USER'] ?? 'root');
if (!defined('DB_PASSWORD'))
    define('DB_PASSWORD', $CONFIG['DB_PASS'] ?? '');
if (!defined('DB_NAME'))
    define('DB_NAME', $CONFIG['DB_NAME'] ?? 'oces');

$cryptoPath = __DIR__ . '/crypto.php';
if (file_exists($cryptoPath)) {
    require_once $cryptoPath;
} else {
    throw new RuntimeException('Missing crypto helpers: ' . $cryptoPath);
}

$loggingPath = __DIR__ . '/logging.php';
if (file_exists($loggingPath)) {
    require_once $loggingPath;
}

$handlers = [
    __DIR__ . '/registrationHandler.php',
    __DIR__ . '/loginHandler.php',
    __DIR__ . '/logoutHandler.php'
];
foreach ($handlers as $h) {
    if (file_exists($h))
        require_once $h;
}

$BOOT = [];
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $BOOT['db'] = new PDO($dsn, DB_USER, DB_PASSWORD, $pdoOptions);
} catch (Throwable $e) {
    throw $e;
}

$BOOT['config'] = $CONFIG;

if (class_exists('RegistrationHandler')) {
    $BOOT['registration_handler'] = new RegistrationHandler($BOOT['db'], $BOOT['config'] ?? []);
}
if (class_exists('LoginHandler')) {
    $BOOT['login_handler'] = new LoginHandler($BOOT['db'], $BOOT['config'] ?? []);
}
if (class_exists('LogoutHandler')) {
    $BOOT['logout_handler'] = new LogoutHandler($BOOT['db'], $BOOT['config'] ?? []);
}

$BOOT['current_user'] = null;
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $BOOT['db']->prepare('SELECT id, username_enc, email_hash FROM tblStudents WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $username = null;
            if (!empty($row['username_enc'])) {
                try {
                    $username = decrypt_blob($row['username_enc']);
                } catch (Throwable $e) {
                    $username = null;
                }
            }
            $BOOT['current_user'] = [
                'id' => $row['id'],
                'username' => $username,
                'email_hash' => $row['email_hash'] ?? null
            ];
        }
    } catch (Throwable $e) {
        $BOOT['current_user'] = null;
    }
}

function bootstrap_get(string $name)
{
    global $BOOT;
    return $BOOT[$name] ?? null;
}