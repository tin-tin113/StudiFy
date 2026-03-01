<?php
/**
 * STUDIFY – Forgot Password
 * Token-based password reset (no email sending - shows reset link directly for demo)
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

$page_title = 'Forgot Password';
$error = '';
$success = '';
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $email = cleanInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Invalidate old tokens
            $inv = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ?");
            $inv->bind_param("s", $email);
            $inv->execute();
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token, $expires);
            $stmt->execute();
            
            // In production, send email. For demo, show link directly.
            $reset_link = BASE_URL . "auth/reset_password.php?token=" . $token;
            $success = 'Password reset link generated! In production, this would be emailed to you.';
        } else {
            // Generic message to prevent email enumeration
            $success = 'If an account with that email exists, a reset link has been generated.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – Studify</title>
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
                <h2>Reset Password</h2>
                <p>Enter your email to receive a reset link</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($reset_link): ?>
                <div class="demo-credentials">
                    <strong><i class="fas fa-link"></i> Demo Reset Link:</strong><br>
                    <a href="<?php echo htmlspecialchars($reset_link); ?>" style="word-break: break-all; font-size: 12px;">
                        <?php echo htmlspecialchars($reset_link); ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$reset_link): ?>
            <form method="POST" action="">
                <?php echo csrfTokenField(); ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required autofocus>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
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
</body>
</html>
