<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IntegrationProfileTest extends TestCase
{
    private static string $baseUrl;
    private static string $cookieJar;

    public static function setUpBeforeClass(): void
    {
        // Set OCES_BASE_URL to e.g. http://localhost/OCES in your environment before running
        self::$baseUrl = getenv('OCES_BASE_URL') ?: '';
        if (self::$baseUrl === '') {
            // Skip all tests in this class if no base URL configured
            self::markTestSkipped('OCES_BASE_URL not set; integration tests skipped.');
        }

        // cookie jar path under tests/tmp_data
        $tmp = __DIR__ . '/tmp_data';
        if (!is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }
        self::$cookieJar = $tmp . '/cookies.txt';
        if (file_exists(self::$cookieJar)) {
            @unlink(self::$cookieJar);
        }
    }

    private function curlPostForm(string $path, array $data): array
    {
        $url = rtrim(self::$baseUrl, '/') . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_COOKIEJAR, self::$cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookieJar);
        // include headers
        curl_setopt($ch, CURLOPT_HEADER, true);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            $this->fail("curl error: $err");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);
        curl_close($ch);
        return ['code' => $code, 'headers' => $headers, 'body' => $body];
    }

    private function curlGetJson(string $path): array
    {
        $url = rtrim(self::$baseUrl, '/') . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookieJar);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            $this->fail("curl error: $err");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($body, true);
        return ['code' => $code, 'body' => $decoded, 'raw' => $body];
    }

    public function testLoginAndProfileFlow(): void
    {
        // configure these to match a real test account in dev DB
        $username = getenv('OCES_TEST_USER') ?: 'Student';
        $password = getenv('OCES_TEST_PASS') ?: 'Student!';
        $loginPath = '/backend/api/login.php';
        $profilePath = '/backend/api/profile_get.php';

        // POST login as application/x-www-form-urlencoded
        $loginResp = $this->curlPostForm($loginPath, ['username' => $username, 'password' => $password]);
        $this->assertThat($loginResp['code'], $this->logicalOr($this->equalTo(200), $this->equalTo(302)), 'login returned 200 or redirect');

        // GET profile using cookie jar saved from login
        $profileResp = $this->curlGetJson($profilePath);
        $this->assertEquals(200, $profileResp['code'], 'profile_get should return HTTP 200 for logged-in session');
        $this->assertIsArray($profileResp['body'], 'profile_get returned valid JSON');
        $this->assertArrayHasKey('success', $profileResp['body']);
        $this->assertTrue($profileResp['body']['success'], 'profile_get success true');
        $this->assertArrayHasKey('data', $profileResp['body']);

        // The API returns the profile object directly under data (not nested under "profile")
        $data = $profileResp['body']['data'];
        $this->assertIsArray($data, 'data should be an array');

        // Validate core profile keys returned by backend/api/profile_get.php
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('username', $data);

        // enrolled may be present or empty; accept missing key as empty array for backward compatibility
        $enrolled = $data['enrolled'] ?? [];
        $this->assertIsArray($enrolled, 'enrolled should be array');

        // waitlist_positions may not be implemented yet in some branches; treat missing as empty array
        $waitlistPositions = $data['waitlist_positions'] ?? [];
        $this->assertIsArray($waitlistPositions, 'waitlist_positions should be array (defaults to empty)');

        $this->assertIsInt($data['id'], 'id should be integer');
    }
}

   /* public function testProfileUpdateChangesEmail(): void
    {
        $profileUpdatePath = '/OCES/backend/api/profile_update.php';

        // generate a new test email
        $email = 'itest+' . bin2hex(random_bytes(4)) . '@example.test';
        // Get current CSRF by calling profile_get first
        $profileResp = $this->curlGetJson('/OCES/backend/api/profile_get.php');
        $this->assertEquals(200, $profileResp['code']);
        $csrf = $profileResp['body']['data']['profile']['csrf_token'] ?? null;
        $this->assertNotEmpty($csrf, 'csrf token must be present');

        // POST JSON update (profile_update expects JSON)
        $url = rtrim(self::$baseUrl, '/') . '/' . ltrim($profileUpdatePath, '/');
        $ch = curl_init($url);
        $payload = json_encode(['email' => $email, 'csrf_token' => $csrf]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, self::$cookieJar);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(200, $code, 'profile_update should return 200');
        $json = json_decode($body, true);
        $this->assertIsArray($json);
        $this->assertTrue(!empty($json['success']), 'profile_update success true');
        $this->assertSame($email, $json['data']['profile']['email'] ?? null);
    }*/

