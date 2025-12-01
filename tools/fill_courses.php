<?php
declare(strict_types=1);

/*
 * tools/fill_courses.php
 *
 * CLI helper to create test students and fill two courses to capacity + 1 waitlist entry.
 *
 * Usage:
 *   php tools/fill_courses.php INT303 INT304
 *
 * If no course codes are provided, defaults to INT303 and INT304.
 *
 * The script:
 *  - ensures course exists
 *  - creates up to (capacity + 1) test students for each course (skips existing usernames)
 *  - enrolls students up to capacity
 *  - places the (capacity+1)th student on the waitlist
 *
 * Designed for local dev / testing only.
 */

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI.\n";
    exit(1);
}

$argvCodes = array_slice($argv, 1);
$courseCodes = count($argvCodes) ? $argvCodes : ['INT303', 'INT304'];

require_once __DIR__ . '/../backend/api/init.php';

$db = bootstrap_get('db');
if (!($db instanceof PDO)) {
    echo "Database not available. Check backend/api/init.php\n";
    exit(1);
}

function make_test_user(PDO $db, string $username, string $email): int
{
    // Use project crypto helpers
    if (!function_exists('hmac_lookup') || !function_exists('encrypt_blob')) {
        throw new RuntimeException('Crypto helpers not available. Ensure backend/api/init.php loads crypto.php.');
    }

    $uhash = hmac_lookup($username);

    // If user exists, return id
    $stmt = $db->prepare('SELECT id FROM tblStudents WHERE username_hash = :uhash LIMIT 1');
    $stmt->execute([':uhash' => $uhash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id'])) {
        return (int)$row['id'];
    }

    $username_enc = encrypt_blob($username);
    $email_enc = encrypt_blob($email);
    $email_hash = hmac_lookup($email);
    $password_hash = password_hash('Password1!', PASSWORD_DEFAULT);
    if ($password_hash === false) {
        throw new RuntimeException('password_hash failed');
    }

    $ins = $db->prepare('
        INSERT INTO tblStudents
          (first_name_enc, last_name_enc, username_enc, username_hash, email_enc, email_hash, password_hash, tfa_enabled, totp_secret, last_login, failed_login_count, locked_until)
        VALUES
          (:first_enc, :last_enc, :username_enc, :username_hash, :email_enc, :email_hash, :password_hash, 0, NULL, NULL, 0, NULL)
    ');

    // Use placeholder names derived from username
    $parts = explode('_', $username, 2);
    $first = $parts[0] ?: 'Test';
    $last = $parts[1] ?? 'User';

    $ins->execute([
        ':first_enc' => encrypt_blob($first),
        ':last_enc' => encrypt_blob($last),
        ':username_enc' => $username_enc,
        ':username_hash' => $uhash,
        ':email_enc' => $email_enc,
        ':email_hash' => $email_hash,
        ':password_hash' => $password_hash
    ]);

    return (int)$db->lastInsertId();
}

foreach ($courseCodes as $code) {
    echo "Processing course: {$code}\n";
    try {
        $db->beginTransaction();

        $cstmt = $db->prepare('SELECT id, course_name, capacity FROM tblCourses WHERE course_code = :code LIMIT 1 FOR UPDATE');
        $cstmt->execute([':code' => $code]);
        $course = $cstmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) {
            $db->rollBack();
            echo "  Course not found: {$code}\n";
            continue;
        }
        $courseId = (int)$course['id'];
        $capacity = (int)$course['capacity'];
        if ($capacity <= 0) {
            // For testing, treat 0 (unlimited) as 5 to exercise waitlist
            $capacity = 5;
        }
        echo "  Course ID={$courseId}, capacity={$capacity}\n";

        // Determine how many to create: capacity + 1 (to produce a waitlist entry)
        $target = $capacity + 1;

        // Create and enroll students
        for ($i = 1; $i <= $target; $i++) {
            $username = sprintf('test_%s_%02d', strtolower($code), $i);
            $email = $username . '@example.local';

            // create or get student id
            $studentId = make_test_user($db, $username, $email);

            // check if already enrolled
            $eChk = $db->prepare('SELECT id FROM tblEnrollments WHERE student_id = :sid AND course_id = :cid LIMIT 1');
            $eChk->execute([':sid' => $studentId, ':cid' => $courseId]);
            if ($eChk->fetch()) {
                echo "    Student {$username} (id {$studentId}) already enrolled — skipping\n";
                continue;
            }

            // check if already waitlisted
            $wChk = $db->prepare('SELECT id FROM tblWaitlist WHERE student_id = :sid AND course_id = :cid LIMIT 1');
            $wChk->execute([':sid' => $studentId, ':cid' => $courseId]);
            if ($wChk->fetch()) {
                echo "    Student {$username} (id {$studentId}) already waitlisted — skipping\n";
                continue;
            }

            // Count current enrolled
            $cnt = $db->prepare('SELECT COUNT(*) AS c FROM tblEnrollments WHERE course_id = :cid');
            $cnt->execute([':cid' => $courseId]);
            $row = $cnt->fetch(PDO::FETCH_ASSOC);
            $enrolledCount = (int)($row['c'] ?? 0);

            if ($enrolledCount < $capacity) {
                $ins = $db->prepare('INSERT INTO tblEnrollments (student_id, course_id) VALUES (:sid, :cid)');
                $ins->execute([':sid' => $studentId, ':cid' => $courseId]);
                echo "    Enrolled {$username} (id {$studentId}) — enrolled count now " . ($enrolledCount + 1) . "\n";
            } else {
                $insW = $db->prepare('INSERT INTO tblWaitlist (student_id, course_id) VALUES (:sid, :cid)');
                $insW->execute([':sid' => $studentId, ':cid' => $courseId]);
                echo "    Added {$username} (id {$studentId}) to waitlist\n";
            }
        }

        $db->commit();
        echo "  Done for course {$code}\n\n";
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo "  Error processing {$code}: " . $e->getMessage() . "\n";
    }
}

echo "All done.\n";