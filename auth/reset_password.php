<?php
/**
 * STUDIFY – Reset Password
 * Validates token and allows password change
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

$token = $_GET['token'] ?? '';
$error = '';
$valid = false;
$email = '';

// Validate token
if (!empty($token)) {
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND used = 0");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (new DateTime($row['expires_at']) > new DateTime()) {
            $valid = true;
            $email = $row['email'];
        } else {
            $error = 'This reset link has expired. Please request a new one.';
        }
    } else {
        $error = 'Invalid or already used reset link.';
    }
} else {
    $error = 'No reset token provided.';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    requireCSRF();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || strlen($password) < 8) {
        $error = 'Password must be at least 8 characters with at least one uppercase letter and one number.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one uppercase letter and one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ?, login_attempts = 0, locked_until = NULL WHERE email = ?");
        $stmt->bind_param("ss", $hashed, $email);
        $stmt->execute();
        
        // Mark token as used
        $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        
        header("Location: login.php?reset=success");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – Studify</title>
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
                <h2>Set New Password</h2>
                <p>Create a new password for your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($valid): ?>
            <form method="POST" action="">
                <?php echo csrfTokenField(); ?>
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Min 8 chars, 1 uppercase, 1 number" required>
                        <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePasswordVisibility(this)" title="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required>
                        <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePasswordVisibility(this)" title="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>
            <?php endif; ?>

            <div class="auth-footer">
                <a href="login.php">← Back to Login</a>
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
