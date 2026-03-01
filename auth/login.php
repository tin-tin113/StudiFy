<?php
/**
 * STUDIFY – Login
 * Secure login with CSRF, rate limiting, session regeneration
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "student/dashboard.php");
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
                if (!empty($redirect) && strpos($redirect, '/') === 0) {
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/logo.png">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="brand-icon"><img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Studify"></div>
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

            <div class="demo-credentials">
                <strong><i class="fas fa-info-circle"></i> Demo Credentials:</strong><br>
                <strong>Email:</strong> student@studify.com &nbsp;|&nbsp; <strong>Password:</strong> password123
            </div>

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
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
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
</body>
</html>
