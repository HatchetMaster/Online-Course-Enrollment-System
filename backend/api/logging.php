<?php
declare(strict_types=1);

if (defined('OCES_LOGGING_BOOTSTRAPPED'))
    return;
define('OCES_LOGGING_BOOTSTRAPPED', true);

$LOG_FILE = __DIR__ . '/../logs/app-errors.log';

function log_entry(string $level, string $component, string $message, array $meta = []): void
{
    global $LOG_FILE;
    $entry = [
        'ts' => gmdate('c'),
        'level' => $level,
        'component' => $component,
        'message' => $message,
        'meta' => $meta
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    error_log($line, 3, $LOG_FILE);
}

function log_error(string $component, string $message, array $meta = []): void
{
    log_entry('ERROR', $component, $message, $meta);
}
function log_warn(string $component, string $message, array $meta = []): void
{
    log_entry('WARNING', $component, $message, $meta);
}
function log_info(string $component, string $message, array $meta = []): void
{
    log_entry('INFO', $component, $message, $meta);
}
