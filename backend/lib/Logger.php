<?php
// backend/lib/Logger.php
declare(strict_types=1);

/**
 * Backwards-compatible shim for legacy Logger class.
 * Forwards calls to the centralized simple logger helpers (log_info/log_warn/log_error).
 *
 * Supports multiple calling styles:
 *  - $logger = new Logger('component'); $logger->info('message', ['meta'=>...]);
 *  - $logger = new Logger(); $logger->info('component','message', ['meta'=>...]);
 *  - Logger::info('component','message', ['meta'=>...]); // static style
 *
 * The shim intentionally uses flexible argument handling to avoid TypeErrors.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Ensure central helpers are available
require_once __DIR__ . '/api_helpers.php';

class Logger
{
    private ?string $component;

    public function __construct(?string $component = null)
    {
        $this->component = $component;
    }

    /**
     * Internal helper to normalize (message, context) from various calling styles.
     *
     * Accepts:
     *  - (message, ctxArray)
     *  - (component, message)
     *  - (component, message, metaArray)
     *
     * If the instance was constructed with a component, and caller passed only (message, ctx),
     * the instance component will be added to the context if not already present.
     *
     * Returns [$message, $ctxArray]
     */
    private function normalizeArgs(array $args): array
    {
        $argc = count($args);
        if ($argc === 0) {
            return ['', []];
        }

        // 1 arg: message
        if ($argc === 1) {
            $msg = (string) $args[0];
            $ctx = [];
            if ($this->component !== null)
                $ctx['component'] = $this->component;
            return [$msg, $ctx];
        }

        // 2 args:
        // - (message, ctxArray)   OR
        // - (component, message)  (legacy)
        if ($argc === 2) {
            if (is_array($args[1])) {
                // (message, ctx)
                $msg = (string) $args[0];
                $ctx = $args[1];
                if ($this->component !== null && empty($ctx['component'])) {
                    $ctx['component'] = $this->component;
                }
                return [$msg, $ctx];
            } else {
                // (component, message)
                $component = (string) $args[0];
                $msg = (string) $args[1];
                $ctx = ['component' => $component];
                return [$msg, $ctx];
            }
        }

        // 3+ args: treat as legacy (component, message, metaArray)
        $component = (string) $args[0];
        $msg = (string) ($args[1] ?? '');
        $meta = isset($args[2]) && is_array($args[2]) ? $args[2] : [];
        $ctx = array_merge(['component' => $component], $meta);
        return [$msg, $ctx];
    }

    private function write(string $level, array $args): void
    {
        [$msg, $ctx] = $this->normalizeArgs($args);
        // Use the central helper functions
        switch (strtolower($level)) {
            case 'error':
            case 'critical':
                if (function_exists('log_error')) {
                    log_error($msg, $ctx);
                } else {
                    oces_simple_log('error', $msg, $ctx);
                }
                break;

            case 'warn':
            case 'warning':
                if (function_exists('log_warn')) {
                    log_warn($msg, $ctx);
                } else {
                    oces_simple_log('warning', $msg, $ctx);
                }
                break;

            case 'debug':
            default:
                if (function_exists('log_info')) {
                    log_info($msg, $ctx);
                } else {
                    oces_simple_log('info', $msg, $ctx);
                }
                break;
        }
    }

    // instance methods
    public function debug(...$args): void
    {
        $this->write('debug', $args);
    }
    public function info(...$args): void
    {
        $this->write('info', $args);
    }
    public function warn(...$args): void
    {
        $this->write('warn', $args);
    }
    public function warning(...$args): void
    {
        $this->write('warn', $args);
    }
    public function error(...$args): void
    {
        $this->write('error', $args);
    }
    public function critical(...$args): void
    {
        $this->write('critical', $args);
    }

    /**
     * Support static calls like Logger::info('component','message', ['meta'=>...])
     */
    public static function __callStatic($name, $arguments)
    {
        $name = strtolower($name);
        $instance = new self(null); // no default component for static calls
        switch ($name) {
            case 'info':
            case 'debug':
                $instance->info(...$arguments);
                return;
            case 'warn':
            case 'warning':
                $instance->warn(...$arguments);
                return;
            case 'error':
            case 'critical':
                $instance->error(...$arguments);
                return;
            default:
                throw new BadMethodCallException("Logger::{$name} is not supported");
        }
    }
}
