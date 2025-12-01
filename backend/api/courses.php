<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond_error_payload(405, 'invalid_method', 'use GET to list courses');
    }

    $db = bootstrap_get('db');
    if (!($db instanceof PDO)) {
        respond_error_payload(500, 'internal_error', 'database unavailable');
    }

    $sql = <<<SQL
SELECT
  c.id,
  c.course_name,
  c.course_code,
  c.course_start_date,
  c.course_end_date,
  c.description,
  c.capacity,
  (SELECT COUNT(*) FROM tblEnrollments e WHERE e.course_id = c.id) AS enrolled_count,
  (SELECT COUNT(*) FROM tblWaitlist w WHERE w.course_id = c.id) AS waitlist_count
FROM tblCourses c
ORDER BY c.course_name
SQL;

    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize numeric fields and ensure date fields are present
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['capacity'] = (int) $r['capacity'];
        $r['enrolled_count'] = (int) $r['enrolled_count'];
        $r['waitlist_count'] = (int) $r['waitlist_count'];
        // Ensure date fields are strings (or null)
        $r['course_start_date'] = isset($r['course_start_date']) ? $r['course_start_date'] : null;
        $r['course_end_date'] = isset($r['course_end_date']) ? $r['course_end_date'] : null;
    }

    respond_success(['courses' => $rows], 200);
} catch (Throwable $e) {
    oces_simple_log('critical', 'courses_exception', ['exception' => $e->getMessage()]);
    respond_error_payload(500, 'internal_error', 'internal server error');
}