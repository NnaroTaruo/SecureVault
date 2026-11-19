<?php
/**
 * Vault Account & Category Queries
 *
 * Passwords are encrypted client-side before reaching PHP; this layer only handles
 * metadata queries, ownership-checked deletion, and vault statistics.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Get all categories owned by a user.
 */
function getUserCategories($userId) {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT id, name, icon FROM categories WHERE user_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get account metadata list (without ciphertexts) for a user.
 */
function getUserAccounts($userId) {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare('
        SELECT a.id, a.service_name, a.username, a.website_url, a.category_id,
               c.name AS category_name, c.icon AS category_icon, a.created_at, a.updated_at
        FROM accounts a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.user_id = ?
        ORDER BY c.name ASC, a.service_name ASC
    ');
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Delete an account owned by the user.
 */
function deleteAccount($userId, $accountId) {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT id FROM accounts WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $accountId, $userId);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        return ['success' => false, 'message' => 'Account not found'];
    }

    $stmt = $db->prepare('DELETE FROM accounts WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $accountId, $userId);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Account deleted successfully'];
    }

    return ['success' => false, 'message' => 'Failed to delete account'];
}

/**
 * Get total account count and per-category breakdown for a user.
 */
function getVaultStats($userId) {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM accounts WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    $stmt = $db->prepare('
        SELECT c.name, COUNT(a.id) AS count
        FROM categories c
        LEFT JOIN accounts a ON c.id = a.category_id
        WHERE c.user_id = ?
        GROUP BY c.id, c.name
    ');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $byCategory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return [
        'total' => $total,
        'by_category' => $byCategory
    ];
}
