<?php
/**
 * STUDIFY – Login
 * Secure login with CSRF, rate limiting, session regeneration
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: " . BASE_URL . "admin/admin_dashboard.php");
    } else {
        header("Location: " . BASE_URL . "student/dashboard.php");
    }
    exit();
}

$page_title = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (isAccountLocked($email, $conn)) {
        $error = 'Account temporarily locked due to too many failed attempts. Please try again in ' . LOCKOUT_DURATION . ' minutes.';
    } else {
        $query = "SELECT id, name, email, password, role FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Success - secure login & reset attempts
                secureLogin($user);
                resetLoginAttempts($email, $conn);
                
                // Redirect to original page or dashboard
                $redirect = $_GET['redirect'] ?? '';
                if (!empty($redirect) && preg_match('#^/[a-zA-Z0-9_./?&=-]+$#', $redirect) && strpos($redirect, '//') === false) {
                    header("Location: " . $redirect);
                } elseif ($user['role'] === 'admin') {
                    header("Location: " . BASE_URL . "admin/admin_dashboard.php");
                } else {
                    header("Location: " . BASE_URL . "student/dashboard.php");
                }
                exit();
            } else {
                incrementLoginAttempts($email, $conn);
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Studify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/style.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='40' height='40' rx='10' fill='%2316A34A'/%3E%3Cpath d='M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z' fill='%23fff' opacity='.9'/%3E%3Cpath d='M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z' fill='%23fff' opacity='.7'/%3E%3C/svg%3E">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="brand-icon">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="40" height="40" rx="10" fill="#16A34A"/>
                        <path d="M20 12c-2.5 0-5 .8-5 .8v14.4s2.5-.8 5-.8 5 .8 5 .8V12.8s-2.5-.8-5-.8z" fill="#fff" opacity=".15"/>
                        <path d="M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z" fill="#fff" opacity=".9"/>
                        <path d="M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z" fill="#fff" opacity=".7"/>
                        <line x1="20" y1="14.5" x2="20" y2="28.5" stroke="#16A34A" stroke-width=".6" opacity=".5"/>
                    </svg>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to your Studify account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Password reset successfully. You can now log in.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfTokenField(); ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           placeholder="you@example.com" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePasswordVisibility(this)" title="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="auth-footer">
                <a href="forgot_password.php" style="font-size: 12px; color: var(--text-muted);">Forgot password?</a>
                <br>
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <script>
    function togglePasswordVisibility(btn) {
        const input = btn.closest('.input-group').querySelector('input');
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
            btn.title = 'Hide password';
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
            btn.title = 'Show password';
        }
    }
    </script>
</body>
</html>
