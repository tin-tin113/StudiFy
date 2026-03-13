<?php
// Authentication Helper
// Session management and auth checks

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
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
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in_at'] = time();
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
    $stmt = $conn->prepare("SELECT login_attempts, locked_until FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['locked_until'] && new DateTime($row['locked_until']) > new DateTime()) {
            return true;
        }
        // Reset if lockout expired
        if ($row['locked_until'] && new DateTime($row['locked_until']) <= new DateTime()) {
            $reset = $conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?");
            $reset->bind_param("s", $email);
            $reset->execute();
        }
    }
    return false;
}

// Increment login attempts
function incrementLoginAttempts($email, $conn) {
    $stmt = $conn->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // Check if should lock
    $check = $conn->prepare("SELECT login_attempts FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    if ($row && $row['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $lock_until = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_DURATION . ' minutes'));
        $lock = $conn->prepare("UPDATE users SET locked_until = ? WHERE email = ?");
        $lock->bind_param("ss", $lock_until, $email);
        $lock->execute();
    }
}

// Reset login attempts on successful login
function resetLoginAttempts($email, $conn) {
    $stmt = $conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
}

?>
