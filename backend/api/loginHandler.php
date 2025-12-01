<?php
declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../lib/api_helpers.php';

class LoginHandler
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function handle(string $username, string $password): array
    {
        if ($username === '' || $password === '') {
            return [
                'status_code' => 400,
                'success' => false,
                'need2fa' => false,
                'user_id' => null,
                'message' => 'username and password required'
            ];
        }

        try {
            $uhash = hmac_lookup($username);
            $stmt = $this->pdo->prepare(
                'SELECT id, password_hash, tfa_enabled FROM tblStudents WHERE username_hash = :uhash LIMIT 1'
            );
            $stmt->execute([':uhash' => $uhash]);
            $row = $stmt->fetch();

            if (!$row) {
                return [
                    'status_code' => 401,
                    'success' => false,
                    'need2fa' => false,
                    'user_id' => null,
                    'message' => 'invalid credentials'
                ];
            }

            $userId = (int) $row['id'];
            $hash = $row['password_hash'];
            $tfaEnabled = (int) $row['tfa_enabled'];

            if (!password_verify($password, $hash)) {
                return [
                    'status_code' => 401,
                    'success' => false,
                    'need2fa' => false,
                    'user_id' => null,
                    'message' => 'invalid credentials'
                ];
            }

            if ($tfaEnabled) {
                return [
                    'status_code' => 200,
                    'success' => true,
                    'need2fa' => true,
                    'user_id' => $userId,
                    'message' => '2fa required'
                ];
            }

            $_SESSION['user_id'] = $userId;

            return [
                'status_code' => 200,
                'success' => true,
                'need2fa' => false,
                'user_id' => $userId,
                'message' => 'login successful'
            ];
        } catch (Throwable $e) {
            error_log((string) $e);
            return [
                'status_code' => 500,
                'success' => false,
                'need2fa' => false,
                'user_id' => null,
                'message' => 'internal error'
            ];
        }
    }
}
