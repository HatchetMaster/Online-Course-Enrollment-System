
<?php
declare(strict_types=1);

/*
 * Centralized error handler bootstrap.
 * - Registers the project's simple logger and consistent PHP error/exception/shutdown handlers.
 * - Idempotent: safe to require() multiple times.
 */

if (defined('OCES_ERROR_HANDLERS_REGISTERED')) {
    return;
}
define('OCES_ERROR_HANDLERS_REGISTERED', true);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Ensure simple logger is available
require_once __DIR__ . '/simple_logger.php';

// Register the central handlers provided by simple_logger
if (!function_exists('oces_register_simple_handlers')) {
    // Fallback: minimal handlers if simple_logger wasn't loaded correctly
    set_exception_handler(function (Throwable $ex) {
        error_log(sprintf("[%s] %s in %s on line %d\n", date('c'), $ex->getMessage(), $ex->getFile(), $ex->getLine()), 3, __DIR__ . '/../logs/app-errors.log');
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'error' => ['code' => 'internal_error', 'message' => 'internal server error']]);
        exit;
    });

    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err !== null && ($err['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
            error_log(json_encode($err) . PHP_EOL, 3, __DIR__ . '/../logs/app-errors.log');
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'error' => ['code' => 'internal_error', 'message' => 'fatal error']]);
            exit;
        }
    });
} else {
    // Use the proper registration from simple_logger
    oces_register_simple_handlers();
}
