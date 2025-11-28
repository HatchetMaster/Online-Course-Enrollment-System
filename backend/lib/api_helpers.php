<?php
// backend/lib/api_helpers.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/error_handler.php';

if (!function_exists('oces_config')) {
    function oces_config(): array
    {
        if (function_exists('config_loader')) {
            return config_loader();
        }
        $cfg = [];
        $local = __DIR__ . '/config.php';
        if (file_exists($local)) {
            $maybe = include $local;
            if (is_array($maybe))
                $cfg = $maybe;
        }
        $cfg['LOG_DIR'] = getenv('OCES_LOG_DIR') ?: ($cfg['LOG_DIR'] ?? __DIR__ . '/../../logs');
        return $cfg;
    }
}

if (!function_exists('respond_success')) {
    function respond_success(array $payload = [], int $status = 200): void
    {
        if (session_status() === PHP_SESSION_NONE)
            @session_start();
        $resp = ['success' => true, 'data' => $payload, 'request_id' => oces_get_request_id()];
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit;
    }
}

if (!function_exists('respond_error_payload')) {
    function respond_error_payload(int $status, string $code, string $message, array $details = []): void
    {
        oces_simple_log('warning', $message, ['error_code' => $code, 'details' => $details]);
        $resp = [
            'success' => false,
            'error' => ['code' => $code, 'message' => $message, 'details' => $details],
            'request_id' => oces_get_request_id()
        ];
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit;
    }
}

if (!function_exists('require_auth')) {
    function require_auth(): array
    {
        if (function_exists('bootstrap_get')) {
            $u = bootstrap_get('current_user');
            if (!empty($u))
                return $u;
        }
        if (!empty($_SESSION['user_id'])) {
            return ['id' => (int) $_SESSION['user_id']];
        }
        respond_error_payload(401, 'authentication_required', 'authentication required');
    }
}

if (!function_exists('check_csrf_from_payload')) {
    function check_csrf_from_payload(array $payload): void
    {
        $token = $payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $session = $_SESSION['csrf_token'] ?? null;
        if (empty($token) || empty($session) || !hash_equals($session, (string) $token)) {
            respond_error_payload(403, 'invalid_csrf', 'invalid csrf token');
        }
    }
}

/* Public wrappers accepting variable args for backward-compatibility */
if (!function_exists('oces_log_dispatch')) {
    function oces_log_dispatch(string $level, ...$args): void
    {
        $argc = count($args);
        if ($argc === 0)
            return;
        $msg = (string) $args[0];
        $ctx = [];

        if ($argc === 1) {
            $msg = (string) $args[0];
            $ctx = [];
        } elseif ($argc === 2) {
            if (is_array($args[1])) {
                $msg = (string) $args[0];
                $ctx = $args[1];
            } else {
                $component = (string) $args[0];
                $message = (string) $args[1];
                $msg = $message;
                $ctx = ['component' => $component];
            }
        } else {
            $component = (string) $args[0];
            $message = (string) $args[1];
            $meta = is_array($args[2]) ? $args[2] : [];
            $msg = $message;
            $ctx = array_merge(['component' => $component], $meta);
        }

        oces_simple_log($level, $msg, $ctx);
    }
}

if (!function_exists('log_info')) {
    function log_info(...$args): void
    {
        oces_log_dispatch('info', ...$args);
    }
}
if (!function_exists('log_warn')) {
    function log_warn(...$args): void
    {
        oces_log_dispatch('warning', ...$args);
    }
}
if (!function_exists('log_error')) {
    function log_error(...$args): void
    {
        oces_log_dispatch('error', ...$args);
    }
}

// ---- end paste block ----
