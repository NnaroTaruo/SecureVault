<?php
/** Run with: php tests/auth-privacy.php (no database or real credentials needed). */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$directory = sys_get_temp_dir() . '/securevault-auth-check-' . bin2hex(random_bytes(8));
mkdir($directory);
mkdir($directory . '/api');
copy(__DIR__ . '/../api/auth.php', $directory . '/api/auth.php');
file_put_contents($directory . '/config.php', '<?php');
file_put_contents($directory . '/auth.php', <<<'PHP'
<?php
function sanitize($value) { return trim($value); }
function isLoggedIn() { return true; }
function getCurrentUserId() { return 1; }
function loginUser(...$args) { return ['success' => false, 'message' => 'Invalid credentials']; }
function registerUser(...$args) { return ['success' => true, 'message' => 'Registered']; }
function updateMasterPassword(...$args) { return ['success' => true, 'message' => 'Updated']; }
function jsonResponse($success, $message, $data = null, $status = 200) {
    echo json_encode(['success' => $success, 'message' => $message, 'status' => $status]);
    exit;
}
PHP);

try {
    foreach (['login' => 401, 'register' => 200, 'update_master_password' => 200] as $action => $status) {
        $script = '$_SERVER["REQUEST_METHOD"] = "POST";'
            . '$_SERVER["REQUEST_URI"] = "/api/auth.php?action=' . $action . '";'
            . '$_GET["action"] = "' . $action . '";'
            . '$_POST = array_fill_keys(["password", "confirm_password", "old_password", "new_password"], "dummy-privacy-check");'
            . 'require "auth.php";';
        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory . '/api');
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the authentication check.');
        }
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $response = json_decode($output, true);
        if ($exitCode !== 0 || $errors !== '' || ($response['status'] ?? null) !== $status
            || str_contains($output, 'dummy-privacy-check') || file_exists($directory . '/api/debug.txt')) {
            throw new RuntimeException('Authentication privacy check failed for ' . $action);
        }
    }
    echo "PASS: login, registration, and password-change requests return JSON without creating a debug log.\n";
} finally {
    foreach (['api/debug.txt', 'api/auth.php', 'auth.php', 'config.php'] as $file) {
        if (is_file($directory . '/' . $file)) {
            unlink($directory . '/' . $file);
        }
    }
    rmdir($directory . '/api');
    rmdir($directory);
}
