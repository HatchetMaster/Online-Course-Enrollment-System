<?php
declare(strict_types=1);
// backend/api/profile_update.php

require_once __DIR__ . '/init.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond_error_payload(405, 'invalid_method', 'use POST for updates');
    }

    $user = require_auth(); // sends 401 if not authorized
    $db = bootstrap_get('db');
    if (!$db) {
        respond_error_payload(500, 'server_error', 'database unavailable');
    }

    // Expect JSON body
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        respond_error_payload(400, 'invalid_json', 'invalid JSON payload');
    }

    // CSRF check
    check_csrf_from_payload($payload);

    // Only email editable for now
    if (!array_key_exists('email', $payload)) {
        respond_error_payload(400, 'missing_field', 'email is required');
    }

    $email = trim((string) $payload['email']);
    if ($email === '') {
        respond_error_payload(400, 'invalid_email', 'email cannot be empty');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_error_payload(400, 'invalid_email', 'invalid email address');
    }

    // prepare encryption and hash
    if (!function_exists('encrypt_blob')) {
        respond_error_payload(500, 'server_error', 'encryption helper not available');
    }

    $emailEnc = encrypt_blob($email);
    $emailHash = hash('sha256', strtolower($email));

    try {
        $stmt = $db->prepare('UPDATE tblStudents SET email_enc = :email_enc, email_hash = :email_hash WHERE id = :id');
        $stmt->execute([
            ':email_enc' => $emailEnc,
            ':email_hash' => $emailHash,
            ':id' => (int) $user['id']
        ]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getCode(), '23000') === 0) {
            respond_error_payload(409, 'conflict', 'email already registered');
        }
        throw $e;
    }

    oces_simple_log('info', 'profile_updated', ['user_id' => $user['id']]);

    respond_success(['message' => 'profile updated'], 200);
} catch (Throwable $e) {
    if (function_exists('oces_simple_log')) {
        oces_simple_log('critical', 'profile_update_exception', ['exception' => (string) $e]);
    }
    respond_error_payload(500, 'internal_error', 'internal server error');
}
