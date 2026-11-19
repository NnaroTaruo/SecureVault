<?php
/**
 * Core Utility & Authentication Helpers
 *
 * Vault entry encryption/decryption runs strictly in the browser (js/vault-crypto.js).
 */

require_once __DIR__ . '/../config.php';

/**
 * Generate a cryptographically secure hex string.
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes((int)($length / 2)));
}

/**
 * Create a 32-hex-character salt for client-side PBKDF2 key derivation.
 */
function createPasswordSalt() {
    return bin2hex(random_bytes(16));
}

/**
 * Hash a master password using bcrypt (cost 12).
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against its bcrypt hash.
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a 64-character random session token.
 */
function generateSessionToken() {
    return generateRandomString(64);
}

/**
 * Trim and HTML-escape untrusted input.
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate an email address.
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate master password strength (min 8 chars, upper, lower, digit, symbol).
 */
function validatePasswordStrength($password) {
    return preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password);
}

/**
 * Emit a JSON API response and terminate execution.
 */
function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

/**
 * Retrieve and clear the current session flash message.
 */
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? '';
    $type = $_SESSION['flash_type'] ?? 'success';

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);

    return ['message' => $message, 'type' => $type];
}
