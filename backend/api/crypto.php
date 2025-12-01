<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/api_helpers.php';


if (defined('OCES_CRYPTO_BOOTSTRAPPED')) {
    return;
}
define('OCES_CRYPTO_BOOTSTRAPPED', true);

$configPath = __DIR__ . '/../lib/config.php';
$configExample = __DIR__ . '/../lib/config.example.php';
$CONFIG = [];
if (file_exists($configPath)) {
    $CONFIG = include $configPath;
} elseif (file_exists($configExample)) {
    $CONFIG = include $configExample;
}

$DEMO_MODE = !empty($CONFIG['DEMO_MODE']) || getenv('OCES_DEMO_MODE') === '1';

function _oces_get_key_hex(string $name, array $cfg, bool $demoMode = false): ?string
{
    if (isset($cfg[$name]) && is_string($cfg[$name]) && trim($cfg[$name]) !== '') {
        return trim($cfg[$name]);
    }
    $env = getenv($name);
    if ($env !== false && trim($env) !== '') {
        return trim($env);
    }
    if ($demoMode) {
        // demo-only example key (32 bytes hex). Replace in production.
        return '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';
    }
    return null;
}

$encHex = _oces_get_key_hex('DATA_ENC_KEY_HEX', $CONFIG, $DEMO_MODE);
$hashHex = _oces_get_key_hex('DATA_HASH_KEY_HEX', $CONFIG, $DEMO_MODE);

if ($encHex === null || $hashHex === null) {
    throw new RuntimeException('Missing DATA_ENC_KEY_HEX or DATA_HASH_KEY_HEX. Provide keys via backend/lib/config.php or env variables.');
}

$DATA_ENC_KEY = hex2bin($encHex);
if ($DATA_ENC_KEY === false || strlen($DATA_ENC_KEY) !== 32) {
    throw new RuntimeException('Invalid DATA_ENC_KEY_HEX: must represent 32 bytes (64 hex chars).');
}
$DATA_HMAC_KEY = hex2bin($hashHex);
if ($DATA_HMAC_KEY === false || strlen($DATA_HMAC_KEY) < 16) {
    throw new RuntimeException('Invalid DATA_HASH_KEY_HEX: must be valid hex (recommended 32 bytes).');
}

if (!function_exists('encrypt_blob')) {
    function encrypt_blob(string $plaintext): string
    {
        global $DATA_ENC_KEY;
        $cipher = 'aes-256-gcm';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = random_bytes($ivlen);
        $tag = '';
        $ct = openssl_encrypt($plaintext, $cipher, $DATA_ENC_KEY, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new RuntimeException('encrypt_blob: encryption failed');
        }
        return base64_encode($iv . $tag . $ct);
    }
}

if (!function_exists('decrypt_blob')) {
    function decrypt_blob(string $b64): string
    {
        global $DATA_ENC_KEY;
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            throw new RuntimeException('decrypt_blob: invalid base64');
        }
        $cipher = 'aes-256-gcm';
        $ivlen = openssl_cipher_iv_length($cipher);
        $taglen = 16;
        if (strlen($raw) < ($ivlen + $taglen)) {
            throw new RuntimeException('decrypt_blob: ciphertext too short');
        }
        $iv = substr($raw, 0, $ivlen);
        $tag = substr($raw, $ivlen, $taglen);
        $ct = substr($raw, $ivlen + $taglen);
        $pt = openssl_decrypt($ct, $cipher, $DATA_ENC_KEY, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new RuntimeException('decrypt_blob: decryption failed or authentication tag mismatch');
        }
        return $pt;
    }
}

if (!function_exists('hmac_lookup')) {
    function hmac_lookup(string $value): string
    {
        global $DATA_HMAC_KEY;
        $normalized = strtolower(trim((string) $value));
        return hash_hmac('sha256', $normalized, $DATA_HMAC_KEY);
    }
}

if (!function_exists('encryptUsername')) {
    function encryptUsername(string $s): string
    {
        return encrypt_blob($s);
    }
}
if (!function_exists('decryptUsername')) {
    function decryptUsername(string $b64): string
    {
        return decrypt_blob($b64);
    }
}
if (!function_exists('usernameHash')) {
    function usernameHash(string $s): string
    {
        return hmac_lookup($s);
    }
}