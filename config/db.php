<?php
// Database Configuration
// Studify - Student Task Management System

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Database credentials (use environment variables in production)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_password = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'studify';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    die('Database connection failed. Please try again later.');
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Define constants for success and error responses
if (!defined('SUCCESS')) define('SUCCESS', 1);
if (!defined('ERROR')) define('ERROR', 0);

// Max login attempts before lockout
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOCKOUT_DURATION')) define('LOCKOUT_DURATION', 15); // minutes

// File upload settings
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/../uploads/');
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar']);

// Function to clean user input (trim only - use prepared statements for SQL, htmlspecialchars for output)
function cleanInput($data) {
    return trim($data);
}

// Legacy alias – trim only, NOT a security sanitizer.
// SQL safety: use prepared statements. XSS safety: use htmlspecialchars() on output.
function sanitize($data) {
    return cleanInput($data);
}

// Output escaping shorthand — always use when echoing user data into HTML
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ─── CSRF Protection ───
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// Alias used in templates
function getCSRFField() {
    return csrfTokenField();
}

function validateCSRFToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCSRF() {
    if (!validateCSRFToken()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
            exit();
        }
        $_SESSION['message'] = 'Security token expired. Please try again.';
        $_SESSION['message_type'] = 'error';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit();
    }
    // Token persists for the session lifetime.
    // It is regenerated naturally on login (session_regenerate_id) and logout (session destroy).
    // This avoids breaking multi-tab usage, back-button navigation, and AJAX after form posts.
}

// Function to redirect with message
function redirect($location, $message = '', $type = '') {
    if (!empty($message)) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type; // success, error, warning, info
    }
    header("Location: " . $location);
    exit();
}
?>
