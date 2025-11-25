<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/api/init.php';

$payload = [
    'firstName' => $argv[1] ?? 'Cli',
    'lastName' => $argv[2] ?? 'Tester',
    'username' => $argv[3] ?? 'clitest' . rand(1000, 9999),
    'email' => $argv[4] ?? 'clitest' . rand(1000, 9999) . '@mail.com',
    'password' => $argv[5] ?? 'Password1!'
];

$handler = bootstrap_get('registration_handler');
if (!($handler instanceof RegistrationHandler)) {
    echo "RegistrationHandler not available\n";
    exit(1);
}
$res = $handler->handle($payload);
echo json_encode($res, JSON_PRETTY_PRINT) . "\n";
