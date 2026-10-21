<?php
/**
 * Authentication API Endpoints
 */

// Prevent any output before JSON header
ob_start();
header('Cache-Control: no-store');

require_once '../config.php';
require_once '../auth.php';

if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE) {
    ob_end_clean();
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Service is currently under maintenance']);
    exit;
}

// Set error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Auth Error: $errstr in $errfile:$errline");
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
});

set_exception_handler(function($e) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log("Auth Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
});

header('Content-Type: application/json');
ob_end_clean();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? sanitize($_GET['action']) : (isset($_POST['action']) ? sanitize($_POST['action']) : '');

// Authentication request bodies contain passwords and must never be logged.
switch ($action) {
    case 'register':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $result = registerUser($username, $email, $password, $confirmPassword);
        $statusCode = $result['success'] ? 200 : 422;
        jsonResponse($result['success'], $result['message'] ?? '', $result, $statusCode);
        break;
    
    case 'login':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $result = loginUser($username, $password);
        $statusCode = $result['success'] ? 200 : 401;
        jsonResponse($result['success'], $result['message'], $result['key_check'] ?? null, $statusCode);
        break;
    
    case 'logout':
        logoutUser();
        jsonResponse(true, 'Logged out successfully');
        break;
    
    case 'me':
        if (!isLoggedIn() || !verifySession()) {
            jsonResponse(false, 'Not authenticated', null, 401);
        }
        
        $user = getCurrentUser();
        if ($user) {
            jsonResponse(true, 'User retrieved', ['user' => $user]);
        } else {
            jsonResponse(false, 'User not found', null, 404);
        }
        break;
    
    case 'update_master_password':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        
        if (!isLoggedIn() || !verifySession()) {
            jsonResponse(false, 'Not authenticated', null, 401);
        }
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            jsonResponse(false, 'Reload the vault before changing your master key.', null, 403);
        }
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $encodedAccounts = $_POST['accounts'] ?? '';
        if (!is_string($oldPassword) || !is_string($newPassword) || !is_string($encodedAccounts)
            || !str_starts_with(ltrim($encodedAccounts), '[')) {
            jsonResponse(false, 'A complete encrypted vault is required. Reload the vault and try again.', null, 400);
        }
        $accounts = json_decode($encodedAccounts, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($accounts)) {
            jsonResponse(false, 'Invalid encrypted vault. Reload the vault and try again.', null, 400);
        }
        $result = updateMasterPassword(getCurrentUserId(), $oldPassword, $newPassword, $accounts);

        $statusCode = $result['success'] ? 200 : ($result['status'] ?? 400);
        jsonResponse($result['success'], $result['message'], null, $statusCode);
        break;
    
    default:
        jsonResponse(false, 'Invalid action', null, 400);
}
