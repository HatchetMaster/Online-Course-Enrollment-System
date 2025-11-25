<?php
declare(strict_types=1);
if ($argc < 3) {
    echo "Usage: php tools/show-user-debug.php <by_id|by_username> <value>\n";
    echo "Examples:\n  php tools/show-user-debug.php by_id 1\n  php tools/show-user-debug.php by_username clitest\n";
    exit(1);
}
require_once __DIR__ . '/../backend/api/init.php';
$mode = $argv[1];
$val = $argv[2];
$pdo = bootstrap_get('db');

if ($mode === 'by_id') {
    $stmt = $pdo->prepare('SELECT * FROM tblStudents WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $val]);
} else {
    $norm = strtolower(trim($val));
    $uhash = hmac_lookup($norm);
    $stmt = $pdo->prepare('SELECT * FROM tblStudents WHERE username_hash = :uhash LIMIT 1');
    $stmt->execute([':uhash' => $uhash]);
}
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) {
    echo "No user found\n";
    exit(0);
}
echo "DB id=" . $r['id'] . PHP_EOL;
echo "username_enc(sample)=" . substr($r['username_enc'], 0, 60) . "..." . PHP_EOL;
try {
    $u = decrypt_blob($r['username_enc']);
} catch (Throwable $e) {
    $u = '<decrypt failed>';
}
echo "decrypted_username=" . $u . PHP_EOL;
echo "username_hash=" . $r['username_hash'] . PHP_EOL;
echo "password_hash=" . substr($r['password_hash'], 0, 30) . "..." . PHP_EOL;
echo "password_verify(plain) => ";
if ($argc >= 4) {
    $pw = $argv[3];
    echo (password_verify($pw, $r['password_hash']) ? "TRUE" : "FALSE") . PHP_EOL;
} else {
    echo "no-plain-provided\n";
}
