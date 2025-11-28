<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/api_helpers.php';
require_once __DIR__ . '/init.php';

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
