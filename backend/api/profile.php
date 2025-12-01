<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

class ProfileAPI
{
    public function __construct()
    {
    }

    public function getProfile(int $userId): array
    {
        if ($userId <= 0) {
            log_warn('profile', 'getProfile called with invalid user id', ['user_id' => $userId]);
            throw new InvalidArgumentException('invalid user id');
        }

        $db = bootstrap_get('db');
        if (!($db instanceof PDO)) {
            oces_simple_log('critical', 'database_unavailable', ['user_id' => $userId]);
            throw new RuntimeException('database unavailable');
        }

        try {
            $stmt = $db->prepare('SELECT id, username_enc, email_enc, first_name_enc, last_name_enc, created_at FROM tblStudents WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                log_info('profile', 'getProfile: user not found', ['user_id' => $userId]);
                throw new RuntimeException('user not found');
            }

            $decrypt = function (?string $val): ?string {
                if (empty($val)) return null;
                try { return decrypt_blob($val); } catch (Throwable $e) { oces_simple_log('warning', 'decrypt_failed', ['exception' => $e->getMessage()]); return null; }
            };

            $data = [
                'id' => (int)$row['id'],
                'username' => $decrypt($row['username_enc']),
                'email' => $decrypt($row['email_enc']),
                'firstName' => $decrypt($row['first_name_enc']),
                'lastName' => $decrypt($row['last_name_enc']),
                'createdAt' => $row['created_at'] ?? null,
            ];

            // enrolled
            $coursesQ = $db->prepare('
                SELECT c.id, c.course_name, c.course_code, e.created_at AS enrolled_at
                FROM tblEnrollments e
                JOIN tblCourses c ON e.course_id = c.id
                WHERE e.student_id = :id
                ORDER BY c.course_name
            ');
            $coursesQ->execute([':id' => $userId]);
            $data['enrolled'] = $coursesQ->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // waitlist positions
            $waitStmt = $db->prepare('
                SELECT w.course_id, w.id,
                       (SELECT COUNT(*) FROM tblWaitlist w2 WHERE w2.course_id = w.course_id AND w2.id <= w.id) AS position
                FROM tblWaitlist w
                WHERE w.student_id = :id
            ');
            $waitStmt->execute([':id' => $userId]);
            $waitMap = [];
            foreach ($waitStmt->fetchAll(PDO::FETCH_ASSOC) as $wr) {
                $waitMap[(int)$wr['course_id']] = (int)$wr['position'];
            }
            $data['waitlist_positions'] = $waitMap;

            return $data;
        } catch (Throwable $e) {
            oces_simple_log('critical', 'profile_get_exception', ['exception' => $e->getMessage(), 'user_id' => $userId]);
            throw $e;
        }
    }
}