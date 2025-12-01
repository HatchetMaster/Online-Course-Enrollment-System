<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond_error_payload(405, 'invalid_method', 'use POST to add courses');
    }

    $user = require_auth(); // require login to add courses in demo
    $userId = (int) ($user['id'] ?? $_SESSION['user_id'] ?? 0);

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        respond_error_payload(415, 'unsupported_media_type', 'expecting application/json');
    }

    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        respond_error_payload(400, 'invalid_json', 'invalid JSON payload');
    }

    // CSRF
    check_csrf_from_payload($payload);

    $name = trim((string) ($payload['course_name'] ?? ''));
    $code = trim((string) ($payload['course_code'] ?? ''));
    $desc = isset($payload['description']) ? (string) $payload['description'] : null;
    $capacity = isset($payload['capacity']) ? (int) $payload['capacity'] : 0;

    // New date fields (expected format: YYYY-MM-DD)
    $startDate = isset($payload['course_start_date']) ? trim((string) $payload['course_start_date']) : null;
    $endDate = isset($payload['course_end_date']) ? trim((string) $payload['course_end_date']) : null;

    // Basic validation
    if ($name === '' || $code === '') {
        respond_error_payload(400, 'missing_fields', 'course_name and course_code are required');
    }
    if ($capacity < 0)
        $capacity = 0;

    // Validate dates if provided
    $startSql = null;
    $endSql = null;
    if ($startDate !== null) {
        $dt = DateTime::createFromFormat('Y-m-d', $startDate);
        if (!$dt || $dt->format('Y-m-d') !== $startDate) {
            respond_error_payload(400, 'invalid_date', 'course_start_date must be YYYY-MM-DD');
        }
        $startSql = $startDate;
    }
    if ($endDate !== null) {
        $dt = DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$dt || $dt->format('Y-m-d') !== $endDate) {
            respond_error_payload(400, 'invalid_date', 'course_end_date must be YYYY-MM-DD');
        }
        $endSql = $endDate;
    }
    if ($startSql !== null && $endSql !== null) {
        if (strcmp($startSql, $endSql) > 0) {
            respond_error_payload(400, 'invalid_date_range', 'course_start_date must be on or before course_end_date');
        }
    }

    $db = bootstrap_get('db');
    if (!($db instanceof PDO)) {
        respond_error_payload(500, 'internal_error', 'database unavailable');
    }

    try {
        $stmt = $db->prepare('INSERT INTO tblCourses (course_name, course_code, course_start_date, course_end_date, description, capacity) VALUES (:name, :code, :start, :end, :desc, :capacity)');
        $stmt->execute([
            ':name' => $name,
            ':code' => $code,
            ':start' => $startSql,
            ':end' => $endSql,
            ':desc' => $desc,
            ':capacity' => $capacity
        ]);
        $newId = (int) $db->lastInsertId();
        respond_success(['message' => 'course_created', 'course' => ['id' => $newId, 'course_name' => $name, 'course_code' => $code, 'capacity' => $capacity]], 201);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            respond_error_payload(409, 'conflict', 'course code already exists');
        }
        throw $e;
    }
} catch (Throwable $e) {
    oces_simple_log('critical', 'course_add_exception', ['exception' => $e->getMessage()]);
    respond_error_payload(500, 'internal_error', 'internal server error');
}