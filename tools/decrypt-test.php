<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/api/init.php';

if ($argc < 2) {
    echo "Usage: php decrypt-test.php <user_id>\n";
    exit(1);
}
$id = (int) $argv[1];
$pdo = bootstrap_get('db');
$stmt = $pdo->prepare('SELECT username_enc FROM tblStudents WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    echo "No user\n";
    exit(1);
}
echo "encrypted: " . substr($row['username_enc'], 0, 80) . "...\n";
try {
    $plain = decrypt_blob($row['username_enc']);
    echo "decrypted: $plain\n";
} catch (Throwable $e) {
    echo "decryption failed: " . $e->getMessage() . "\n";
}
