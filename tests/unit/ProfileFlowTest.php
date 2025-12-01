<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProfileFlowTest extends TestCase
{
    private static string $dbPath;
    private static ?\PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        // ensure session is available for the tests
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];

        // create tmp_data dir
        $tmpdir = __DIR__ . '/tmp_data';
        if (!is_dir($tmpdir))
            mkdir($tmpdir, 0755, true);

        self::$dbPath = $tmpdir . '/oces_test.sqlite';
        if (file_exists(self::$dbPath)) {
            @unlink(self::$dbPath);
        }

        // create sqlite and minimal table matching your spec
        $dsn = 'sqlite:' . self::$dbPath;
        self::$pdo = new PDO($dsn);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $sql = <<<SQL
CREATE TABLE tblStudents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    first_name TEXT,
    last_name TEXT,
    email TEXT
);
SQL;
        self::$pdo->exec($sql);

        // insert a test student (id will be 1)
        $stmt = self::$pdo->prepare('INSERT INTO tblStudents (username, first_name, last_name, email) VALUES (:u,:f,:l,:e)');
        $stmt->execute([
            ':u' => 'testuser',
            ':f' => 'Test',
            ':l' => 'User',
            ':e' => 'test@example.test'
        ]);

        // ensure the tests use this sqlite via environment/config
        putenv('OCES_TEST_DB=' . realpath(self::$dbPath));
    }

    public static function tearDownAfterClass(): void
    {
        $_SESSION = [];
        if (isset(self::$pdo)) {
            self::$pdo = null;
        }
        if (file_exists(self::$dbPath)) {
            @unlink(self::$dbPath);
        }
        $tmpdir = __DIR__ . '/tmp_data';
        if (is_dir($tmpdir)) {
            @rmdir($tmpdir);
        }
    }

    /**
     * Helper that simulates the profile_get.php DB read behavior using the test sqlite.
     */
    private function fetchProfileFromTestDb(int $userId): array|false
    {
        $pdo = new PDO('sqlite:' . self::$dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email FROM tblStudents WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    public function testRequireAuthFallbackUsesSession(): void
    {
        $_SESSION['user_id'] = 1;

        require_once __DIR__ . '/../backend/lib/api_helpers.php';

        $user = require_auth();

        $this->assertIsArray($user, 'require_auth should return an array for a logged-in session');
        $this->assertArrayHasKey('id', $user);
        $this->assertSame(1, (int) $user['id'], 'require_auth should return the session user id');
    }

    public function testProfileRetrievalFromDb(): void
    {
        $_SESSION['user_id'] = 1;

        $row = $this->fetchProfileFromTestDb(1);
        $this->assertIsArray($row, 'Expected to find a row for user id 1');
        $this->assertSame('testuser', $row['username']);
        $this->assertSame('Test', $row['first_name']);
        $this->assertSame('User', $row['last_name']);
        $this->assertSame('test@example.test', $row['email']);
    }

    public function testCheckCsrfFromPayloadSuccessAndFailure(): void
    {
        // Set a session CSRF token
        $_SESSION['csrf_token'] = 'tok123';

        // Valid payload -> should not throw and returns void (which is null when asserted)
        $this->assertNull(check_csrf_from_payload(['csrf_token' => 'tok123']));

        // Invalid payload -> expect respond_error_payload to raise (test bootstrap transforms into exception)
        $this->expectException(Exception::class);

        // Suppress any output while the invalid-case throws
        ob_start();
        try {
            check_csrf_from_payload(['csrf_token' => 'bad']);
        } finally {
            ob_end_clean();
        }
    }
}
