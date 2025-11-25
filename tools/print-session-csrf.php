<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/api/init.php';

$token = $_SESSION['csrf_token'] ?? null;
echo "session_csrf=" . ($token === null ? '(none)' : $token) . PHP_EOL;

echo "session_id=" . session_id() . PHP_EOL;
