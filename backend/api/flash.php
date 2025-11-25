<?php
declare(strict_types=1);
error_log("flash.php reached");

header('Content-Type: application/json; charset=utf-8');
$flash = $_SESSION['_flash'] ?? null;
if (isset($_SESSION['_flash']))
    unset($_SESSION['_flash']);

echo json_encode(['flash' => $flash]);
