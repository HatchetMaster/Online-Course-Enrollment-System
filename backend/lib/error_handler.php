<?php
// backend/lib/error_handler.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/simple_logger.php';

if (!function_exists('oces_error_handlers_registered')) {
    function oces_error_handlers_registered(): bool
    {
        return true;
    }
    oces_register_simple_handlers();
}
