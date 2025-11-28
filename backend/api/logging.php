<?php
declare(strict_types=1);

/*
 * Legacy logging compatibility wrapper.
 * Forwards to centralized api_helpers/simple_logger if available.
 * Only defines functions if they don't already exist to avoid redeclaration.
 */

if (defined('OCES_LOGGING_BOOTSTRAPPED')) {
    return;
}
define('OCES_LOGGING_BOOTSTRAPPED', true);

// Include centralized helpers (resolve relative to this file)
require_once __DIR__ . '/../lib/api_helpers.php';

/**
 * Define legacy functions only if they don't already exist.
 * These forward to the central log helpers.
 */

if (!function_exists('log_entry')) {
    function log_entry(string $level, string $component, string $message, array $meta = []): void
    {
        $levelNorm = strtoupper($level);
        $ctx = array_merge(['component' => $component], $meta);

        if (in_array($levelNorm, ['ERROR', 'CRITICAL'], true)) {
            if (function_exists('log_error')) {
                log_error($message, $ctx);
                return;
            }
        } elseif (in_array($levelNorm, ['WARN', 'WARNING'], true)) {
            if (function_exists('log_warn')) {
                log_warn($message, $ctx);
                return;
            }
        } else {
            if (function_exists('log_info')) {
                log_info($message, $ctx);
                return;
            }
        }

        // Fallback if central helpers missing: write JSON line to backend/logs/app-errors.log
        $logFile = __DIR__ . '/../logs/app-errors.log';
        $entry = [
            'ts' => gmdate('c'),
            'level' => $levelNorm,
            'component' => $component,
            'message' => $message,
            'meta' => $meta
        ];
        @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('log_error')) {
    function log_error(string $component, string $message, array $meta = []): void
    {
        log_entry('ERROR', $component, $message, $meta);
    }
}

if (!function_exists('log_warn')) {
    function log_warn(string $component, string $message, array $meta = []): void
    {
        log_entry('WARNING', $component, $message, $meta);
    }
}

if (!function_exists('log_info')) {
    function log_info(string $component, string $message, array $meta = []): void
    {
        log_entry('INFO', $component, $message, $meta);
    }
}
