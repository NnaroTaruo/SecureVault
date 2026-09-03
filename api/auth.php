<?php
/**
 * Authentication API Endpoints
 */

// Prevent any output before JSON header
ob_start();

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
    ob_end_clean();
    header('Content-Type: application/json');
    error_log("Auth Error: $errstr in $errfile:$errline");
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
});

set_exception_handler(function($e) {
    ob_end_clean();
    header('Content-Type: application/json');
    error_log("Auth Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
});

header('Content-Type: application/json');
ob_end_clean();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? sanitize($_GET['action']) : (isset($_POST['action']) ? sanitize($_POST['action']) : '');

file_put_contents(__DIR__ . '/debug.txt', print_r([
    'time' => date('Y-m-d H:i:s'),
    'method' => $method,
    'get' => $_GET,
    'post' => $_POST,
    'input' => file_get_contents('php://input'),
    'request_uri' => $_SERVER['REQUEST_URI']
], true) . "\n", FILE_APPEND);

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
        jsonResponse($result['success'], $result['message'], null, $statusCode);
        break;
    
    case 'logout':
        logoutUser();
        jsonResponse(true, 'Logged out successfully');
        break;
    
    case 'me':
        if (!isLoggedIn()) {
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
        
        if (!isLoggedIn()) {
            jsonResponse(false, 'Not authenticated', null, 401);
        }
        

        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        // Parse accounts if provided
        $accounts = null;
        if (isset($_POST['accounts']) && !empty($_POST['accounts'])) {
            $accounts = json_decode($_POST['accounts'], true);
        }

        $result = updateMasterPassword(getCurrentUserId(), $oldPassword, $newPassword, $accounts);

        $statusCode = $result['success'] ? 200 : 400;
        jsonResponse($result['success'], $result['message'], null, $statusCode);
        break;
    
    default:
        jsonResponse(false, 'Invalid action', null, 400);
}
