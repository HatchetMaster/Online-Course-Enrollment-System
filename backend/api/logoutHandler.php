<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/api_helpers.php';

class LogoutHandler
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function handle(): array
    {
        try {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();

            return ['status_code' => 200, 'success' => true, 'message' => 'logged out'];
        } catch (Throwable $e) {
            error_log((string) $e);
            return ['status_code' => 500, 'success' => false, 'message' => 'internal error'];
        }
    }
}
