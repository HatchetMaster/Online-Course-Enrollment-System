<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond_error_payload(405, 'invalid_method', 'use GET to list enrollments');
    }

    $user = require_auth();
    $userId = (int) ($user['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        respond_error_payload(401, 'authentication_required', 'authentication required');
    }

    $db = bootstrap_get('db');
    if (!($db instanceof PDO)) {
        respond_error_payload(500, 'internal_error', 'database unavailable');
    }

    $stmt = $db->prepare('
        SELECT c.id, c.course_name, c.course_code, e.created_at AS enrolled_at
        FROM tblEnrollments e
        JOIN tblCourses c ON e.course_id = c.id
        WHERE e.student_id = :id
        ORDER BY c.course_name
    ');
    $stmt->execute([':id' => $userId]);
    $enrolled = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get waitlist positions for this user (if any)
    $waitStmt = $db->prepare('
        SELECT w.course_id,
               w.id,
               (SELECT COUNT(*) FROM tblWaitlist w2 WHERE w2.course_id = w.course_id AND w2.id <= w.id) AS position
        FROM tblWaitlist w
        WHERE w.student_id = :id
    ');
    $waitStmt->execute([':id' => $userId]);
    $waitRows = $waitStmt->fetchAll(PDO::FETCH_ASSOC);

    $waitMap = [];
    foreach ($waitRows as $wr) {
        $waitMap[(int)$wr['course_id']] = (int)$wr['position'];
    }

    respond_success(['enrolled' => $enrolled, 'waitlist_positions' => $waitMap], 200);

} catch (Throwable $e) {
    oces_simple_log('critical', 'enrollments_exception', ['exception' => $e->getMessage()]);
    respond_error_payload(500, 'internal_error', 'internal server error');
}