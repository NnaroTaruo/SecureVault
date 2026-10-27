<?php
/**
 * SecureVault Configuration
 * 
 * SETUP: Copy this file to config.php and fill in your own values.
 * NEVER commit config.php to version control!
 */

// Database Configuration
define('DB_HOST', 'localhost');           // e.g. localhost or sql311.infinityfree.com
define('DB_USER', 'your_db_user');        // e.g. root or if0_12345678
define('DB_PASS', 'your_db_password');    // Your database password
define('DB_NAME', 'your_db_name');        // e.g. securevault or if0_12345678_securevault
define('DB_PORT', 3306);

// Encryption Settings
define('ENCRYPTION_ALGO', 'aes-256-gcm');
define('ENCRYPTION_KEY_DERIVATION', 'pbkdf2');

// Session Settings
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'securevault_session');

// Site Settings
define('SITE_NAME', 'SecureVault');
define('SITE_URL', 'http://localhost/SecureVault/'); // Use your HTTPS URL in production
define('MAINTENANCE_MODE', false);

// Security
define('MAX_LOGIN_ATTEMPTS', 5);       // Failed guesses per account or unknown identifier
define('LOGIN_IP_MAX_ATTEMPTS', 25);   // Failed guesses across accounts from one REMOTE_ADDR
define('LOCKOUT_DURATION', 900);       // 15-minute observation window and cooldown

// Environment: 'development' or 'production'
define('ENVIRONMENT', 'development');
define('DEBUG_MODE', true);

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}

// Headers for security
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
