<?php
// Database Configuration
// Studify - Student Task Management System

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings, including reverse-proxy HTTPS detection.
    $httpsHeader = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwardedSsl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443) ||
        $httpsHeader === 'https' ||
        $forwardedSsl === 'on'
    );

    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $is_https ? '1' : '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Optional file-based overrides for hosts that do not expose env vars (e.g., shared hosting).
$local_config = [];
$local_config_file = __DIR__ . '/db.local.php';
if (is_file($local_config_file)) {
    $loaded = require $local_config_file;
    if (is_array($loaded)) {
        $local_config = $loaded;
    }
}

// App environment
if (!defined('APP_ENV')) define('APP_ENV', getenv('APP_ENV') ?: ($local_config['APP_ENV'] ?? 'development'));
if (!defined('APP_URL')) define('APP_URL', rtrim((string)(getenv('APP_URL') ?: ($local_config['APP_URL'] ?? '')), '/'));

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Database credentials (prefer env vars, fallback to local config file)
$running_in_docker = is_file('/.dockerenv');
$default_db_host = $running_in_docker ? 'host.docker.internal' : 'localhost';
$db_host = getenv('DB_HOST') ?: ($local_config['DB_HOST'] ?? $default_db_host);
$db_user = getenv('DB_USER') ?: ($local_config['DB_USER'] ?? 'root');
$db_password = getenv('DB_PASS') ?: ($local_config['DB_PASS'] ?? '');
$db_name = getenv('DB_NAME') ?: ($local_config['DB_NAME'] ?? 'studify');
$db_port = (int)(getenv('DB_PORT') ?: ($local_config['DB_PORT'] ?? 3306));
$db_ssl_ca = getenv('DB_SSL_CA') ?: ($local_config['DB_SSL_CA'] ?? '');
$db_ssl_ca_b64 = getenv('DB_SSL_CA_B64') ?: ($local_config['DB_SSL_CA_B64'] ?? '');
$db_ssl_ca_path = '';
if ($db_ssl_ca !== '' && is_file($db_ssl_ca)) {
    $db_ssl_ca_path = $db_ssl_ca;
} elseif ($db_ssl_ca_b64 !== '') {
    $decoded = base64_decode($db_ssl_ca_b64, true);
    if ($decoded !== false) {
        $tmp_path = sys_get_temp_dir() . '/db-ca.pem';
        if (@file_put_contents($tmp_path, $decoded) !== false) {
            $db_ssl_ca_path = $tmp_path;
        }
    }
}

// If localhost is forced in Docker, mysqli may attempt a Unix socket path that does not exist.
if ($running_in_docker && strtolower((string)$db_host) === 'localhost') {
    $db_host = 'host.docker.internal';
}

// Create connection
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli();
if ($db_ssl_ca_path !== '') {
    // Optional TLS for providers like TiDB Cloud.
    $conn->ssl_set(null, null, $db_ssl_ca_path, null, null);
}
$conn->real_connect($db_host, $db_user, $db_password, $db_name, $db_port);

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

// Build absolute URLs when APP_URL is configured, otherwise keep relative paths.
function appUrl($path = '') {
    $normalized = ltrim((string)$path, '/');
    if (APP_URL === '') {
        return $normalized;
    }
    return APP_URL . ($normalized !== '' ? '/' . $normalized : '');
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
