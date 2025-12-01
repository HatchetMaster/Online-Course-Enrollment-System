<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SimpleLoggerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Ensure the tmp log dir is empty at start
        if (defined('OCES_TEST_LOG_FILE')) {
            $path = OCES_TEST_LOG_FILE;
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (file_exists($path)) {
                @unlink($path);
            }
        } else {
            // fallback: ensure tests/tmp_logs exists
            $tmp = __DIR__ . '/tmp_logs';
            if (!is_dir($tmp))
                mkdir($tmp, 0755, true);
        }

        // Ensure session is clean
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        $_SESSION = [];
    }

    public static function tearDownAfterClass(): void
    {
        // cleanup test logs
        if (defined('OCES_TEST_LOG_FILE')) {
            $path = OCES_TEST_LOG_FILE;
            if (file_exists($path)) {
                @unlink($path);
            }
            $dir = dirname($path);
            // do not remove dir if it contains other files; only remove if empty
            if (is_dir($dir) && count(glob($dir . DIRECTORY_SEPARATOR . '*')) === 0) {
                @rmdir($dir);
            }
        }
        // remove tmp dir if present
        $tmp = __DIR__ . '/tmp_logs';
        if (is_dir($tmp)) {
            $files = glob("$tmp/*");
            foreach ($files as $f) {
                if (is_file($f))
                    @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    private function readLastLogLine(): array
    {
        $this->assertTrue(defined('OCES_TEST_LOG_FILE'), 'OCES_TEST_LOG_FILE must be defined in bootstrap');
        $path = OCES_TEST_LOG_FILE;
        $this->assertFileExists($path, "Expected log file $path to exist");

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotEmpty($lines, 'Log file should not be empty');
        $last = array_pop($lines);
        $decoded = json_decode($last, true);
        $this->assertNotNull($decoded, 'Last log line must be valid JSON');
        return $decoded;
    }

    public function testRedactSensitiveFields(): void
    {
        $in = ['password' => 'secret', 'user' => 'alice', 'nested' => ['totp_secret' => '123456']];
        $out = oces_redact_sensitive($in);
        $this->assertSame('REDACTED', $out['password']);
        $this->assertSame('alice', $out['user']);
        $this->assertSame('REDACTED', $out['nested']['totp_secret']);
    }

    public function testRequestIdIsStable(): void
    {
        // same session, repeated calls should return same id
        $id1 = oces_get_request_id();
        $id2 = oces_get_request_id();
        $this->assertSame($id1, $id2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $id1);
    }

    public function testSimpleLogWritesJsonLineAndRedacts(): void
    {
        // write log
        oces_simple_log('info', 'unit test message', ['password' => 'p@ss', 'role' => 'tester']);
        $decoded = $this->readLastLogLine();

        $this->assertArrayHasKey('ts', $decoded);
        $this->assertSame('info', $decoded['level']);
        $this->assertArrayHasKey('request_id', $decoded);
        $this->assertArrayHasKey('msg', $decoded);
        $this->assertSame('unit test message', $decoded['msg']);
        $this->assertArrayHasKey('context', $decoded);
        $this->assertArrayHasKey('password', $decoded['context']);
        $this->assertSame('REDACTED', $decoded['context']['password']);
        $this->assertSame('tester', $decoded['context']['role']);
    }

    public function testLoggerShimForwardsToCentralLogger(): void
    {
        // If Logger class exists (shim), use it
        if (!class_exists('Logger')) {
            $this->markTestSkipped('Logger shim not present');
        }

        // Use the instance-style call
        $logger = new Logger('auth');
        $logger->info('User logged in', ['password' => 'abc123', 'user' => 42]);

        $decoded = $this->readLastLogLine();
        $this->assertStringContainsString('User logged in', $decoded['msg']);
        // shim sets component in context
        $this->assertArrayHasKey('component', $decoded['context']);
        $this->assertSame('auth', $decoded['context']['component']);
        $this->assertSame('REDACTED', $decoded['context']['password']);
    }

    public function testLoggingWrapperFunctionsForward(): void
    {
        // If legacy logging.php functions exist, they should forward
        if (!function_exists('log_info')) {
            $this->markTestSkipped('log_info not present');
        }

        // call wrapper like legacy usage
        log_info('legacy', 'legacy message', ['password' => 'xyz']);
        $decoded = $this->readLastLogLine();
        $this->assertStringContainsString('legacy message', $decoded['msg']);
        $this->assertSame('REDACTED', $decoded['context']['password']);
        $this->assertSame('legacy', $decoded['context']['component']);
    }

    public function testRequireAuthFallbackUsesSession(): void
    {
        // ensure no bootstrap_get exists
        if (function_exists('bootstrap_get')) {
            // if bootstrap_get exists and returns current_user, skip, because behavior may vary
            $val = bootstrap_get('current_user');
            if (!empty($val)) {
                $this->markTestSkipped('bootstrap_get returns user; skipping fallback test');
            }
        }

        // No user in session -> require_auth() will exit with 401; set a user to test success path
        $_SESSION['user_id'] = 999;
        $user = require_auth();
        $this->assertIsArray($user);
        $this->assertSame(999, $user['id']);
        // cleanup
        unset($_SESSION['user_id']);
    }

    public function testCheckCsrfFromPayloadSuccess(): void
    {
        // set a session token and pass it
        $_SESSION['csrf_token'] = 'tok123';
        // should not throw / exit for a valid token
        check_csrf_from_payload(['csrf_token' => 'tok123']);
        $this->assertTrue(true, 'check_csrf_from_payload did not error for valid token');
        unset($_SESSION['csrf_token']);
    }
}
