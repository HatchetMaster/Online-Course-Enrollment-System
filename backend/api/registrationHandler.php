<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/api_helpers.php';

class RegistrationHandler
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function handle(array $payload): array
    {
        $firstName = isset($payload['firstName']) ? trim((string) $payload['firstName']) : '';
        $lastName = isset($payload['lastName']) ? trim((string) $payload['lastName']) : '';
        $username = isset($payload['username']) ? trim((string) $payload['username']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ($firstName === '' || $lastName === '' || $username === '' || $email === '' || $password === '') {
            return ['status_code' => 400, 'success' => false, 'message' => 'Missing required fields', 'user_id' => null];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status_code' => 400, 'success' => false, 'message' => 'Invalid email address', 'user_id' => null];
        }

        if (strlen($password) < 8) {
            return ['status_code' => 400, 'success' => false, 'message' => 'Password must be at least 8 characters', 'user_id' => null];
        }

        try {
            $username_hash = hmac_lookup($username);
            $username_enc = encrypt_blob($username);

            $email_hash = hmac_lookup($email);
            $email_enc = encrypt_blob($email);

            $first_name_enc = encrypt_blob($firstName);
            $last_name_enc = encrypt_blob($lastName);

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            if ($password_hash === false) {
                throw new RuntimeException('password_hash failure');
            }
        } catch (Throwable $e) {
            log_error('RegistrationHandler', 'Crypto error: ' . $e->getMessage());
            return ['status_code' => 500, 'success' => false, 'message' => 'Server crypto error', 'user_id' => null];
        }

        try {
            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare('SELECT id FROM tblStudents WHERE username_hash = :uhash OR email_hash = :ehash LIMIT 1');
            $check->execute([':uhash' => $username_hash, ':ehash' => $email_hash]);
            if ($check->fetch()) {
                $this->pdo->rollBack();
                return ['status_code' => 409, 'success' => false, 'message' => 'Username or email already registered', 'user_id' => null];
            }

            $insertSql = <<<SQL
                INSERT INTO tblStudents
                  (first_name_enc, last_name_enc, username_enc, username_hash, email_enc, email_hash, password_hash, tfa_enabled, totp_secret, last_login, failed_login_count, locked_until)
                VALUES
                  (:first_enc, :last_enc, :username_enc, :username_hash, :email_enc, :email_hash, :password_hash, 0, NULL, NULL, 0, NULL)
                SQL;

            $stmt = $this->pdo->prepare($insertSql);
            $stmt->execute([
                ':first_enc' => $first_name_enc,
                ':last_enc' => $last_name_enc,
                ':username_enc' => $username_enc,
                ':username_hash' => $username_hash,
                ':email_enc' => $email_enc,
                ':email_hash' => $email_hash,
                ':password_hash' => $password_hash
            ]);

            $newId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            log_info('RegistrationHandler', 'Registered new user', ['user_id' => $newId, 'username_hash' => $username_hash]);

            return ['status_code' => 201, 'success' => true, 'message' => 'Registered successfully', 'user_id' => $newId];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction())
                $this->pdo->rollBack();
            log_error('RegistrationHandler', 'DB error: ' . $e->getMessage());
            if ((string) $e->getCode() === '23000') {
                return ['status_code' => 409, 'success' => false, 'message' => 'Username or email already registered', 'user_id' => null];
            }
            return ['status_code' => 500, 'success' => false, 'message' => 'Database error', 'user_id' => null];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction())
                $this->pdo->rollBack();
            log_error('RegistrationHandler', 'Internal error: ' . $e->getMessage());
            return ['status_code' => 500, 'success' => false, 'message' => 'Internal server error', 'user_id' => null];
        }
    }
}
