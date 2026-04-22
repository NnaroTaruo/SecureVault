<?php
/**
 * Account API Endpoints
 */

// Prevent any output before JSON header
ob_start();

require_once '../config.php';
require_once '../auth.php';
require_once '../accounts.php';

// Set error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    header('Content-Type: application/json');
    error_log("API Error: $errstr in $errfile:$errline");
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function($e) {
    ob_end_clean();
    header('Content-Type: application/json');
    error_log("API Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

try {
    requireLogin();
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');
ob_end_clean();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? sanitize($_GET['action']) : (isset($_POST['action']) ? sanitize($_POST['action']) : '');

$userId = getCurrentUserId();
$masterPassword = isset($_POST['master_password']) ? $_POST['master_password'] : '';

// Verify master password is correct
if (empty($masterPassword)) {
    jsonResponse(false, 'Master password required', null, 401);
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare('SELECT password_hash, master_key_salt FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    jsonResponse(false, 'User not found', null, 401);
}

$userRow = $userResult->fetch_assoc();

// Verify master password hash
if (!verifyPassword($masterPassword, $userRow['password_hash'])) {
    jsonResponse(false, 'Invalid master password', null, 401);
}

// Now derive encryption key from verified password
$encryptionKey = deriveEncryptionKey($masterPassword, $userRow['master_key_salt']);
if (!$encryptionKey) {
    jsonResponse(false, 'Failed to derive encryption key', null, 500);
}

switch ($action) {
    case 'get_accounts':
        $accounts = getUserAccounts($userId);
        // Don't return encrypted passwords in list
        foreach ($accounts as &$acc) {
            unset($acc['password_encrypted']);
        }
        jsonResponse(true, 'Accounts retrieved', ['accounts' => $accounts]);
        break;
    
    case 'get_account':
        $accountId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $account = getAccount($userId, $accountId, $encryptionKey);
        if ($account) {
            unset($account['password_encrypted']);
            jsonResponse(true, 'Account retrieved', ['account' => $account]);
        } else {
            jsonResponse(false, 'Account not found', null, 404);
        }
        break;
    
    case 'reveal':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        $accountId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $account = getAccount($userId, $accountId, $encryptionKey);
        if ($account) {
            jsonResponse(true, 'Password revealed', ['password' => $account['password']]);
        } else {
            jsonResponse(false, 'Account not found', null, 404);
        }
        break;
    
    case 'add_account':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        
        $serviceName = sanitize($_POST['service_name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $websiteUrl = sanitize($_POST['website_url'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        $result = addAccount($userId, $serviceName, $username, $password, $categoryId, $websiteUrl, $notes, $encryptionKey);
        jsonResponse($result['success'], $result['message'], $result, $result['success'] ? 200 : 400);
        break;
    
    case 'update':
    case 'update_account':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        
        $accountId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $serviceName = sanitize($_POST['service_name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $websiteUrl = sanitize($_POST['website_url'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        $result = updateAccount($userId, $accountId, $serviceName, $username, $password, $categoryId, $websiteUrl, $notes, $encryptionKey);
        jsonResponse($result['success'], $result['message'], null, $result['success'] ? 200 : 400);
        break;
    
    case 'delete_account':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        
        $accountId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $result = deleteAccount($userId, $accountId);
        jsonResponse($result['success'], $result['message'], null, $result['success'] ? 200 : 400);
        break;
    
    case 'get_stats':
        $stats = getVaultStats($userId);
        jsonResponse(true, 'Stats retrieved', ['stats' => $stats]);
        break;
    
    default:
        jsonResponse(false, 'Invalid action', null, 400);
}
