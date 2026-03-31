<?php
// Authentication Helper
// Session management and auth checks

// Start session if not already started (with secure settings)
if (session_status() === PHP_SESSION_NONE) {
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

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is student
function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

// Check if user is admin
function isAdminRole() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Require login - redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    // Idle-based session timeout (2 hours of inactivity)
    $timeout = 7200; // 2 hours in seconds
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        secureLogout();
        header("Location: " . BASE_URL . "auth/login.php?message=" . urlencode('Session expired due to inactivity. Please login again.'));
        exit();
    }
    // Refresh activity timestamp on every authenticated page load
    $_SESSION['last_activity'] = time();
}

// Require admin - redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdminRole()) {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

// Get current logged in user ID
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Get current logged in user role
function getCurrentUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Regenerate session ID after login (prevents session fixation)
function regenerateSession() {
    session_regenerate_id(true);
}

// Secure login - sets session and regenerates ID
function secureLogin($user) {
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']); // Force fresh CSRF token after login
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_activity'] = time();
}

// Secure logout - destroys session properly
function secureLogout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// Check if user account is locked (brute force protection)
function isAccountLocked($email, $conn) {
    // Atomically reset expired lockouts
    $reset = $conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ? AND locked_until IS NOT NULL AND locked_until <= NOW()");
    $reset->bind_param("s", $email);
    $reset->execute();

    $stmt = $conn->prepare("SELECT locked_until FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['locked_until'] && new DateTime($row['locked_until']) > new DateTime()) {
            return true;
        }
    }
    return false;
}

// Increment login attempts (atomic increment + conditional lock)
function incrementLoginAttempts($email, $conn) {
    $lockout_minutes = defined('LOCKOUT_DURATION') ? LOCKOUT_DURATION : 15;
    $max_attempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
    $stmt = $conn->prepare("UPDATE users SET login_attempts = login_attempts + 1,
        locked_until = CASE WHEN login_attempts + 1 >= ? 
            THEN DATE_ADD(NOW(), INTERVAL ? MINUTE) ELSE locked_until END
        WHERE email = ?");
    $stmt->bind_param("iis", $max_attempts, $lockout_minutes, $email);
    $stmt->execute();
}

// Reset login attempts on successful login
function resetLoginAttempts($email, $conn) {
    $stmt = $conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
}

?>
