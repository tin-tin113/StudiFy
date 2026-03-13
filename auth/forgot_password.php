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
            $token_hash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token_hash, $expires);
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
                <div class="alert alert-warning" style="font-size: 12px;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Demo Mode:</strong> In a production environment, this link would be sent to your email instead of being displayed here. This is for demonstration purposes only.
                </div>
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
