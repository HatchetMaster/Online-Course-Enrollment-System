<?php
declare(strict_types=1);

/*
 * Unit test bootstrap
 * - Minimal overrides so tests can include backend/lib files safely.
 * - Uses tests/tmp_logs for logging and uses an in-memory or sqlite test DB per-test.
 */

if (!defined('OCES_TESTING'))
    define('OCES_TESTING', true);

// Test-friendly response helpers (no echo/exit)
if (!function_exists('respond_error_payload')) {
    function respond_error_payload(int $status, string $code, string $message, array $details = []): void
    {
        throw new RuntimeException(json_encode([
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'details' => $details
        ]));
    }
}
if (!function_exists('respond_success')) {
    function respond_success(array $payload = [], int $status = 200): void
    {
        return;
    }
}

// Ensure exceptions are re-thrown to PHPUnit
set_exception_handler(function ($e) {
    throw $e; });

// Make tests write to deterministic test log dir
$testLogs = realpath(__DIR__ . '/tmp_logs') ?: (__DIR__ . '/tmp_logs');
if (!is_dir($testLogs))
    @mkdir($testLogs, 0755, true);
putenv('OCES_LOG_DIR=' . $testLogs);

// Keep includes deterministic (point to backend/lib)
$PROJECT_ROOT = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
$LIB = $PROJECT_ROOT . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'lib';

// include minimal lib pieces used by unit tests
$required = [
    $LIB . DIRECTORY_SEPARATOR . 'simple_logger.php',
    $LIB . DIRECTORY_SEPARATOR . 'error_handler.php',
    $LIB . DIRECTORY_SEPARATOR . 'api_helpers.php',
];
foreach ($required as $f) {
    if (!file_exists($f)) {
        fwrite(STDERR, "Bootstrap error: required file missing: $f\n");
        exit(1);
    }
    require_once $f;
}

// define a test log file constant for quick reference in tests
if (!defined('OCES_TEST_LOG_FILE')) {
    define('OCES_TEST_LOG_FILE', rtrim(getenv('OCES_LOG_DIR') ?: ($PROJECT_ROOT . '/backend/logs'), '/\\') . '/app.log');
}
