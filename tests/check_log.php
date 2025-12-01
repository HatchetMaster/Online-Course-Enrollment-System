<?php
// tests/check_log.php
declare(strict_types=1);

require __DIR__ . '/../backend/lib/api_helpers.php';

echo "=== LOGGING DIAGNOSTIC ===\n";

$env = getenv('OCES_LOG_DIR');
echo "OCES_LOG_DIR (env): " . var_export($env, true) . "\n";

$cfg = oces_config();
echo "cfg['LOG_DIR']: " . var_export($cfg['LOG_DIR'] ?? null, true) . "\n";

$default = realpath(__DIR__ . '/../backend/lib/../../logs');
echo "default resolved (backend/logs realpath): " . var_export($default, true) . "\n";

$cfgDir = $cfg['LOG_DIR'] ?? '';
echo "is_dir(cfg['LOG_DIR']): " . (is_dir($cfgDir) ? 'yes' : 'no') . "\n";
echo "is_dir(default): " . (is_dir($default) ? 'yes' : 'no') . "\n";

$writePath = ($cfg['LOG_DIR'] ?? $default) ?: (__DIR__ . '/../backend/logs');
$writePath = rtrim($writePath, '/\\') . '/test-write.txt';

echo "Attempting write to: $writePath\n";
$ok = @file_put_contents($writePath, "test\n", FILE_APPEND | LOCK_EX);
echo "file_put_contents return: " . var_export($ok, true) . "\n";
echo "file_exists: " . (file_exists($writePath) ? 'yes' : 'no') . "\n";

echo "is_writable(dir): " . (is_writable(dirname($writePath)) ? 'yes' : 'no') . "\n";

echo "END\n";
