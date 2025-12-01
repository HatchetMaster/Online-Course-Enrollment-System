<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/api_helpers.php';

try {
    // ensure session is active (api_helpers normally starts session)
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        // create a 32-byte token (64 hex chars)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    respond_success(['csrf_token' => $_SESSION['csrf_token']], 200);
} catch (Throwable $e) {
    // Log for diagnostics but don't leak secrets to client
    if (function_exists('oces_simple_log')) {
        oces_simple_log('critical', 'csrf_exception', ['exception' => (string) $e]);
    }
    respond_error_payload(500, 'internal_error', 'internal server error');
}
