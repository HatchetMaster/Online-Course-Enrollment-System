<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';


error_log("flash.php reached");

header('Content-Type: application/json; charset=utf-8');
$flash = $_SESSION['_flash'] ?? null;
if (isset($_SESSION['_flash']))
    unset($_SESSION['_flash']);

echo json_encode(['flash' => $flash]);
