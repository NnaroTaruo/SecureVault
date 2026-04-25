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
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare('SELECT master_key_salt FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    jsonResponse(false, 'User not found', null, 401);
}

$userRow = $userResult->fetch_assoc();

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
        // Fetch account directly from db to get encrypted password without decrypting
        $stmt = $db->prepare('SELECT id, service_name, username, password_encrypted, website_url, notes, category_id, created_at, updated_at FROM accounts WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $account = $result->fetch_assoc();
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
        $stmt = $db->prepare('SELECT password_encrypted FROM accounts WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $account = $result->fetch_assoc();
            jsonResponse(true, 'Password revealed', ['password_encrypted' => $account['password_encrypted']]);
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
        $encryptedPassword = $_POST['encrypted_password'] ?? '';
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $websiteUrl = sanitize($_POST['website_url'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (empty($encryptedPassword)) {
            jsonResponse(false, 'Encrypted password is required', null, 400);
        }
        
        $stmt = $db->prepare('INSERT INTO accounts (user_id, service_name, username, password_encrypted, category_id, website_url, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssiss', $userId, $serviceName, $username, $encryptedPassword, $categoryId, $websiteUrl, $notes);
        
        if ($stmt->execute()) {
            jsonResponse(true, 'Account added successfully', ['id' => $db->insert_id]);
        } else {
            jsonResponse(false, 'Failed to add account', null, 500);
        }
        break;
    
    case 'update':
    case 'update_account':
        if ($method !== 'POST') {
            jsonResponse(false, 'Invalid method', null, 400);
        }
        
        $accountId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $serviceName = sanitize($_POST['service_name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $encryptedPassword = $_POST['encrypted_password'] ?? '';
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $websiteUrl = sanitize($_POST['website_url'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (empty($encryptedPassword)) {
            jsonResponse(false, 'Encrypted password is required', null, 400);
        }
        
        $stmt = $db->prepare('UPDATE accounts SET service_name = ?, username = ?, password_encrypted = ?, category_id = ?, website_url = ?, notes = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->bind_param('sssissii', $serviceName, $username, $encryptedPassword, $categoryId, $websiteUrl, $notes, $accountId, $userId);
        
        if ($stmt->execute()) {
            jsonResponse(true, 'Account updated successfully');
        } else {
            jsonResponse(false, 'Failed to update account', null, 500);
        }
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
