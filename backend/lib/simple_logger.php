<?php
// backend/lib/simple_logger.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function oces_get_request_id(): string
{
    if (!empty($_SESSION['oces_request_id']))
        return (string) $_SESSION['oces_request_id'];
    $rid = bin2hex(random_bytes(12));
    $_SESSION['oces_request_id'] = $rid;
    return $rid;
}

function oces_redact_sensitive(array $ctx): array
{
    $redact = ['password', 'pwd', 'pass', 'token', 'csrf_token', 'authorization', 'totp_secret', 'credit_card', 'ccnum', 'ssn'];
    array_walk_recursive($ctx, function (&$v, $k) use ($redact) {
        if (in_array(strtolower($k), $redact, true))
            $v = 'REDACTED';
    });
    return $ctx;
}

function oces_simple_log(string $level, string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../../logs';
    if (function_exists('oces_config')) {
        $cfg = oces_config();
        if (!empty($cfg['LOG_DIR']))
            $logDir = $cfg['LOG_DIR'];
    }
    if (!is_dir($logDir))
        @mkdir($logDir, 0755, true);

    $entry = [
        'ts' => gmdate('c'),
        'level' => $level,
        'request_id' => oces_get_request_id(),
        'path' => $_SERVER['REQUEST_URI'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'user_id' => $_SESSION['user_id'] ?? null,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'msg' => $message,
        'context' => oces_redact_sensitive($context),
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logDir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

function oces_register_simple_handlers(): void
{
    set_exception_handler(function (Throwable $ex) {
        oces_simple_log('error', $ex->getMessage(), [
            'exception' => get_class($ex),
            'file' => $ex->getFile(),
            'line' => $ex->getLine(),
            'trace' => $ex->getTraceAsString(),
        ]);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => ['code' => 'internal_error', 'message' => 'internal server error'],
            'request_id' => oces_get_request_id()
        ]);
        exit;
    });

    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err !== null && ($err['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
            oces_simple_log('critical', 'fatal error', $err);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'internal_error', 'message' => 'internal server error'],
                'request_id' => oces_get_request_id()
            ]);
            exit;
        }
    });
}
