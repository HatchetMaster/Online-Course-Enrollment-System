<?php
// tests/bootstrap_integration.php
declare(strict_types=1);

/*
 * Integration test bootstrap
 * - Sets up test tmp dirs and environment variables used by integration tests.
 * - Does not include app code — integration tests will talk over HTTP to a running server.
 */

if (!defined('OCES_TESTING')) define('OCES_TESTING', true);

// Make tests write to deterministic test log dir
$testLogs = realpath(__DIR__ . '/tmp_logs') ?: (__DIR__ . '/tmp_logs');
if (!is_dir($testLogs)) @mkdir($testLogs, 0755, true);
putenv('OCES_LOG_DIR=' . $testLogs);

// Create tmp_data and cookie storage
$tmpdata = realpath(__DIR__ . '/tmp_data') ?: (__DIR__ . '/tmp_data');
if (!is_dir($tmpdata)) @mkdir($tmpdata, 0755, true);

// Nothing else to include here — integration tests will use HTTP calls.
// Ensure OCES_BASE_URL is provided when running integration tests.
// Example: $env:OCES_BASE_URL = 'http://localhost/OCES'
