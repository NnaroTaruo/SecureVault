<?php
/**
 * Authentication Functions
 */

require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

session_start();

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current user details
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = Database::getInstance()->getConnection();
    $userId = getCurrentUserId();
    
    $stmt = $db->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Register new user
 */
function registerUser($username, $email, $password, $confirmPassword) {
    $db = Database::getInstance()->getConnection();
    
    $errors = [];
    
    // Validation
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if (!isValidEmail($email)) {
        $errors[] = 'Invalid email address';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!validatePasswordStrength($password)) {
        $errors[] = 'Password must contain uppercase, lowercase, numbers, and special characters';
    }
    
    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Check if username or email exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'errors' => ['Username or email already exists']];
    }
    
    // Create user
    $passwordHash = hashPassword($password);
    $masterKeySalt = createPasswordSalt();
    
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, master_key_salt) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $username, $email, $passwordHash, $masterKeySalt);
    
    if ($stmt->execute()) {
        $userId = $db->insert_id;
        
        // Create default categories
        $categories = ['Gaming', 'University', 'Financial', 'Social Media'];
        $categoryStmt = $db->prepare('INSERT INTO categories (user_id, name, icon) VALUES (?, ?, ?)');
        
        foreach ($categories as $catName) {
            $icon = match($catName) {
                'Gaming' => '🎮',
                'University' => '🎓',
                'Financial' => '💰',
                'Social Media' => '📱',
                default => '📁'
            };
            $categoryStmt->bind_param('iss', $userId, $catName, $icon);
            $categoryStmt->execute();
        }
        
        return ['success' => true, 'message' => 'Registration successful. Please log in.'];
    } else {
        return ['success' => false, 'errors' => ['Registration failed']];
    }
}

/**
 * Login user
 */
function loginUser($username, $password) {
    $db = Database::getInstance()->getConnection();
    
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password required'];
    }
    
    $stmt = $db->prepare('SELECT id, username, password_hash, master_key_salt FROM users WHERE username = ? OR email = ?');
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    $user = $result->fetch_assoc();
    
    if (!verifyPassword($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Create session
    session_regenerate_id(true);
    $sessionToken = generateSessionToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    
    $stmt = $db->prepare('INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $user['id'], $sessionToken, $expiresAt);
    
    if ($stmt->execute()) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['username'] = $user['username'];
        $_SESSION['master_key_salt'] = $user['master_key_salt'];
        
        return ['success' => true, 'message' => 'Login successful'];
    } else {
        return ['success' => false, 'message' => 'Login failed'];
    }
}

/**
 * Logout user
 */
function logoutUser() {
    $db = Database::getInstance()->getConnection();
    
    if (isLoggedIn()) {
        $sessionToken = $_SESSION['session_token'];
        $stmt = $db->prepare('DELETE FROM sessions WHERE session_token = ?');
        $stmt->bind_param('s', $sessionToken);
        $stmt->execute();
    }
    
    session_destroy();
    return true;
}

/**
 * Verify session is valid
 */
function verifySession() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = Database::getInstance()->getConnection();
    $sessionToken = $_SESSION['session_token'];
    $userId = getCurrentUserId();
    
    $stmt = $db->prepare('SELECT id FROM sessions WHERE session_token = ? AND user_id = ? AND expires_at > NOW()');
    $stmt->bind_param('si', $sessionToken, $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        logoutUser();
        return false;
    }
    
    return true;
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn() || !verifySession()) {
        header('Location: ' . SITE_URL . 'login.php');
        exit;
    }
}

/**
 * Update master password
 */
function updateMasterPassword($userId, $oldPassword, $newPassword, $accounts = null) {
    $db = Database::getInstance()->getConnection();
    
    // Get current password hash
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $user = $result->fetch_assoc();
    
    if (!verifyPassword($oldPassword, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    if (!validatePasswordStrength($newPassword)) {
        return ['success' => false, 'message' => 'New password does not meet strength requirements'];
    }
    
    $newHash = hashPassword($newPassword);
    
    // Start transaction for atomic update
    $db->begin_transaction();

    try {
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $userId);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update password hash');
        }

        // Update encrypted passwords if provided
        if ($accounts !== null && is_array($accounts)) {
            $updateStmt = $db->prepare('UPDATE accounts SET password_encrypted = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');

            foreach ($accounts as $acc) {
                if (!isset($acc['id']) || !isset($acc['password_encrypted'])) {
                    continue; // Skip invalid entries
                }

                $accountId = (int)$acc['id'];
                $encryptedPassword = $acc['password_encrypted'];

                $updateStmt->bind_param('sii', $encryptedPassword, $accountId, $userId);
                if (!$updateStmt->execute()) {
                    throw new Exception('Failed to update an account');
                }
            }
        }

        $db->commit();
        return ['success' => true, 'message' => 'Master password updated successfully'];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Failed to update password: ' . $e->getMessage()];
    }
}
