<?php
declare(strict_types=1);

/*
 * tools/create_default_students.php
 *
 * CLI helper to create a set of default students.
 *
 * Usage:
 *   php tools/create_default_students.php
 *
 * Output:
 *   Lists created (or existing) users with their assigned IDs and plaintext password.
 *
 * Notes:
 * - Uses the project's crypto helpers (encrypt_blob, hmac_lookup) and DB bootstrap via backend/api/init.php.
 * - Intended for local dev only. Do not run in production.
 */

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../backend/api/init.php';

$db = bootstrap_get('db');
if (!($db instanceof PDO)) {
    echo "Database not available. Check backend/api/init.php\n";
    exit(1);
}

if (!function_exists('hmac_lookup') || !function_exists('encrypt_blob')) {
    echo "Crypto helpers not available. Ensure backend/api/init.php loads crypto.php.\n";
    exit(1);
}

/**
 * Create a student if not exists. Returns user id.
 */
function create_or_get_user(PDO $db, string $username, string $email, string $first, string $last, string $password): int
{
    $uhash = hmac_lookup($username);

    // Return existing user if present
    $stmt = $db->prepare('SELECT id FROM tblStudents WHERE username_hash = :uhash LIMIT 1');
    $stmt->execute([':uhash' => $uhash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id'])) {
        return (int)$row['id'];
    }

    $username_enc = encrypt_blob($username);
    $email_enc = encrypt_blob($email);
    $first_enc = encrypt_blob($first);
    $last_enc = encrypt_blob($last);
    $email_hash = hmac_lookup($email);
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if ($password_hash === false) {
        throw new RuntimeException('password_hash failed');
    }

    $ins = $db->prepare('
        INSERT INTO tblStudents
          (first_name_enc, last_name_enc, username_enc, username_hash, email_enc, email_hash, password_hash, tfa_enabled, totp_secret, last_login, failed_login_count, locked_until)
        VALUES
          (:first_enc, :last_enc, :username_enc, :username_hash, :email_enc, :email_hash, :password_hash, 0, NULL, NULL, 0, NULL)
    ');
    $ins->execute([
        ':first_enc' => $first_enc,
        ':last_enc' => $last_enc,
        ':username_enc' => $username_enc,
        ':username_hash' => $uhash,
        ':email_enc' => $email_enc,
        ':email_hash' => $email_hash,
        ':password_hash' => $password_hash
    ]);

    return (int)$db->lastInsertId();
}

echo "Creating default students (local dev only)\n\n";

$created = [];
try {
    // Create Student1 through Student11
    for ($i = 1; $i <= 11; $i++) {
        $username = 'Student' . $i;
        $email = strtolower($username) . '@mail.com';
        $first = $username;
        $last = 'User';
        $password = $username . '!';

        try {
            $id = create_or_get_user($db, $username, $email, $first, $last, $password);
            $created[] = ['username' => $username, 'id' => $id, 'password' => $password, 'email' => $email];
            echo sprintf("User: %-10s id=%-4d  email=%-24s password=%s\n", $username, $id, $email, $password);
        } catch (Throwable $e) {
            echo "Error creating {$username}: " . $e->getMessage() . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "\nDone. You can log in with any of these accounts using the printed password.\n";