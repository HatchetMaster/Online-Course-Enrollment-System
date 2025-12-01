<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

try {
    // Ensure user is authenticated; respond_error_payload will exit on failure
    $current = require_auth();
    $userId = (int) ($current['id'] ?? ($_SESSION['user_id'] ?? 0));
    if ($userId <= 0) {
        respond_error_payload(401, 'authentication_required', 'authentication required');
    }

    // Use the bootstrapped PDO from init.php
    global $BOOT;
    $pdo = $BOOT['db'] ?? null;
    if (!($pdo instanceof PDO)) {
        respond_error_payload(500, 'internal_error', 'database unavailable');
    }

    // Select the encrypted columns that actually exist in your schema
    $sql = 'SELECT id, username_enc, email_enc, first_name_enc, last_name_enc, created_at FROM tblStudents WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond_error_payload(404, 'not_found', 'user not found');
    }

    // decrypt_blob() is provided by crypto.php (loaded in init.php)
    $decrypt = function (?string $val) {
        if (empty($val))
            return null;
        try {
            return decrypt_blob($val);
        } catch (Throwable $e) {
            // If decrypt fails, fallback to null
            return null;
        }
    };

    $data = [
        'id' => (int) $row['id'],
        'username' => $decrypt($row['username_enc']),
        'email' => $decrypt($row['email_enc']),
        'firstName' => $decrypt($row['first_name_enc']),
        'lastName' => $decrypt($row['last_name_enc']),
        'createdAt' => $row['created_at'] ?? null,
    ];

    // Load enrolled courses — tolerant to missing table/other DB errors
    try {
        $coursesQ = $pdo->prepare('SELECT c.id, c.course_name, c.course_code FROM tblEnrollments e JOIN tblCourses c ON e.course_id = c.id WHERE e.student_id = :id ORDER BY c.course_name');
        $coursesQ->execute([':id' => $userId]);
        $courses = $coursesQ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $dbEx) {
        // Log and continue with empty list
        if (function_exists('oces_simple_log')) {
            oces_simple_log('warning', 'profile_courses_unavailable', ['exception' => $dbEx->getMessage(), 'user_id' => $userId]);
        }
        $courses = [];
    }

    $data['enrolled'] = $courses;

    respond_success($data, 200);

} catch (Throwable $e) {
    oces_simple_log('critical', 'profile_get_exception', ['exception' => $e->getMessage()]);
    respond_error_payload(500, 'internal_error', 'internal server error');
}