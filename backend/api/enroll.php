<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond_error_payload(405, 'invalid_method', 'use POST to enroll/cancel');
    }

    $user = require_auth();
    $userId = (int) ($user['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        respond_error_payload(401, 'authentication_required', 'authentication required');
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        respond_error_payload(415, 'unsupported_media_type', 'expecting application/json');
    }

    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        respond_error_payload(400, 'invalid_json', 'invalid JSON payload');
    }

    // CSRF check
    check_csrf_from_payload($payload);

    $courseId = isset($payload['course_id']) ? (int) $payload['course_id'] : 0;
    $action = isset($payload['action']) ? strtolower(trim((string)$payload['action'])) : 'enroll';

    if ($courseId <= 0) {
        respond_error_payload(400, 'invalid_course', 'course_id is required');
    }
    if (!in_array($action, ['enroll','cancel'], true)) {
        respond_error_payload(400, 'invalid_action', 'action must be "enroll" or "cancel"');
    }

    $db = bootstrap_get('db');
    if (!($db instanceof PDO)) {
        respond_error_payload(500, 'internal_error', 'database unavailable');
    }

    // Verify course exists and lock the row to avoid race conditions
    $db->beginTransaction();
    $courseStmt = $db->prepare('SELECT id, course_name, capacity FROM tblCourses WHERE id = :id FOR UPDATE');
    $courseStmt->execute([':id' => $courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        $db->rollBack();
        respond_error_payload(404, 'course_not_found', 'course not found');
    }
    $capacity = (int) $course['capacity'];

    // Enroll flow
    if ($action === 'enroll') {
        // already enrolled?
        $chk = $db->prepare('SELECT id FROM tblEnrollments WHERE student_id = :sid AND course_id = :cid LIMIT 1');
        $chk->execute([':sid' => $userId, ':cid' => $courseId]);
        if ($chk->fetch()) {
            $db->rollBack();
            respond_error_payload(409, 'already_enrolled', 'already enrolled in course');
        }

        // If capacity == 0 treat as unlimited
        $enrolledCount = 0;
        if ($capacity > 0) {
            $cnt = $db->prepare('SELECT COUNT(*) AS c FROM tblEnrollments WHERE course_id = :cid');
            $cnt->execute([':cid' => $courseId]);
            $row = $cnt->fetch(PDO::FETCH_ASSOC);
            $enrolledCount = (int) ($row['c'] ?? 0);
        }

        if ($capacity > 0 && $enrolledCount >= $capacity) {
            // Course full -> add to waitlist if not already
            $wchk = $db->prepare('SELECT id FROM tblWaitlist WHERE student_id = :sid AND course_id = :cid LIMIT 1');
            $wchk->execute([':sid' => $userId, ':cid' => $courseId]);
            if ($wchk->fetch()) {
                $db->rollBack();
                respond_error_payload(409, 'already_waitlisted', 'already on waitlist for this course');
            }

            // compute position (current waitlist length + 1)
            $posStmt = $db->prepare('SELECT COUNT(*) AS c FROM tblWaitlist WHERE course_id = :cid');
            $posStmt->execute([':cid' => $courseId]);
            $posRow = $posStmt->fetch(PDO::FETCH_ASSOC);
            $position = (int) ($posRow['c'] ?? 0) + 1;

            $ins = $db->prepare('INSERT INTO tblWaitlist (student_id, course_id) VALUES (:sid, :cid)');
            $ins->execute([':sid' => $userId, ':cid' => $courseId]);
            $db->commit();

            respond_success([
                'message' => 'waitlisted',
                'waitlist_position' => $position,
                'course' => ['id' => (int)$course['id'], 'name' => $course['course_name']]
            ], 202);
        }

        // There is capacity -> insert enrollment
        $ins = $db->prepare('INSERT INTO tblEnrollments (student_id, course_id) VALUES (:sid, :cid)');
        $ins->execute([':sid' => $userId, ':cid' => $courseId]);
        $db->commit();

        respond_success([
            'message' => 'enrolled',
            'course' => ['id' => (int)$course['id'], 'name' => $course['course_name']]
        ], 201);
    }

    // Cancel flow (action === 'cancel')
    // First try to delete enrollment
    $del = $db->prepare('DELETE FROM tblEnrollments WHERE student_id = :sid AND course_id = :cid');
    $del->execute([':sid' => $userId, ':cid' => $courseId]);
    $deleted = $del->rowCount();

    if ($deleted > 0) {
        // Promote first waitlist entry, if any
        $promoteStmt = $db->prepare('SELECT id, student_id FROM tblWaitlist WHERE course_id = :cid ORDER BY created_at ASC LIMIT 1 FOR UPDATE');
        $promoteStmt->execute([':cid' => $courseId]);
        $waitRow = $promoteStmt->fetch(PDO::FETCH_ASSOC);
        if ($waitRow) {
            $waitId = (int)$waitRow['id'];
            $waitStudent = (int)$waitRow['student_id'];

            // remove from waitlist and insert enrollment for promoted student
            $delW = $db->prepare('DELETE FROM tblWaitlist WHERE id = :id');
            $delW->execute([':id' => $waitId]);

            $ins2 = $db->prepare('INSERT INTO tblEnrollments (student_id, course_id) VALUES (:sid, :cid)');
            $ins2->execute([':sid' => $waitStudent, ':cid' => $courseId]);

            $db->commit();

            oces_simple_log('info', 'waitlist_promote', ['course_id' => $courseId, 'promoted_student' => $waitStudent]);

            respond_success([
                'message' => 'cancelled_and_promoted',
                'promoted_student' => $waitStudent,
                'course' => ['id' => (int)$course['id'], 'name' => $course['course_name']]
            ], 200);
        }

        $db->commit();
        respond_success(['message' => 'cancelled', 'course' => ['id' => (int)$course['id'], 'name' => $course['course_name'] ]], 200);
    }

    // Not enrolled — attempt to remove from waitlist (student cancelling waitlist)
    $delW2 = $db->prepare('DELETE FROM tblWaitlist WHERE student_id = :sid AND course_id = :cid');
    $delW2->execute([':sid' => $userId, ':cid' => $courseId]);
    $delW2Count = $delW2->rowCount();
    $db->commit();

    if ($delW2Count > 0) {
        respond_success(['message' => 'waitlist_removed', 'course' => ['id' => (int)$course['id'], 'name' => $course['course_name'] ]], 200);
    }

    respond_error_payload(404, 'not_enrolled_or_waitlisted', 'not enrolled or on waitlist for this course');

} catch (Throwable $e) {
    // If transaction remains open, roll it back
    try {
        if (!empty($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
    } catch (Throwable $_) {
    }
    oces_simple_log('critical', 'enroll_exception', ['exception' => $e->getMessage()]);
    respond_error_payload(500, 'internal_error', 'internal server error');
}