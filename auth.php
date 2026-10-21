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
    
    // Serialize sign-in with key changes so an old-password login cannot finish afterward.
    $db->begin_transaction();
    $stmt = $db->prepare('SELECT id, username, password_hash, master_key_salt FROM users WHERE username = ? OR email = ? FOR UPDATE');
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $db->rollback();
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    $user = $result->fetch_assoc();
    
    if (!verifyPassword($password, $user['password_hash'])) {
        $db->rollback();
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Create session
    session_regenerate_id(true);
    $sessionToken = generateSessionToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    
    $stmt = $db->prepare('INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $user['id'], $sessionToken, $expiresAt);
    
    if ($stmt->execute()) {
        $db->commit();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['username'] = $user['username'];
        $_SESSION['master_key_salt'] = $user['master_key_salt'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['key_check_context'] = bin2hex(random_bytes(16));
        setcookie('sv_signed_out', '', ['expires' => 1, 'path' => parse_url(SITE_URL, PHP_URL_PATH) ?: '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Strict']);
        
        return ['success' => true, 'message' => 'Login successful', 'key_check' => [
            'salt' => $user['master_key_salt'], 'context' => $_SESSION['key_check_context'], 'user_id' => (int)$user['id']
        ]];
    } else {
        $db->rollback();
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
    // An offline explicit sign-out must also block the old HTTP session on reconnect.
    if (($_COOKIE['sv_signed_out'] ?? '') === '1') {
        logoutUser();
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

    if (!is_string($oldPassword) || !is_string($newPassword) || !validatePasswordStrength($newPassword)) {
        return ['success' => false, 'message' => 'New password does not meet strength requirements'];
    }
    if ($oldPassword === $newPassword) {
        return ['success' => false, 'message' => 'Choose a different master key'];
    }
    // bcrypt authenticates at most 72 bytes; never silently truncate a new vault key.
    if (strlen($newPassword) > 72) {
        return ['success' => false, 'message' => 'Your new master key is too long. Try a shorter phrase.'];
    }
    if (!is_array($accounts) || array_values($accounts) !== $accounts) {
        return ['success' => false, 'message' => 'A complete encrypted vault is required. Reload the vault and try again.'];
    }

    $replacements = [];
    foreach ($accounts as $account) {
        if (!is_array($account) || !isset($account['id']) || !is_int($account['id']) || $account['id'] < 1
            || isset($replacements[$account['id']]) || !is_string($account['previous_password_encrypted'] ?? null)
            || !is_string($account['password_encrypted'] ?? null)) {
            return ['success' => false, 'message' => 'Invalid encrypted vault. Reload the vault and try again.'];
        }
        $previous = base64_decode($account['previous_password_encrypted'], true);
        $replacement = base64_decode($account['password_encrypted'], true);
        if ($previous === false || $replacement === false || strlen($previous) < 28
            || strlen($previous) !== strlen($replacement) || substr($previous, 0, 12) === substr($replacement, 0, 12)) {
            return ['success' => false, 'message' => 'Invalid encrypted password data. Nothing was changed.'];
        }
        $replacements[$account['id']] = $account;
    }

    $status = 400;
    try {
        $tables = $db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users', 'accounts', 'sessions') AND ENGINE = 'InnoDB'");
        if ((int)$tables->fetch_assoc()['total'] !== 3) {
            return ['success' => false, 'message' => 'This database needs transactional tables before master keys can be changed.'];
        }
        $db->begin_transaction();
        // All online vault writes take this same user lock and then recheck the session.
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user || !verifySession()) {
            $status = 401;
            throw new DomainException('Your session expired. Sign in again.');
        }
        if (!verifyPassword($oldPassword, $user['password_hash'])) {
            throw new DomainException('Current password is incorrect');
        }

        $stmt = $db->prepare('SELECT id, password_encrypted FROM accounts WHERE user_id = ? ORDER BY id FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $currentAccounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $status = 409;
        if (count($currentAccounts) !== count($replacements)) {
            throw new DomainException('Your vault changed. Reload it and try again; nothing was changed.');
        }
        foreach ($currentAccounts as $account) {
            $replacement = $replacements[$account['id']] ?? null;
            if (!$replacement || $replacement['previous_password_encrypted'] !== $account['password_encrypted']) {
                throw new DomainException('Your vault changed. Reload it and try again; nothing was changed.');
            }
        }

        $stmt = $db->prepare('UPDATE accounts SET password_encrypted = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
        foreach ($replacements as $accountId => $account) {
            $encryptedPassword = $account['password_encrypted'];
            $stmt->bind_param('sii', $encryptedPassword, $accountId, $userId);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new RuntimeException('Encrypted account update failed');
            }
        }
        $newHash = hashPassword($newPassword);
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $userId);
        if (!$stmt->execute()) throw new RuntimeException('Password hash update failed');
        $stmt = $db->prepare('DELETE FROM sessions WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new RuntimeException('Session invalidation failed');
        if (!$db->commit()) throw new RuntimeException('Password change commit failed');
        $_SESSION = [];
        return ['success' => true, 'message' => 'Master key changed. Sign in with your new key.'];
    } catch (DomainException $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage(), 'status' => $status];
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        error_log('Master password change failed (code ' . $e->getCode() . ')');
        return ['success' => false, 'message' => 'Could not complete the key change. Sign in online to refresh your vault.', 'status' => 500];
    }
}
